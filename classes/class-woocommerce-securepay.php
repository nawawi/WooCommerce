<?php

class WC_Gateway_securepay extends WC_Payment_Gateway
{
    public $id = 'securepay';
    private $live_url = "https://securepay.my/api/v1/payments";
    private $sandbox_url = "https://sandbox.securepay.my/api/v1/payments";
    private $log;

    public function __construct()
    {
        global $woocommerce;

        $this->has_fields = false;
        $this->icon       = apply_filters('woocommerce_securepay_icon', SECUREPAY_URL . 'assets/images/logo-securepay.svg?ver=1.1');
        if(is_admin()) {
            $this->has_fields = true;
            $this->init_form_fields();
        }
        
        // Define user set variables
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->method_title       = 'SecurePay';
        $this->method_description = 'Allow your customers to pay with SecurePay Platform.';        
                    
        // Actions
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }

        add_action('woocommerce_wc_gateway_securepay_process_response', array(&$this, 'process_response'));
        add_action('woocommerce_wc_gateway_securepay_responseOnline', array(&$this, 'responseOnline'));
        add_action( 'woocommerce_order_button_text', array(&$this, 'custom_order_button_text'), 10, 1 );
    }

    function custom_order_button_text( $order_button_text ) {
        $default = __( 'Place order', 'woocommerce' ); // If needed
        // Get the chosen payment gateway (dynamically)
        $chosen_payment_method = WC()->session->get('chosen_payment_method');

        if( $chosen_payment_method == $this->id)
            $order_button_text = $this->get_option("place_order_text"); 
        
        // jQuery code: Make dynamic text button "on change" event ?>
        <script type="text/javascript">
        (function($){
            $('form.checkout').on( 'change', 'input[name^="payment_method"]', function() {
                var t = { updateTimer: !1,  dirtyInput: !1,
                    reset_update_checkout_timer: function() {
                        clearTimeout(t.updateTimer)
                    },  trigger_update_checkout: function() {
                        t.reset_update_checkout_timer(), t.dirtyInput = !1,
                        $(document.body).trigger("update_checkout")
                    }
                };
                t.trigger_update_checkout();
            });
        })(jQuery);
        </script><?php

        return $order_button_text;
    }

    function payment_scripts()
    {
        global $woocommerce;
        if (!is_checkout()) {
            return;
        }
        
        wp_enqueue_script('SecurePay-js-checkout', SECUREPAY_URL . 'assets/js/checkout.js', array(), 10014, true);
    }

    function process_admin_options() {
        $result = parent::process_admin_options();
        $post_data = $this->get_post_data();
        $settings = $this->settings;
        
        return $result;
    }

    /**
     * Check if this gateway is enabled and available in the user's currency
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use()
    {
        // Skip currency check
        return true;
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'api keys' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        ?>
        <h2>SecurePay 
        <?php wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>
        </h2>
        <p><?php _e('Please fill in the below section to start accepting payments via SecurePay Platform.', 'SecurePay'); ?></p>

        <?php if ($this->is_valid_for_use()) : ?>

            <table class="form-table">
            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
               
            </table><!--/.form-table-->
        <?php else : ?>
            <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'SecurePay'); ?></strong>: <?php _e('SecurePay does not support your store currency at this time.', 'SecurePay'); ?></p></div>
        <?php
        endif;
    }


    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'             => array(
                'title'   => __('Enable/Disable', 'SecurePay'),
                'type'    => 'checkbox',
                'label'   => __('Enable SecurePay payment platform', 'SecurePay'),
                'default' => 'yes'
            ),
            'title'         => array(
                'title'       => __('Title', 'SecurePay'),
                'type'        => 'text',
                'description' => __('This is the title the user sees during checkout.', 'SecurePay'),
                'default'     => __('SecurePay', 'SecurePay')
            ),
            'description'         => array(
                'title'       => __('Description', 'SecurePay'),
                'type'        => 'text',
                'description' => __('This is the description the user sees during checkout.', 'SecurePay'),
                'default'     => __('Pay for your items securely with SecurePay', 'SecurePay')
            ),
          
            'live_token' => array(
                'title'       => __('Live Token', 'SecurePay'),
                'type'        => 'text',
                'description' => __('Your Live Token.', 'SecurePay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
          
            'live_checksum' => array(
                'title'       => __('Live Checksum Token', 'SecurePay'),
                'type'        => 'text',
                'description' => __('Your Live Checksum.', 'SecurePay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
          
            'live_uid' => array(
                'title'       => __('Live UID', 'SecurePay'),
                'type'        => 'text',
                'description' => __('Your Live UID.', 'SecurePay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
           
            'sandbox_mode'        => array(
                'title'   => __('Sandbox mode', 'SecurePay'),
                'type'    => 'checkbox',
                'label'   => __('Enable Sandbox mode', 'SecurePay'),
                'default' => 'no'
            ),
           
            'sandbox_token' => array(
                'title'       => __('Sandbox Token Token', 'SecurePay'),
                'type'        => 'text',
                'description' => __('Your Sandbox Token.', 'SecurePay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
          
            'sandbox_checksum' => array(
                'title'       => __('Sandbox Checksum', 'SecurePay'),
                'type'        => 'text',
                'description' => __('Your Sandbox Checksum.', 'SecurePay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
          
            'sandbox_uid' => array(
                'title'       => __('Sandbox UID', 'SecurePay'),
                'type'        => 'text',
                'description' => __('Your Sandbox UID.', 'SecurePay'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => ''
            ),
            
            'debug_mode'     => array(
                'title'       => 'Debug Mode',
                'type'        => 'select',
                'options'     => array(
                    '1' => 'Enabled',
                    '0' => 'Disabled',
                ),
                'default'     => '0',
                'desc_tip'    => true,
                'description' => sprintf('Logs additional information. <br>Log file path: %s', 'Your admin panel -> WooCommerce -> System Status -> Logs'),
                'placeholder' => '',
                'class'       => 'wc-enhanced-select',
            ),

            'place_order_text'         => array(
                'title'       => __('Place order text', 'SecurePay'),
                'type'        => 'text',
                'description' => __('This is the text for Place Order button.', 'SecurePay'),
                'default'     => __('Pay with SecurePay', 'SecurePay')
            )
        );
    }

    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
    function process_payment($order_id)
    {
        global $woocommerce;
        $order   = new WC_Order($order_id);

        update_post_meta($order->id, '_payment_method_title', 'SecurePay');
        update_post_meta($order->id, '_payment_method', 'SecurePay');
        $orderId = $order->get_id();
        
        $url = $this->get_option("sandbox_mode") == "yes" ? $this->sandbox_url : $this->live_url;
                  
        $checksum = $this->get_option("sandbox_mode") == "yes" ? $this->get_option("sandbox_checksum") : $this->get_option("live_checksum") ;
 
        $token = $this->get_option("sandbox_mode") == "yes" ? $this->get_option("sandbox_token") : $this->get_option("live_token") ;
        $uid = $this->get_option("sandbox_mode") == "yes" ? $this->get_option("sandbox_uid") : $this->get_option("live_uid") ;
        $errors = '';
        

        if(empty($token))
            $errors .= "<li>Token parameter is empty</li>";

        if(empty($uid))
            $errors .= "<li>UID parameter is empty</li>";

        if(empty($checksum))
            $errors .= "<li>Checksum parameter is empty</li>";
        


         if(strlen($errors) > 0){
            $newError = str_replace("</li>", "", $errors);
            $newError = str_replace("<li>", "\n", $newError);
            $this->log("Error in settings".$newError);
            return wp_send_json(array('result' => 'failure', 'messages' => $errors));
        }


        $product_description = "Payment for order no ".$orderId;
        $buyer_name = $order->data['billing']['first_name'] ." ". $order->data['billing']['last_name'];
        $buyer_email = $order->data['billing']['email'];
        $buyer_phone = $order->data['billing']['phone'];
      
        $total = wc_format_decimal($order->get_total(), 2);
        $redirect_url = get_site_url() . '/?wc-api=wc_gateway_securepay_process_response';

        $calculateSign = "$buyer_email|$buyer_name|$buyer_phone|$redirect_url|$orderId|$product_description|$redirect_url|$total|$uid";

        $sign = hash_hmac('sha256', $calculateSign, $checksum);

        $form = '<form style="display:none" name="frm_securepay_payment" id="frm_securepay_payment" method="post" action="' . $url . '">';
        $form .= "<input type='hidden' name='order_number' value='".$orderId."'>";
        $form .= "<input type='hidden' name='buyer_name' value='".$buyer_name."'>";
        $form .= "<input type='hidden' name='buyer_email' value='".$buyer_email."'>";
        $form .= "<input type='hidden' name='buyer_phone' value='".$buyer_phone."'>";
        $form .= "<input type='hidden' name='transaction_amount' value='".$total."'>";
        $form .= "<input type='hidden' name='product_description' value='".$product_description."'>";
        $form .= "<input type='hidden' name='callback_url' value='".$redirect_url."'>";
        $form .= "<input type='hidden' name='redirect_url' value='".$redirect_url."'>";
        $form .= "<input type='hidden' name='checksum' value='".$sign."'>";
        $form .= "<input type='hidden' name='token' value='".$token."'>";
        $form .= '<input type="submit">';

        $this->log("Payment Initiated for order ID ".$orderId);

        $result = array('result' => 'success', 'form' => $form);
        if (isset($_POST['woocommerce_pay']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-pay')) {
                wp_send_json($result);
                exit;
        }
         else {
             return $result;
        }
    }

    public function process_response()
    {
        $this->_handleResponse('offline');
    }

    public function responseOnline()
    {
        $this->_handleResponse('online');
    }

    private function _handleResponse($response_mode = 'online')
    {
        global $woocommerce;
        $response_params = array_merge($_GET, $_POST); //never use $_REQUEST, it might include PUT .. etc
        
    
        if (isset($response_params['order_number'])) {
            $success = $this->handleResponse($response_params);
            $order = new WC_Order($response_params['order_number']);

            if ($success) {
                $this->log("Payment Successfully for order ID ".$response_params['order_number']." \n with return data \n".print_r($response_params, true));

                $order->payment_complete();
                $success = "SecurePay payment successful<br>";
                $success .= 'Payment ID: '.$response_params['merchant_reference_number']."<br>";
                $success .= 'Receipt link: '.$response_params['receipt_url']."<br>";
                $success .= 'Status link: '.$response_params['status_url']."<br>";

                $order->add_order_note($success);
                
                WC()->session->set('refresh_totals', true);
                $redirectUrl = $this->get_return_url($order);
            }
            else {
                $this->log("Payment Failed for order ID ".$response_params['order_number']." \n with return data \n".print_r($response_params, true));

                $redirectUrl = esc_url($woocommerce->cart->get_checkout_url());
                wc_add_notice( __( 'SecurePay Payment failed.', 'woocommerce' ), 'error' );
                $error = "SecurePay payment failed<br>";
                $error .= 'Payment ID: '.$response_params['merchant_reference_number']."<br>";
                $error .= 'Retry link: '.$response_params['retry_url']."<br>";
                $error .= 'Status link: '.$response_params['status_url']."<br>";

                $order->add_order_note($error);
            }
            echo '<script>window.top.location.href = "' . $redirectUrl . '"</script>';
            exit;
        }
    }

    public function handleResponse ($response_params) {
        $url = $this->get_option("sandbox_mode") == "yes" ? $this->sandbox_url : $this->live_url;
                  
        $checksum = $this->get_option("sandbox_mode") == "yes" ? $this->get_option("sandbox_checksum") : $this->get_option("live_checksum") ;
 
        $token = $this->get_option("sandbox_mode") == "yes" ? $this->get_option("sandbox_token") : $this->get_option("live_token") ;
        $uid = $this->get_option("sandbox_mode") == "yes" ? $this->get_option("sandbox_uid") : $this->get_option("live_uid") ;

        $calculateSign = $response_params['buyer_email']."|".$response_params['buyer_name']."|".$response_params['buyer_phone']."|".$response_params['client_ip']."|".$response_params['created_at']."|".$response_params['currency']."|".$response_params['exchange_number']."|".$response_params['merchant_reference_number']."|".$response_params['order_number']."|".$response_params['payment_id']."|".$response_params['payment_status']."|".$response_params['receipt_url']."|".$response_params['retry_url']."|".$response_params['source']."|".$response_params['status_url']."|".$response_params['transaction_amount']."|".$response_params['transaction_amount_received']."|".$uid;
        $sign = hash_hmac('sha256', $calculateSign, $checksum);
        return /*$sign == $response_params['checksum'] && */$response_params['payment_status'] == "true";
    }

    public function getReturnUrl($path)
    {
        return get_site_url().'?wc-api=wc_gateway_securepay_'.$path;
    }

    /**
     *
     * @access public
     * @param none
     * @return string
     */
    function payment_fields()
    {
        
        if ($this->description) {
            echo "<p>" . $this->description . "</p>";
        }
        
        ?>
        <?php
    }

    /**
     * Log the error on the disk
     */
    public function log($messages, $forceDebug = false)
    {
        $debugMode = $this->get_option("debug_mode");
        if (!$debugMode && !$forceDebug) {
            return;
        }
        if ( ! class_exists( 'WC_Logger' ) ) {
                include_once( 'class-wc-logger.php' );
        }
        if ( empty( $this->log ) ) {
                $this->log = new WC_Logger();
        }
        $messages .= "\n-----------------------------------------------------------\n";
        $this->log->add( 'SecurePay', $messages );
    }

}
