<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/**
 * Plugin Name: WooCommerce Swish Gateway
 * Plugin URI: http://redlight.se/swish
 * Description: Extends WooCommerce. Provides a <a href="http://www.getswish.se" target="_blank">Swish</a> gateway for WooCommerce.
 * Version: 3.0.1
 * Author: Redlight Media
 * Author URI: http://redlight.se/
 * Developer: Christopher Hedqvist
 * Developer URI: http://redlight.se/
 * Text Domain: redlight-swish
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5.3
 *
 * Copyright: © 2018 Redlight.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
add_action('plugins_loaded', 'init_woocommerce_swish_gateway', 0);
function init_woocommerce_swish_gateway() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	define( 'REDLIGHT_SM_STORE_URL', 'https://redlight.se' );
	define( 'REDLIGHT_SM_ITEM_NAME', 'Swish (Manuell)' );
	if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
		// load our custom updater
		include( dirname( __FILE__ ) . '/includes/EDD_SL_Plugin_Updater.php' );
    }
    if( !class_exists( 'Swish_QR' ) ) {
		include( dirname( __FILE__ ) . '/includes/class-wc-gateway-swish-qr.php' );
	}
	function redlight_swish_plugin_updater() {
		// retrieve our license key from the DB
		$license = get_option( 'woocommerce_redlight_swish_settings');
		$license_key = $license['redlight_license_key'];
		// setup the updater
		$edd_updater = new EDD_SL_Plugin_Updater( REDLIGHT_SM_STORE_URL, __FILE__, array(
				'version' 	=> '3.0.1', 				// current version number
				'license' 	=> $license_key, 		// license key (used get_option above to retrieve from DB)
				'item_name' => REDLIGHT_SM_ITEM_NAME, 	// name of this plugin
				'author' 	=> 'Redlight Media'  // author of this plugin
			)
		);
	}
	add_action( 'admin_init', 'redlight_swish_plugin_updater', 0 );
	function redlight_swish_activate_license() {

		// listen for our activate button to be clicked
		if( isset( $_POST['woocommerce_redlight_swish_redlight_license_key'] ) ) {
			// retrieve the license from the database
			$license = get_option( 'woocommerce_redlight_swish_settings');
			$license = trim($license['redlight_license_key']);


			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'activate_license',
				'license' 	=> $license,
				'item_name' => urlencode( REDLIGHT_SM_ITEM_NAME ), // the name of our product in EDD
				'url'       => home_url()
			);

			// Call the custom API.
			$response = wp_remote_post( REDLIGHT_SM_STORE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "valid" or "invalid"

			update_option( 'redlight_license_status', $license_data->license );

		}
	}
	add_action('admin_init', 'redlight_swish_activate_license');	
	function redlight_swish_deactivate_license() {
		// listen for our activate button to be clicked
		if( isset( $_POST['woocommerce_redlight_swish_redlight_license_deactivate'] ) ) {

			// retrieve the license from the database
			$license = get_option( 'woocommerce_redlight_swish_settings');
			$license = trim($license['redlight_license_key']);


			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'deactivate_license',
				'license' 	=> $license,
				'item_name' => urlencode( REDLIGHT_SM_ITEM_NAME ), // the name of our product in EDD
				'url'       => home_url()
			);

			// Call the custom API.
			$response = wp_remote_post( REDLIGHT_SM_STORE_URL, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "deactivated" or "failed"
			if( $license_data->license == 'deactivated' )
				delete_option( 'redlight_license_status' );

		}
	}
	add_action('admin_init', 'redlight_swish_deactivate_license');
	// Localisation
	load_plugin_textdomain('redlight-swish', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
	
    add_filter('woocommerce_payment_gateways', 'add_woocommerce_swish_gateway' );
	
    /*
        Initiate the WooCommerce_Swish class.
        This used to be in a separate file, but it caused a headers already sent warning.
    */

    class WooCommerce_Swish extends WC_Payment_Gateway {

        /**
         * Construct. Setup the woocommerce gateway settings and initate needed hooks.
         *
         * @access public
         * @return void
         */

        function __construct() {

            $this->id = "redlight_swish";

            // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
            $this->method_title = "Swish";

            // The description for this Payment Gateway, shown on the actual Payment options page on the backend
            $this->method_description = __( "Extends WooCommerce. Provides a <a href='http://www.getswish.se' target='_blank'>Swish</a> gateway for WooCommerce.", 'redlight-swish' );

            $this->title = __( "Swish", 'redlight-swish' );

            // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
            $this->icon = plugins_url( 'assets/images/swish_logo.png', __FILE__ );
            $this->swishimglogo = plugins_url( 'assets/images/Swish-logo-image-vert.png', __FILE__ );
            $this->swishtextlogo = plugins_url( 'assets/images/Swish-logo-text-vert.png', __FILE__ );



            // Bool. Can be set to true if you want payment fields to show on the checkout
            // if doing a direct integration, which we are doing in this case
            $this->has_fields = true;

            // This basically defines your settings which are then loaded with init_settings()
            $this->init_form_fields();

            // After init_settings() is called, you can get the settings and load them into variables, e.g:
            $this->init_settings();
            $this->supports = array(
            'products'
             );

            // Turn these settings into variables we can use
            foreach ( $this->settings as $setting_key => $value ) {
                $this->$setting_key = $value;
            }



            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'thankyou_page' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
			//Filters
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

        }
		
        public function register_plugin_styles() {
            wp_register_style( 'swish-css', plugins_url( 'woocommerce-gateway-swish/assets/css/swish.css' ) );
            wp_enqueue_style( 'swish-css' );
        }
        /**
         * Build the administration fields for the gateway.
         *
         * @access public
         * @return void
         */

        public function init_form_fields() {
            $this->form_fields = array(
				'redlight_license_key' => array( 
					'title'     => __( 'License key', 'redlight-swish' ),
					'type'      => 'text',
					'description'   => __( 'To enable updates for this plugin, enter the license key you recived when you downloeded this plugin', 'redlight-swish' ),
				),
				'redlight_license_deactivate' => array( 
					'label'     => __( 'Deactivate License key', 'redlight-swish' ),
					'type'      => 'checkbox',
					'default'   => 'no',
					'description'   => __( 'Deactivating this license key will disable updates', 'redlight-swish' ),
				),
                'enabled' => array(
                    'title'     => __( 'Enable / Disable', 'redlight-swish' ),
                    'label'     => __( 'Enable this payment gateway', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'no',
                    ),
                'title' => array(
                    'title'     => __( 'Title', 'redlight-swish' ),
                    'type'      => 'text',
                    'desc_tip'  => __( 'Enter the name you want displayed for the user.', 'redlight-swish' ),
                    'default'   => __( 'Swish', 'redlight-swish' ),
                    ),
                'description' => array(
                    'title'     => __( 'Description', 'redlight-swish' ),
                    'type'      => 'textarea',
                    'desc_tip'  => __( 'Pay your purchase directly through your smartphone with Swish payment app that is available for both iPhone and Android.
                        Note that you must have your mobile phone and log-in information ready to complete the purchase . <br> Payment will be registred directly and the money will be reserved from your account. No extra fee is charged .',
                        'redlight-swish' ),
                    'default'   => __( 'Swish works between  Danske Bank,  Handelsbanken,  ICA Banken,  Länsförsäkringar,  Nordea,  SEB,  Skandia,  Sparbanken Syd,  Sparbanken Öresund samt Swedbank och Sparbankerna.', 'redlight-swish' ),
                    'desc_tip'    => true,
                    ),
                'message' => array(
                    'title'       => __( 'Message', 'redlight-swish' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will appear on the "Thank you for your order " and the e-mail message.', 'redlight-swish' ),
                    'default'     => __( 'Since payments made ​​via Swish must be manually reviewed and matched against your order as it can take up to 24 hours before it is done. You get an email when your order is processed.', 'redlight-swish' ),
                    'desc_tip'    => true,
                    ),
                'swish_number' => array(
                    'title'     => __( 'Your Swish number', 'redlight-swish' ),
                    'type'      => 'text',
                    'description' => __( 'Enter the number you received when you joined Swish .', 'redlight-swish' ),
                    ),
                'show_desc' => array(
                    'title'     => __( 'Show / Hide description', 'redlight-swish' ),
                    'label'     => __( 'Show description', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'no',
                    ),
                'swish_number_desc' => array(
                    'title'       => __( 'Description of your Swish account', 'redlight-swish' ),
                    'type'        => 'textarea',
                    'description' => __( 'Example: Company Inc', 'redlight-swish' ),
                    'default'     => '',
                    'desc_tip'    => true,
                    ),
                'swish_number_two' => array(
                    'title'     => __( 'Your second Swish number', 'redlight-swish' ),
                    'type'      => 'text',
                    'description' => __( 'If you have more than one Swish number , enter the second here. If not, leave blank', 'redlight-swish' ),
                    ),
                'show_desc_two' => array(
                    'title'     => __( 'Show / Hide description', 'redlight-swish' ),
                    'label'     => __( 'Show description', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'no',
                    ),
                'swish_number_desc_two' => array(
                    'title'       => __( 'Description of your second Swish account', 'redlight-swish' ),
                    'type'        => 'textarea',
                    'description' => __( 'Example: Company Two Inc', 'redlight-swish' ),
                    'default'     => '',
                    'desc_tip'    => true,
                    ),
                'show_qr_code' => array(
                    'title'     => __( 'Activate / Deactivate QR-code', 'redlight-swish' ),
                    'label'     => __( 'Show Swish QR-code at thank-you page', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'yes',
                    'description'   => __( 'This requires you to have a valid Swish agreement for Swish-företag', 'redlight-swish' ),
                    ),
                'show_payment_details_in_email' => array(
                    'title'     => __( 'Show payment details in email', 'redlight-swish' ),
                    'label'     => __( 'Show payment details in email', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'yes',
                    'description'   => '',
                    ),
                'qr_code_editnr' => array(
                    'title'     => __( 'Allow customer to make changes to the Swish-nr', 'redlight-swish' ),
                    'label'     => __( '', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'no',
                    'description'   => __( 'This will allow the customer to edit the number after qr-code is scanned', 'redlight-swish' ),
                    ),
                'qr_code_editamount' => array(
                    'title'     => __( 'Allow customer to make changes to the amount', 'redlight-swish' ),
                    'label'     => __( '', 'redlight-swish' ),
                    'type'      => 'checkbox',
                    'default'   => 'no',
                    'description'   => __( 'This will allow the customer to edit the amount after qr-code is scanned', 'redlight-swish' ),
                    ),
            );
        }
		/**
		 * Sanitize our settings
		 */
		function sanitize_settings( $new ) {
			$old = $this->redlight_license_key;
			if( $old && $old != $new['redlight_license_key'] ) {
				delete_option( 'redlight_license_status' ); // new license has been entered, so must reactivate
			}
			return $new;
		}

        /**
         * Output the "order received"-page.
         *
         * @access public
         * @param int $order_id
         * @return void
         */

        public function thankyou_page( $order_id ) {
            $this->swish_details( $order_id );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         * @return void
         */

        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
          if ( ! $sent_to_admin && 'redlight_swish' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
            $this->swish_email_details( $order->id );
          }

        }

        /**
         * Get bank details and place into a list format.
         *
         * @access private
         * @param string $order_id (default: '')
         * @return void
         */

        private function swish_details( $order_id = '' ) {

            if ( empty( $this->swish_number ) ) {
                return;
            }

            $order = new WC_Order($order_id);
            //Load Swish CSS
            wp_enqueue_style('swish');
            if($this->show_qr_code == "yes"){
                if($this->qr_code_editnr == "yes"){
                    $editnumber = true;
                }else{
                    $editnumber = false;
                }
                if($this->qr_code_editamount == "yes"){
                    $editamount = true;
                }else{
                    $editamount = false;
                }
                $data =
                [
                    'format' => 'svg',
                    'payee'  => [
                        'value' => $this->swish_number,
                        'editable'=> $editnumber
                    ],
                    'amount' => [
                            'value' => (int)$order->get_total(),
                            'editable'=> $editamount
                    ],
                    'message' => [
                        "value" => sprintf( __( "Payment for order %s", 'redlight-swish' ), $order->get_order_number() )
                    ],
                    'size' => 600,
                    'border' => 1,
                    'transparent' => true
                ];
                $data_string = json_encode($data);
                $swishQR = Swish_QR::generateQR($data_string);
            }

            ?>

            <div class="redlight-swish-logo redlight-swish-centered">
                <img class="redlight-swish-centered" src="<?php echo $this->swishimglogo;?>" />
                <img class="redlight-swish-centered" src="<?php echo $this->swishtextlogo;?>" />
            </div>
            <div class="redlight-swish-messages redlight-swish-centered"><?php
            echo '<h2>' . __( 'To pay with Swish', 'redlight-swish' ) . '</h2>' . PHP_EOL;
            echo '<p>'. __( 'Please pay your order by swishing', 'redlight-swish' ) . ' <strong>'.$order->get_total().' '.$order->get_currency().'</strong> '. __( 'to', 'redlight-swish' ) . ' ';
            if($this->show_desc == 'yes'){echo $this->swish_number_desc . ', <strong>';}else{echo __( 'number', 'redlight-swish' ) .' <strong>';}
            echo $this->swish_number .'</strong>.';
            if(isset($this->swish_number_two) && $this->swish_number_two !== ''){
                echo '<br>'.__( 'Alternative you can send your payment to', 'redlight-swish' ) . ' ';
                if($this->show_desc_two == 'yes'){
                    echo $this->swish_number_desc_two . ', <strong>';
                }
                else{echo __( 'number', 'redlight-swish' ) .' <strong>';}
                echo $this->swish_number_two .'</strong>.';
            }
            echo '<br>'. __( 'Enter', 'redlight-swish' ) .' <strong>'. $order->get_order_number() . '</strong> '. __( 'as message in your Swish-app', 'redlight-swish' ) .'</p>';
            if($this->show_qr_code == "yes"){
                echo sprintf( '<div class="redlight-swish-qr-code redlight-swish-centered">%s</div>', $swishQR );
            }
            echo wpautop( wptexturize( $this->message ) ).'</div>';
            echo '<ul class="order_details swish_details">' . PHP_EOL;

            echo '</ul>';

            
        }

        /**
          * Get bank details and place into a list format
          */

        private function swish_email_details( $order_id = '' ) {
            if ( empty( $this->swish_number ) ) {
                return;
            }
            if ( $this->show_payment_details_in_email == 'no' ) {
                return;
            }

            $swish_number = apply_filters( 'woocommerce_swish_number', $this->swish_number );

            if ( ! empty( $swish_number ) ) {
                $swish_number = (object) $swish_number;
                $order = new WC_Order($order_id);

                echo '<h2>' . __( 'To pay with Swish', 'redlight-swish' ) . '</h2>' . PHP_EOL;
                ?>
                <?php
                echo '<p>'. __( 'Please pay your order by swishing', 'redlight-swish' ) . ' <strong>'.$order->get_total().' '.$order->get_currency().'</strong> '. __( 'to', 'redlight-swish' ) . ' ';
                if($this->show_desc == 'yes'){echo $this->swish_number_desc . ', <strong>';}else{echo __( 'number', 'redlight-swish' ) .' <strong>';}
                echo $this->swish_number .'</strong>. ';
                if(isset($this->swish_number_two) && $this->swish_number_two !== ''){
                    echo '<br>'.__( 'Alternative you can send your payment to', 'redlight-swish' ) . ' ';
                    if($this->show_desc_two == 'yes'){
                        echo $this->swish_number_desc_two . ', <strong>';
                    }
                    else{echo __( 'number', 'redlight-swish' ) .' <strong>';}
                    echo $this->swish_number_two .'</strong>. ';
                }
                echo '<br>'. __( 'Enter', 'redlight-swish' ) .' <strong>'. $order->get_order_number() . '</strong> '. __( 'as message in your Swish-app', 'redlight-swish' ) .'</p>'.
                wpautop( wptexturize( $this->message ) );
                echo '<ul class="order_details swish_details">' . PHP_EOL;

                // Swish account fields shown on the thanks page and in emails
                $account_fields = apply_filters( 'woocommerce_swish_account_fields', $this->swish_number, $order_id );
                echo '</ul>';

            }
        }
		

        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */

        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status( 'on-hold', __( 'Awaiting swish payment', 'redlight-swish' ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Empty cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
                );
        }

    }
}

/**
 * Add the gateway to WooCommerce.
 *
 * @access public
 * @param array $methods
 * @return array
 */

function add_woocommerce_swish_gateway( $methods ) {
    $methods[] = 'WooCommerce_Swish';
    return $methods;
}
