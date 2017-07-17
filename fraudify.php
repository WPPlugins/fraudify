<?php
/*
Plugin Name: Fraudify
Description: This plugin enables your ecommerce to do fraud detection using Riskfied and Stripe
Version:     1.0
Author:      BrainPulse Labs

Copyright © 2016 BrainPulse Labs.

Fraudify is an Open Source project licensed under the terms of the LGPLv3 license. 
Please see http://www.gnu.org/licenses/lgpl-3.0.html for license text or LICENSE 
file distributed with the source code.

Some subscriptions allow to use Fraudify under the proprietary license, allowing private 
forks and modifications of Fraudify. Please email contact@brainpulselabs.com for details
*/

include __DIR__.'/riskified_php_sdk/src/Riskified/autoloader.php';

use Riskified\Common\Riskified;
use Riskified\Common\Signature;
use Riskified\DecisionNotification\Model;

if ( ! function_exists('fraudify_write_log')) {
   function fraudify_write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
   }
}

function riskified_add_menu_icons_styles(){
?>

<style>
	.riskified-status-icon.status-pending {
		color: #b8b8b8;
	}

	.riskified-status-icon.status-declined {
		color: #AA0000;
	}

	.riskified-status-icon.status-approved {
		color: #81B03B;
	}
</style>

<?php
}
add_action( 'admin_head', 'riskified_add_menu_icons_styles' );

function fraudify_init() {
 	//fraudify_write_log( 'fraudify_init 2' );

 	if(!session_id()) {
        session_start();
    }

    // https://wordpress.org/plugins/stripe-for-woocommerce/
	add_filter( 's4wc_charge_data', 'fraudify_add_stripe_charge_data', 10, 3 );

	// https://wordpress.org/plugins/woocommerce-gateway-stripe/
	add_filter( 'wc_stripe_generate_payment_request', 'fraudify_add_stripe_charge_data2', 10, 3 );
	
	add_rewrite_rule('^api/fraudify/?([0-9]+)?/?','index.php?__fraudify=1&fraudify=$matches[1]','top');
	add_filter('query_vars', 'fraudify_add_query_vars', 0);
}
add_action( 'init', 'fraudify_init', 1);
add_action('parse_request', 'fraudify_sniff_requests');

/** Add public query vars
*	@param array $vars List of current public query vars
*	@return array $vars 
*/
function fraudify_add_query_vars($vars){
	$vars[] = '__api';
	$vars[] = 'fraudify';
	return $vars;
}

/**	Sniff Requests
*	This is where we hijack all API requests
* 	If $_GET['__api'] is set, we kill WP and serve up pug bomb awesomeness
*	@return die if API request
*/
function fraudify_sniff_requests(){
	global $wp;
	if(isset($wp->query_vars['__api'])){
		fraudify_handle_request();
		exit;
	}
}

/** Handle Requests
*	This is where we send off for an intense pug bomb package
*	@return void 
*/
function fraudify_handle_request(){
	global $wp;
	$shouldHandle = $wp->query_vars['fraudify'];

	if(!$shouldHandle) {
		exit;
	}

	//fraudify_write_log("received post from Riskified 2");

	if ( empty( $_SERVER['HTTP_X_RISKIFIED_HMAC_SHA256'] ) ) die();

	//fraudify_write_log("received post from Riskified 3");

	$options = get_option( 'fraudify_settings' );

	$domain =  $options['fraudify_shop_domain'];
	$authToken = $options['fraudify_auth_token'];

	//fraudify_write_log($domain . ' - ' . $authToken);

	Riskified::init($domain, $authToken);

	$signature = new Signature\HttpDataSignature();

	$valid_headers = array(
		$signature::HMAC_HEADER_NAME
	);

	$canonical_headers = array_reduce(array_map('fraudify_map_keys', array_keys($_SERVER), $_SERVER), 'fraudify_reduce_keys');

	$body = @file_get_contents('php://input');
	$headers = array_intersect_key($canonical_headers, array_flip($valid_headers));

	$notification = new Model\Notification($signature, $headers, $body);
	// TODO add message to order history
	$msg = "Order #$notification->id changed to status '$notification->status' with message '$notification->description'\n";

	var_dump($notification);

	$orders = get_posts(array(
		'post_type' => 'shop_order',
		'posts_per_page' => 1,
		'fields' => 'ids',
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_query' => array(
			array(
				'key' => '_transaction_id',
				'value' => $notification->id
			)
		)
	));

	if ( $orders[0] )
	{
		update_post_meta( $orders[0], '_riskified_status', $notification->status );
	} else {
		fraudify_write_log('Fraudify didnt find order : ' . $notification->id);
	}
}
	
function fraudify_map_keys($header, $value) {
	$canonical_header = str_replace('HTTP-','', str_replace('_', '-', strtoupper(trim($header))));
	return array ($canonical_header => $value);
};

function fraudify_reduce_keys($carry, $item) {
	if (is_null($carry)) {
		$carry=array();
	}
	return array_merge($carry, $item);
};

function fraudify_install() {
 	// flush rewrite rules - only do this on activation as anything more frequent is bad!
    flush_rewrite_rules();

}
register_activation_hook( __FILE__, 'fraudify_install' );

function fraudify_deactivation() {
  	// flush rules on deactivate as well so they're not left hanging around uselessly
    flush_rewrite_rules();
 
}
register_deactivation_hook( __FILE__, 'fraudify_deactivation' );

function fraudify_uninstall() {
 
}
register_uninstall_hook( __FILE__, 'fraudify_uninstall' );

add_action('wp_logout', 'fraudify_myEndSession');
//add_action('wp_login', 'myEndSession');
function fraudify_myEndSession() {
    session_destroy ();
}

/*
 * Add all your sections, fields and settings during admin_init
 */
function fraudify_admin_init() {
	//fraudify_write_log( 'fraudify_admin_init' );
	add_filter( 'manage_shop_order_posts_columns', 'fraudify_admin_column' , 15 );
	add_action( 'manage_shop_order_posts_custom_column', 'fraudify_admin_column_render', 4 );
} 
add_action( 'admin_init', 'fraudify_admin_init' );

/**
 * Riskified column header
 * @param $existing_columns
 * @return array
 */
function fraudify_admin_column( $existing_columns )
{
	//fraudify_write_log( 'admin_column' );

	$position = 6;
	$part1 = array_slice( $existing_columns, 0, $position );
	$part2 = array_slice( $existing_columns, $position );
	$col = array( 'riskified' => '<span class="dashicons dashicons-shield-alt tips" data-tip="Riskified Status"></span>');

	return array_merge($part1, $col, $part2 );
}

/**
 * Riskified column render
 * @param $column
 */
function fraudify_admin_column_render( $column )
{
	//fraudify_write_log( 'admin_column_render' );

	if ( $column !== 'riskified' ) return;

	global $post;

	if ( empty( $the_order ) || $the_order->id != $post->ID ) {
		$the_order = wc_get_order( $post->ID );
	}

	if ( $the_order->payment_method == 's4wc' || $the_order->payment_method == 'stripe')
	{
		$status = get_post_meta( $post->ID, '_riskified_status', true );

		if ( ! $status ) $status = 'pending';

		echo '<span class="riskified-status-icon tips dashicons dashicons-shield-alt status-'.$status.'" data-tip="'.ucwords($status).'"></span>';
	}
	else
	{
		echo '-';
	}
}

/*
 * Add riskified beacon to wp footer
 */
function fraudify_add_riskified_beacon() {
	//fraudify_write_log( 'add_riskified_beacon' );
	$options = get_option( 'fraudify_settings' );
	$store_domain = $options['fraudify_shop_domain'];

    ?>
		<script type="text/javascript">
			//<![CDATA[
			(function() {
				function riskifiedBeaconLoad() {
					var store_domain = '<?php echo $store_domain; ?>';
					var session_id = '<?php echo session_id(); ?>';
					var url = ('https:' == document.location.protocol ? 'https://' : 'http://')
						+ "beacon.riskified.com?shop=" + store_domain + "&sid=" + session_id;
					var s = document.createElement('script');
					s.type = 'text/javascript';
					s.async = true;
					s.src = url;
					var x = document.getElementsByTagName('script')[0];
					x.parentNode.insertBefore(s, x);
				}
				riskifiedBeaconLoad();
			})();
			//]]>
		</script>

<?php
}
add_action( 'wp_footer', 'fraudify_add_riskified_beacon' );

/**
 * Function for adding product metadata to the Stripe charge
 *
 * @param        array $charge_data
 * @param        array $form_data Data that's pulled from the customer, contains customer name, email, charge amount, etc.
 * @param        WC_Order $order The order object
 * @return       array $charge_data
 */
function fraudify_add_stripe_charge_data( $charge_data, $form_data, $order )
{
	fraudify_write_log( 'fraudify_add_stripe_charge_data' );
	return fraudify_add_stripe_charge_data_internal($charge_data, $order);
}

function fraudify_add_stripe_charge_data2( $charge_data, $order, $source )
{
	fraudify_write_log( 'fraudify_add_stripe_charge_data2' );
	return fraudify_add_stripe_charge_data_internal($charge_data, $order);
}

function fraudify_add_stripe_charge_data_internal($charge_data, $order)  
{
	//fraudify_write_log( 'add_stripe_charge_data_internal' );

	if ( ! isset( $charge_data['metadata'] ) ) {
		$charge_data['metadata'] = array();
	}

	$address = array(
		'city' => $order->shipping_city,
		'line1' => $order->shipping_address_1,
		'line2' => $order->shipping_address_2,
		'postal_code' => $order->shipping_postcode,
		'state' => $order->shipping_state,
		'country' => $order->shipping_country,

	);

	$charge_data['shipping'] = array(
		'name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
		'address' => $address,
		'phone' => $order->billing_phone
	);

	$charge_data['metadata']['billing_company']  = $order->billing_company;
	$charge_data['metadata']['billing_phone']    = $order->billing_phone;
	$charge_data['metadata']['email']            = $order->billing_email;

	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) )
	{
		$charge_data['metadata']['browser_ip'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
	else
	{
		$charge_data['metadata']['browser_ip'] = $_SERVER['REMOTE_ADDR'];

	}
	$charge_data['metadata']['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
	$charge_data['metadata']['device_id']  = session_id();
	$charge_data['metadata']['source']  = "website";
	$charge_data['metadata']['updated'] =  date_timestamp_get(date_create());

	// line items
	$formatted_order_items = array();
	foreach( $order->get_items() as $item )
	{
		$formatted_order_items[] = array(
			't' => $item['name'],
			'p' => ( $item['line_subtotal'] / $item['qty'] ),
			'q' => $item['qty'],
		);
	}

	$charge_data['metadata']['line_items'] = json_encode( $formatted_order_items );

	//fraudify_write_log( var_export($order, true));

	//fraudify_write_log( var_export($charge_data, true));

	return $charge_data;
}


//================ admin settings page =================//

add_action( 'admin_menu', 'fraudify_add_admin_menu' );
add_action( 'admin_init', 'fraudify_settings_init' );

function fraudify_add_admin_menu(  ) { 

	add_options_page( 'Fraudify', 'Fraudify', 'manage_options', 'fraudify_for_stripe', 'fraudify_options_page' );

}

function fraudify_settings_init(  ) { 

	register_setting( 'pluginPage', 'fraudify_settings' );

	add_settings_section(
		'fraudify_pluginPage_section', 
		__( '', 'Wordpress' ), 
		'fraudify_settings_section_callback', 
		'pluginPage'
	);

	add_settings_field( 
		'fraudify_shop_domain', 
		__( 'Shop Domain', 'Wordpress' ), 
		'fraudify_shop_domain_render', 
		'pluginPage', 
		'fraudify_pluginPage_section' 
	);

	add_settings_field( 
		'fraudify_auth_token', 
		__( 'Auth token', 'Wordpress' ), 
		'fraudify_auth_token_render', 
		'pluginPage', 
		'fraudify_pluginPage_section' 
	);


}

function fraudify_shop_domain_render(  ) { 

	$options = get_option( 'fraudify_settings' );
	?>
	<input type='text' size='50' name='fraudify_settings[fraudify_shop_domain]' value='<?php echo $options['fraudify_shop_domain']; ?>'>
	<?php

}

function fraudify_auth_token_render(  ) { 

	$options = get_option( 'fraudify_settings' );
	?>
	<input type='text' size='50' name='fraudify_settings[fraudify_auth_token]' value='<?php echo $options['fraudify_auth_token']; ?>'>
	<?php

}

function fraudify_settings_section_callback(  ) { 

	$url = home_url( "index.php?__api=1&fraudify=1" );
	echo __( 'Your riskified "Order notification endpoint" is : ', 'Wordpress' );
	echo '<b>';
	echo $url;
	echo '</b>';

}

function fraudify_options_page(  ) { 

	?>
	<form action='options.php' method='post'>

		<h2>Fraudify Settings</h2>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		submit_button();
		?>

	</form>
	<?php

}

