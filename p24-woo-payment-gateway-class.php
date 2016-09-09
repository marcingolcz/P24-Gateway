<?php
include_once('includes/Przelewy24.php');
class WC_Gateway_Przelewy24 extends WC_Payment_Gateway{

    /**
     * Set up required variables
     */
    public function __construct()
    {
        //Unique ID for your gateway, e.g., ‘your_gateway’
        $this->id = "p24";
        //If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
        $this->icon = null;
        //Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
        $this->has_fields = true;
        //Title of the payment method shown on the admin page.
        $this->method_title = __('Przelewy24.pl', 'p24_gateway');
        //Description for the payment method shown on the admin page.
        $this->method_description = __('Bramka płatności Przelewy24.pl', 'p24_gateway');

        $this->notify_link = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Przelewy24', home_url( '/' ) ) );
        // This basically defines settings which are then loaded with init_settings()
        $this->init_form_fields();
        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
        // Turn these settings into variables we can use
        foreach($this->settings as $setting_key => $value){
            $this->$setting_key = $value;
        }
        //Save admin settings
        if(is_admin()){
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_przelewy24', array($this, 'gateway_report'));
    }

    /**
    * Initialise Gateway Settings Form Fields
    */
    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Włącz/Wyłącz', 'p24_gateway'),
                'label' => __('Włącz metodę płatności Przelewy24.pl', 'p24_gateway'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => sprintf( __( ' <a href="%s" TARGET="_blank">Załóż konto w systemie Przelewy24.pl</a>.', 'p24_gateway' ), 'https://secure.przelewy24.pl/panel/rejestracja.php' ),
                ),
            'title' => array(
                'title' => __('Tytuł', 'p24_gateway'),
                'desc_tip'  => __( 'Tytuł płatności, który będzie widoczny na stronie podsumowania.', 'spyr-authorizenet-aim' ),
                'type' => 'text',
                'default' => __('Przelewy24','p24_gateway')
                ),
            'description' => array(
                'title'     => __( 'Opis', 'p24_gateway' ),
                'type'      => 'textarea',
                'desc_tip'  => __( 'Opis płatności, który będzie widoczny na stronie podsumowania.', 'p24_gateway' ),
                'default'   => __( 'Zapłać przez Przelewy24.pl.', 'p24_gateway' ),
                'css'       => 'max-width:350px;'
                ),
            'client_id' => array(
                'title' => __('ID Sprzedawcy', 'p24_gateway'),
                'type' => 'text',
                'desc_tip'  => __( 'ID Sprzedawcy' ),
                ),
            'CRC_key' => array(
                'title' => __('Klucz CRC', 'p24_gateway'),
                'desc_tip' => __('Losowy ciąg znaków służący do generowania sumy kontrolnej przesyłanych parametrów,
                                do pobrania z panelu Przelewy24.', 'p24_gateway'),
                'type' => 'text',
                ),
            'environment' => array(
                'title'     => __( 'Tryb testowy', 'p24_gateway' ),
                'label'     => __( 'Włącz tryb testowy', 'p24_gateway' ),
                'type'      => 'checkbox',
                'description' => __( 'Uruchom tryb testowy dla płatności Przelewy24.pl', 'p24_gateway' ),
                'default'   => 'no',
                )
            );
    }

    /**
     * Submit payment and handle response
     * @param  int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);

        // Mark as on-hold (we will be awaiting the Przelewy24 payment)
        $order->update_status('on-hold', __('Oczekuje na płatność Przelewy24.pl', 'woocommerce'));
        // Reduce stock levels
        $order->reduce_order_stock();
        // Clear cart
        $woocommerce->cart->empty_cart();
        // Post data and redirect to Przelewy24.pl
        return array(
            'result' => 'success',
            'redirect' => add_query_arg(array('order_id' => $order_id), $this->notify_link)
            );
    }

    /**
     * Verify if payment is coplete
     * @return void
     */
    function gateway_report() {
        if (($_SERVER['REMOTE_ADDR'] == '91.216.191.181' || $_SERVER['REMOTE_ADDR'] == '91.216.191.182' || $_SERVER['REMOTE_ADDR'] == '91.216.191.183' || $_SERVER['REMOTE_ADDR'] == '91.216.191.184' || $_SERVER['REMOTE_ADDR'] == '91.216.191.185') && (!empty($_POST))) {
            $this->verify_payment_response();
        } else if (isset($_GET['order_id'])) {
            $this->send_payment_data($_GET['order_id']);
        }
        exit;
    }

    /**
     * Handles sending data to the server via post method
     * @param int $order_id
     */
    function send_payment_data($order_id) {
        global $wp;
        // get order data
        $order = new WC_Order($order_id);

        // Are we testing right now or is it a real transaction
        $environment = ( $this->environment == "yes" ) ? true : false;

        $merchantId = $this->client_id;

        $P24 = new Przelewy24($merchantId, $merchantId, $this->CRC_key, $environment);
        $amount = $amount = number_format($order->get_total()*100, 0, "", "");
        $currency = $order->get_order_currency();
        // populate data array to be posted
        $crc = md5($order_id."|".$merchantId."|".$amount."|".$currency."|".$this->CRC_key);
        $P24->addValue("p24_merchant_id", $this->client_id);
        $P24->addValue("p24_pos_id",$this->client_id);
        $P24->addValue("p24_session_id",$order_id);
        $P24->addValue("p24_amount", $amount);
        $P24->addValue("p24_currency", $currency);
        $P24->addValue("p24_description", "Opłata za zamówienie nr ".$order_id);
        $P24->addValue("p24_email", $order->billing_email);
        $P24->addValue("p24_country", $order->billing_country);
        $P24->addValue("p24_encoding", "UTF-8");
        $P24->addValue("p24_url_status", $this->notify_link);
        $P24->addValue("p24_url_return", esc_url($this->get_return_url($order)));
        $P24->addValue("p24_api_version", '3.2');
        $P24->addValue("p24_return_url_error", esc_url($order->get_cancel_order_url()));
        $P24->addValue("p24_sign", $crc);
        $RET = $P24->trnRegister();
        $RES = $P24->trnRequest($RET['token'], true);
        die();
    }

    /**
     * Verify transaction
     */
    public function verify_payment_response()
    {
        $environment = ( $this->environment == "yes" ) ? true : false;
        $body = file_get_contents('php://input');
        $P24 = new Przelewy24($_POST["p24_merchant_id"],$_POST["p24_pos_id"],$this->CRC_key,$environment);
        foreach($_POST as $k=>$v) $P24->addValue($k,$v);

        $P24->addValue('p24_currency',$_POST['p24_currency']);
        $P24->addValue('p24_amount',$_POST['p24_amount']);
        $res = $P24->trnVerify();
        // file_put_contents(public_path().'/przelewy.txt', $res);
        // There are no errors complete payment
        if($res["error"] === '0')
        {

            $order = new WC_Order($_POST['p24_session_id']);
            $order->update_status('completed', __('Zapłacono.', 'p24_gateway'));
        }
        else{
            $order->update_status('failed', __('Nie można zweryfikować płatnośći.','p24_gateway'));
        }
        return $res['error'];
    }

    /**
     * Validate frontend fields
     * @return boolean
     */
    function validate_fields() {
        if (get_woocommerce_currency() == "PLN" || get_woocommerce_currency() == "EUR" || get_woocommerce_currency() == "GBP" || get_woocommerce_currency() == "CZK")
            return true;
        else {
            return false;
        }
    }

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available() {
        $is_available = ( 'yes' === $this->enabled );

        if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
            $is_available = false;
        }
        $is_available = $this->validate_fields();

        return $is_available;
    }

}
