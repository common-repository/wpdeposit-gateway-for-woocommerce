<?php
/*
Plugin Name: WPdeposit gateway for Woocommerce
Plugin URI: http://plugins.svn.wordpress.org/wpdeposit-gateway-for-woocommerce/
Description: This plugin adds a new payment gateway to WooCommerce. The plugin allows users to select WPdeposit as their payment method. It will deduct the order total from their deposited credits.
Version: 0.1
Author: uWebic
Author URI: http://uwebic.com
License: GPL2
*/
add_action('plugins_loaded', 'woocommerce_wpdeposit_init', 0);

function woocommerce_wpdeposit_init() {

class WC_WPDEPOSIT extends WC_Payment_Gateway {

    public function __construct() { 
		$this->id			= 'WPdeposit';
		$this->icon 			= apply_filters('woocommerce_bacs_icon', '');
		$this->has_fields 		= false;
		$this->method_title     = __( 'WPDeposit', 'woocommerce' );
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title 			= $this->settings['title'];
		$this->currency_match	= $this->settings['currency_match'];
		
		// Actions
		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
    	add_action('woocommerce_thankyou_ppay', array(&$this, 'thankyou_page'));
    	
    } 

	/**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
    	$WCCurrentCurrency = get_woocommerce_currency();
    	$currencyMessage = (WPD_CURRENCY_CODE != $WCCurrentCurrency)? '<span style="color:red;">The WPdeposit and Woocommerce current currency do not match. Please update to match currencies.</span>' : '<span style="color:green;">WPdeposit and Woocommerce selected currencies matches.</span>' ;
    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ), 
							'type' => 'checkbox', 
							'label' => __( 'Enable WPdeposit', 'woocommerce' ), 
							'default' => 'yes'
						), 
			'title' => array(
							'title' => __( 'Title', 'woocommerce' ), 
							'type' => 'text', 
							'description' => __( 'Definisci il titolo del sistema di pagamento.', 'woocommerce' ), 
							'default' => __( 'WPdeposit', 'woocommerce' )
						),
			'currency_match' => array(
							'title' => __( 'Currency match', 'woocommerce' ), 
							'type' => 'title', 
							'description' => $currencyMessage
						),
			);
    
    } // End init_form_fields()
    
	/**
	 * Admin Panel Options 
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
    	?>
    	<h3><?php _e('WPdeposit', 'woocommerce'); ?></h3>
    	<p><?php _e('Allows your user to deduct points/money they have deposited on your account using the WPDeposit plugin', 'woocommerce'); ?></p>
    	<table class="form-table">
    	<?php
    		// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    	?>
		</table><!--/.form-table-->
    	<?php
    } // End admin_options()


    /**
    * There are no payment fields for bacs, but we want to show the description if set.
    **/
    function payment_fields() {
      if ($this->description) echo wpautop(wptexturize($this->description));
    }

    function thankyou_page() {
		if ($this->description) echo wpautop(wptexturize($this->description));
		
		?><h2><?php _e('Our Details', 'woocommerce') ?></h2><ul class="order_details ppay_details"><?php
		
		$fields = array(
			'ppay_number'=> __('Numero WPdeposit', 'woocommerce')
		);
		
		foreach ($fields as $key=>$value) :
		    if(!empty($this->$key)) :
		    	echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
		    endif;
		endforeach;
		
		?></ul><?php
    }

    /**
    * Process the payment and return the result
    **/
    function process_payment( $order_id ) {
    	global $woocommerce;
    	$userModel = $userModel = new UserModel(UserModel::getCurrentUser()->ID);
		$order = new WC_Order( $order_id );
		if ($userModel->getBalance() <  $order->order_total) {
			$woocommerce->add_error(__('Payment error:', 'woothemes') . ' You do not have enough balance to checkout your cart. Deposit more money on your account.');
			return false;
		}
		// Mark as on-hold (we're awaiting the payment)
		//$order->update_status('on-hold', __('In attesa di versamento WPdeposit', 'woocommerce'));
		
		if (!$userModel->decrementBalance($order->order_total)) {
			$woocommerce->add_error(__('Payment error:', 'woothemes') . ' Something went wrong when deducting the total price order from your deposits. Contact the site administrator if this persist.');
			return false;
		}
		
		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		$woocommerce->cart->empty_cart();
		
		//if all is ok we can update the status
		$order->update_status('completed');

		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('thanks'))))
		);
    }

}

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_wpdeposit_gateway($methods) {
		$methods[] = 'WC_WPDEPOSIT';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_wpdeposit_gateway' );
}
