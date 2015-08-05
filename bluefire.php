<?php
/*
Plugin Name: WooCommerce BlueFire Payment Module
Description: BlueFire Donations Payment
Version: 0.4
Author: BlueFire
Author URI: https://gobluefire.com/
*/

add_action('plugins_loaded', 'init_bluefire', 0);

function init_bluefire() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	class WC_BlueFire extends WC_Payment_Gateway {

		public function __construct() {
			
			$this->id = 'bluefire';
			$this->method_title	= __('BlueFire', 'woothemes');
			$this->icon = apply_filters('woocommerce_bluefire_icon', plugins_url('logo.png',__FILE__));
			$this->has_fields = false;
			
			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->pay_description = $this->settings['pay_description'];
			$this->form_key = $this->settings['form_key'];
			$this->secret_key = $this->settings['secret_key'];
			$this->host_url = $this->settings['host_url'];
			$this->success_url = $this->settings['success_url'];
			$this->fund_name = $this->settings['fund_name'];
			$this->fund_number = $this->settings['fund_number'];
			
			// Actions
			add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_receipt_bluefire', array(&$this, 'receipt_page'));

		}
		
		function init_form_fields() {
			
			$this->form_fields = array(
				'enabled' => array(
				    'title' => __( 'Enable/Disable', 'woothemes' ),
				    'type' => 'checkbox',
				    'label' => __( 'Enable BlueFire Payment', 'woothemes' ),
				    'default' => 'yes'
				),
				
				'title' => array(
				    'title' => __( 'Title', 'woothemes' ),
				    'type' => 'text',
				    'description' => __( 'This controls the title the user sees during checkout.', 'woothemes' ),
				    'default' => __( 'BlueFire', 'woothemes' )
				),
				
				'description' => array(
				    'title' => __( 'Customer Message', 'woothemes' ),
				    'type' => 'textarea',
				    'description' => __( 'Accepts credit card.', 'woothemes' ),
				    'default' => 'Please proceed to BlueFire to complete this transaction.'
				),
				
				'pay_description' => array(
				    'title' => __( 'Order Description', 'woothemes' ),
				    'type' => 'textarea',
				    'description' => __( 'Description on order page.', 'woothemes' ),
				    'default' => 'Online order.'
				),
				
				'form_key' => array(
				    'title' => __( 'Form Key', 'woothemes' ),
				    'type' => 'text',
				    'description' => __( 'Form RID from BlueFire.', 'woothemes' ),
				    'default' => __( '', 'woothemes' )
				),
				
				'secret_key' => array(
				    'title' => __( 'Secret Key', 'woothemes' ),
				    'type' => 'text',
				    'description' => __( 'API key from BlueFire.', 'woothemes' ),
				    'default' => __( '', 'woothemes' )
				),
				
				'host_url' => array(
				    'title' => __( 'Host URL', 'woothemes' ),
				    'type' => 'text',
				    'description' => __( 'URL to post values to BlueFire. Default is https://bluefire-secure.com/go/cf/checkout.php', 'woothemes' ),
				    'default' => __( 'https://bluefire-secure.com/go/cf/checkout.php', 'woothemes' )
				),
				
				'success_url' => array(
				    'title' => __( 'Success URL', 'woothemes' ),
				    'type' => 'text',
				    'description' => __( 'For example, http://yourdomain.com/thank-you. If a page doesn\'t exist, create one.', 'woothemes' ),
				    'default' => __( '' , 'woothemes' )
				),

				'fund_name' => array(
					'title' => __( 'Default Fund Name', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'All transactions in BlueFire will appear under this fund.', 'woothemes'),
					'default' => __( get_bloginfo('blog_name'), 'woothemes' )
				),

				'fund_number' => array(
					'title' => __( 'Default Fund Number', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Fund number that will be applied to all transactions in BlueFire.', 'woothemes' ),
					'default' => __( '', 'woothemes' )
				),
			);
			
		}
		
		public function admin_options() {
			?>
			<h3><?php _e('BlueFire Payment', 'woothemes'); ?></h3>
			<table class='form-table'>
			<?php
			  // Generate the HTML For the settings form.
			  $this->generate_settings_html();
			?>
			</table>
			<?php
		}

		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
		
		function process_payment( $order_id ) {
			global $woocommerce;
			
			$order = &new WC_Order( $order_id );
			
			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
			
		}

		function receipt_page( $order ) {
		
			echo '<p>'.__($this->pay_description, 'woocommerce').'</p>';
			echo $this->generate_bluefire_form( $order );

		}

		function generate_bluefire_form($order_id) {
			
			global $woocommerce;
			
			$order = new WC_Order( $order_id );
			$amount = $order->get_total();
			
			if (strlen($this->success_url) > 1) {
				$redirect = $this->success_url;
			} else{
				$redirect = get_bloginfo('url');
			}
			
			$formKey = $this->form_key;
			$secKey = $this->secret_key;
			$fundName = $this->fund_name;
			$fundNumber = $this->fund_number;
			
			$getArray = array(
				'rid' => $formKey,
				'orderId' => $order_id,
				'total' => $amount,
				'time' => mktime(),
				/*'clientIp' => $_SERVER['SERVER_ADDR'],*/
				'redirect' => $redirect,
				'descrip' => $fundName,
				'fundNumber' => $fundNumber,
			);
			
			$hostURL = $this->host_url;
			$hashSubj = http_build_query($getArray);
			$sig = hash('sha256', $hashSubj . $secKey,false);
			$getArray['signature'] = $sig;
			
			/* Additional Values */
			
			$getArray['add1'] = $order->shipping_address_1;
			$getArray['add2'] = $order->shipping_address_2;
			$getArray['city'] = $order->billing_city;
			$getArray['country'] = $order->billing_country;
			$getArray['email'] = $order->billing_email;
			$getArray['firstName'] = $order->billing_first_name;
			$getArray['lastName'] = $order->billing_last_name;
			$getArray['phone'] = $order->billing_phone;
			$getArray['state'] = $order->billing_state;
			$getArray['zip'] = $order->billing_postcode;
			
			$payLink = $hostURL . '?' . http_build_query($getArray);
			
			$output.= "
			<div class='bluefire-form '>
			<a href=". htmlentities($payLink) ." class='button alt'>PROCEED TO PAYMENT</a>
			</div>";
			
			return $output;
		}
  	
	}
}

// Add the gateway to woo
function add_bluefire_gateway( $methods ) {
  $methods[] = 'WC_BlueFire';
  return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_bluefire_gateway' );

// Process the response
add_action('init', 'check_bluefire_response');

function check_bluefire_response() {
	
	if (isset($_REQUEST["orderId"]) && isset($_REQUEST["transId"]) && isset($_REQUEST["signature"])) {
		
		$getArray = array(
			'transId' => $_REQUEST["transId"],
			'orderId' => $_REQUEST["orderId"],
			'total' => $_REQUEST["total"]
		);
		
		$bluefire = new WC_BlueFire;
		
		$formKey = $bluefire->form_key;
		$secKey = $bluefire->secret_key;
		
		$hashSubj = http_build_query($getArray);
		$sig = hash('sha256', $hashSubj . $secKey, false);
		
		if ($sig == $_REQUEST['signature']) {
			
			global $woocommerce;
			
			$order = new WC_Order($_REQUEST["orderId"]);
			$amount = $order->get_total();
			
			if ($order->status == 'completed')
				return;
			
			$order->payment_complete();
			$woocommerce->cart->empty_cart();
			update_post_meta($_REQUEST["orderId"], 'trans_id', $_REQUEST["transId"]);
			
		}
		
	}
	
}
