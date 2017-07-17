=== Plugin Name ===
Contributors: brainpulselabs
Donate link: http://www.brainpulse.ca/
Tags: riskified, stripe, woocommerce, ecommerce, e-commerce, store, sales, sell, shop, cart, checkout, downloadable, downloads, paypal, storefront
Requires at least: 4.1
Tested up to: 4.6
Stable tag: 1.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Fraudify Wordpress extension was developed to support a simple and efficient integration process using Riskified, WooCommerce and Stripe backend infrastructure.

It allows you to secure your transactions using Riskified directly from your WooCommerce installation. 

== Description ==

Fraudify Wordpress extension was developed to support a simple and efficient integration process using Riskified, WooCommerce and Stripe backend infrastructure.

Features:

* Communicates with Riskified service to validate and secure each transaction.
* Shows status of each transaction on WooCommerce dashboard (Pending, Approved, Rejected) 

Fraudify currently supports 2 stripe plugins. Please choose one of them:

* [WooCommerce Stripe Payment Gateway](https://wordpress.org/plugins/woocommerce-gateway-stripe/ "WooCommerce Stripe Payment Gateway") 
* [Stripe for WooCommerce](https://wordpress.org/plugins/stripe-for-woocommerce/ "Stripe for WooCommerce") 

== Installation ==

Note: Please make sure you have installed the following plugins first:

* [WooCommerce](https://wordpress.org/plugins/woocommerce/ "WooCommerce") 

Fraudify currently supports 2 stripe plugins. Please choose one of them:

* [WooCommerce Stripe Payment Gateway](https://wordpress.org/plugins/woocommerce-gateway-stripe/ "WooCommerce Stripe Payment Gateway") 
* [Stripe for WooCommerce](https://wordpress.org/plugins/stripe-for-woocommerce/ "Stripe for WooCommerce") 

1. Please link your Stripe account with your Riskified account. See the following link for detail information : 
[Guide to API Integration with Stripe as Gateway](http://www.riskified.com/documentation/stripe-gateway.html "Guide to API Integration with Stripe as Gateway")

If you don't have a Riskified account yet you will have to contact their customer support. You will not be able to test Fraudify without a Riskified account.

Note that Fraudify takes care of step number 2 of the guide above. You only have to do steps 1 and 3.

2.a WordPress upload - For most users, this is probably the simplest installation method. To install the Fraudify plugin using this method, please follow these steps:

* Login to your WordPress admin panel
* Navigate to Plugins > Add New > Upload Plugin
* Click on Choose File and select fraudify.zip
* Click on Install Now

2.b FTP upload - If you would like to install the Fraudify plugin via FTP, please follow these steps:

* Extract the fraudify.zip file you previously located. You should now see a folder named fraudify
* Using an FTP client, login to the server where your WordPress website is hosted
* Using an FTP client, navigate to the /wp-content/plugins/ directory under your WordPress website's root directory
* Using an FTP client, upload the previously extracted fraudify folder to the plugins directory on your remote server

3. Once the installation is complete, Fraudify plugin will be ready for use. Now all you need to do is navigate to Plugins > Installed plugins and activate Fraudify plugin. After you have done this, you should see Fraudify appear in the left navigation bar of your WordPress admin panel under  Settings > Fraudify.

4. The last step is to enter your "Shop Domain" and "Auth Token" under Settings > Fraudify. Both of these items can be found in your Riskified admin panel. Your "Order notification endpoint" can be found in the top of this page. This piece information will be entered in your Riskified admin panel. See screenshots.

NOTE: The following steps are needed if you are using WooCommerce before 2.6 and using "WooCommerce Stripe Payment Gateway". Please add the following line to the specified file:

File: wp-content\plugins\woocommerce-gateway-stripe\includes\legacy\class-wc-gateway-stripe.php

Line: 349

Line current looks like this: "return $post_data;"

It should looks like this: 

"return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order, $source );"

5. To be able to test you have to pass an order on your website. Once that is done go to WooCommerce > Orders. You will see that the Riskified shield is gray, which means "Pending". Now go to Riskified sandbox and submit a result to this newly created transaction. Go back to your Orders list and refresh the page. You should see the Riskified shield change colors.

== Screenshots ==

1. Setup page on Wordpress
2. Setup page on Riskified
3. Verify order status on WooCommerce Order listing

== Changelog ==

= 1.2.2 =
* Fixed issues with Riskified beacon
* Added billing phone number on riskified metadata

= 1.1 =
* Added support for "WooCommerce Stripe Payment Gateway"
* Improved installation steps

= 1.0 =
* Release of first version