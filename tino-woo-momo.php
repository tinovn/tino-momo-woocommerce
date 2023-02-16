<?php
  /*
  Plugin Name:  Momo.vn Payment Gateway for WooCommerce
  Plugin URI: https://tinohost.com/plugins/momo-payment-gateway-for-woocommerce
  Description: Momo.vn Pugin Payment Gateway (Tài khoản doanh nghiệp), Plugin được chia sẻ bởi TinoHost.
  Contributors: Tran Binh, Webico, TinoHost
  Installation:
  Version: 1.0.0
  Author: Tino Team
  Text Domain: tino
  Domain Path: /languages
  Tags: Webico.vn, Tran Binh, TinoHost
  Tested up to: 5.3.2
  Requires PHP: 5.6
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
  Donate link: https://www.webico.vn

   Plugin được chia sẻ bởi TinoHost.
  */

  if (!defined('ABSPATH')) {
      exit; // Exit if accessed directly
  }

 add_filter('woocommerce_payment_gateways', 'tino_momo_add_gateway_class');
 function tino_momo_add_gateway_class($gateways)
 {
     $gateways[] = 'WC_Tino_Momo_Gateway'; // your class name is here
     return $gateways;
 }

 add_action('plugins_loaded', 'tino_momo_init_gateway_class');
 function tino_momo_init_gateway_class()
 {
     class WC_Tino_Momo_Gateway extends WC_Payment_Gateway
     {
         public function __construct()
         {
             $this->id = 'tino_momo'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Momo.vn Gateway';
             $this->method_description = 'Description of Momo.vn payment gateway'; // will be displayed on the options page

             $this->supports = array(
                'subscriptions',
            'products',
            'subscription_cancellation',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_amount_changes',
            'subscription_payment_method_change',
            'subscription_date_changes',
            'default_credit_card_form',
            'refunds',
            'pre-orders'
            );

             $this->init_form_fields();

             $this->init_settings();
             $this->title = $this->get_option('title');
             $this->description = $this->get_option('description');
             $this->enabled = $this->get_option('enabled');
             $this->testmode = 'yes' === $this->get_option('testmode');

             $this->currency_convert = $this->get_option('currency_convert');
             $this->account_id = $this->get_option('account_id');
             $this->access_key = $this->get_option('access_key');
             $this->secret_key = $this->get_option('secret_key');
             add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
             add_action('wp_enqueue_scripts', array( $this, 'payment_scripts' ));
             add_action('woocommerce_api_tino-momo-callback', array( $this, 'webhook_callback' ));
             add_action('woocommerce_api_tino-momo-payment', array( $this, 'webhook_momo' ));
         }

         public function init_form_fields()
         {
             $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Momo.vn Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Credit Card',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),

                'account_id' => array(
                    'title'       => 'PARTNER CODE',
                    'type'        => 'text'
                ),
                'access_key' => array(
                    'title'       => 'ACCESS KEY',
                    'type'        => 'text'
                ),
                'secret_key' => array(
                    'title'       => 'SECRET KEY',
                    'type'        => 'password'
                ),
                'currency_convert' => array(
                    'title'       => 'VND Rate',
                    'type'        => 'number',
              'default'     => 1,
                )
            );
         }

         public function get_icon()
         {
             $icon_html = '<img style="max-height: 35px;" src="'. plugins_url('assets/img/logo-momo.png', __FILE__) .'" alt="' . esc_attr__('Momo acceptance mark', 'woocommerce') . '" />';
             return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
         }

         public function payment_fields()
         {
         }

         public function payment_scripts()
         {
             if (! is_cart() && ! is_checkout() && ! isset($_GET['pay_for_order'])) {
                 return;
             }
             if ('no' === $this->enabled) {
                 return;
             }
             if (empty($this->secret_key) || empty($this->access_key)) {
                 return;
             }
             if (! $this->testmode && ! is_ssl()) {
                 return;
             }
         }

         public function validate_fields()
         {
         }

         public function process_payment($order_id)
         {
             global $woocommerce;
             $order = new WC_Order( $order_id );
             $currency = get_woocommerce_currency();
             $partnerCode = $this->account_id;
             $accessKey = $this->access_key;
             $serectKey = $this->secret_key;

             $momo_Url = $this->testmode ? 'https://test-payment.momo.vn/v2/gateway/api/create' : 'https://payment.momo.vn/v2/gateway/api/create';

             $ipnUrl = get_home_url().'/wc-api/tino-momo-callback';
             $redirectUrl = get_home_url().'/wc-api/tino-momo-payment';

             if ($currency != 'VND') {
                 $totalamount = $order->get_total() * $this->currency_convert;
             } else {
                 $totalamount = $order->get_total();
             }
             if ($totalamount < '1000') {
                 wc_add_notice(__('Amount invalid should be between 1,000đ and 20,000,000đ', 'tino'), 'error');
                 return;
             }
           $endpoint = $this->testmode ? 'https://test-payment.momo.vn/v2/gateway/api/create' : 'https://payment.momo.vn/v2/gateway/api/create';
           $partnerCode = $partnerCode;
           $accessKey = $accessKey;
           $secretKey = $serectKey;
           $orderInfo = 'Thanh toán đơn hàng ' . $order_id . ' qua ví MoMo';
           $amount = round($totalamount);
           $amount = strval($amount);
           $orderId = $this->momo_genInvoiceID($order_id);
           $requestId = time().time();
           $extraData = '';
           $requestType = 'captureWallet';
           $orderGroupId ='';
           $autoCapture = true;
           $lang = 'vi';


           $rawHash =
           "accessKey=" . $accessKey .
           "&amount=" . $amount .
           "&extraData=" . $extraData .
           "&ipnUrl=" . $ipnUrl .
           "&orderId=" . $orderId .
           "&orderInfo=" . $orderInfo .
           "&partnerCode=" . $partnerCode .
           "&redirectUrl=" . $redirectUrl .
           "&requestId=" . $requestId .
           "&requestType=" . $requestType;
           $signature = hash_hmac("sha256", $rawHash, $secretKey);
           $data = array(
             'partnerCode' => $partnerCode,
             'partnerName' => "MyTino",
             'storeId' => $partnerCode,
             'requestId' => $requestId,
             'amount' => $amount,
             'orderId' => $orderId,
             'orderInfo' => $orderInfo,
             'requestType' => $requestType,
             'ipnUrl' => $ipnUrl,
             'lang' => 'vi',
             'redirectUrl' => $redirectUrl,
             'autoCapture' => $autoCapture,
             'extraData' => $extraData,
             'orderGroupId' => $orderGroupId,
             'signature' => $signature
           );
           $result = $this->momo_execPostRequest($endpoint, json_encode($data));
             if (!is_wp_error($result)) {
                 $jsonResult =json_decode($result, true);  // decode json
                 if ($jsonResult['errorCode'] == 0) {
                     return array(
                      'result' => 'success',
                      'redirect' => $jsonResult['payUrl']
                  );
                 } else {
                     $logger = wc_get_logger();
                     $logger->debug($result, $context);
                     $logger->info(json_encode($data), $context);
                     wc_add_notice($jsonResult['message'] . ' Please try again.', 'error');
                     return;
                 }
             } else {
                 wc_add_notice('Connection error.', 'error');
                 return;
             }
         }

         public function process_refund($order_id, $amount = null, $reason = '')
         {
             $order = new WC_Order($order_id);
             $transaction_id = $order->get_transaction_id('view');
             $currency = get_woocommerce_currency();
             $partnerCode = $this->account_id;
             $accessKey = $this->access_key;
             $secretKey = $this->secret_key;
             $endpoint = $this->testmode ? 'https://test-payment.momo.vn/v2/gateway/api/refund' : 'https://payment.momo.vn/v2/gateway/api/refund';

             $extraData = "merchantName=";
             if ($currency != 'VND') {
                 $totalamount = $amount * $this->currency_convert;
             } else {
                 $totalamount = $amount;
             }
             $totalamount = round($totalamount);
             $refundAmount = strval($totalamount);
             $orderId = $this->momo_genInvoiceID($order_id);
             $requestId = time()."";
             $requestType = 'refundMoMoWallet';
             $orderInfo = "Hoàn tiền đơn hàng ". $order_id;


              $transId = $transaction_id;

              $description = 'Hoàn tiền giao dịch ' .$transId  . ' của hoá đơn ' . $orderId;
              $rawHash =
                "accessKey=" . $accessKey .
                "&amount=" . $refundAmount .
                "&description=" . $description .
                "&orderId=" . $orderId .
                "&partnerCode=" . $partnerCode .
                "&requestId=" . $requestId .
                "&transId=" . $transId;

              $signature = hash_hmac("sha256", $rawHash, $secretKey);
               $data = array(
                'partnerCode' => $partnerCode,
                'orderId' => $orderId,
                'requestId' => $requestId,
                'amount' => $refundAmount,
                'transId' => $transId,
                'lang' => 'vi',
                'description' => $description,
                'signature' => $signature
              );
             try {
                 $result = $this->momo_execPostRequest($endpoint, json_encode($data));
                 $jsonResult =json_decode($result,true);  // decode json
                 if (is_wp_error($jsonResult)) {
                     $logger = wc_get_logger();
                     $logger->debug($result, $context);
                     return false;
                 }
                 $result = json_decode($result, true);
                 if ($result['resultCode'] == 0) {

                         $order->add_order_note(
                             sprintf(__('Success: Refunded amount %1$s - Refund ID: %2$s', 'tino'), wc_price($amount), $result['transId']),
                             true
                         );
                         return true;
                 } else {
                     $order->add_order_note(
                         sprintf(__('Error %1$s: Refunded amount %2$s - Refund ID: %3$s', 'tino'), $result['message'], wc_price($result['amount']), $result['transId'])
                     );
                     $logger = wc_get_logger();
                     $logger->debug($result, $context);
                     return false;
                 }
             } catch (Exception $e) {
                 return false;
             }
         }

         public function webhook_momo()
         {
             global $woocommerce;

             if ($_GET['orderId']) {
                  $indata = $_GET;
                   $orderid = $this->momo_getInvoiceID($indata['orderId']);
                   $order = new WC_Order( $orderid );
                   if ($indata['resultCode'] != 0) {
                       $order->add_order_note(esc_html($indata['message']));
                       $url = $order->get_checkout_payment_url();
                       wp_redirect($url);
                   } else {
                       $woocommerce->cart->empty_cart();
                       $order->add_order_note(__('The order process was successful. Waiting for payment confirmation', 'tino'), true);
                       wp_redirect($this->get_return_url($order));
                   }

             }
         }

         public function webhook_callback()
         {
             global $woocommerce;

             $response = json_decode(file_get_contents('php://input'), true);
             $logger = wc_get_logger();
             $logger->debug(json_encode($response));

              $return = [];
              $verified = false;

              try {
                if ($response) {

                  $partnerCode = $response['partnerCode'];
                  $orderId = $response['orderId'];
                  $requestId = $response['requestId'];
                  $amount = $response['amount'];
                  $orderInfo = $response['orderInfo'];
                  $orderType = $response['orderType'];
                  $transId = $response['transId'];
                  $resultCode = $response['resultCode'];
                  $extraData = $response['extraData'];
                  $message = $response['message'];
                  $payType = $response['payType'];
                  $responseTime = $response['responseTime'];

                  $rawHash =
                  "accessKey=" . $this->access_key .
                  "&amount=" . $amount .
                  "&extraData=" . $extraData .
                  "&message=" . $message .
                  "&orderId=" . $orderId .
                  "&orderInfo=" . $orderInfo .
                  "&orderType=" . $orderType .
                  "&partnerCode=" . $partnerCode .
                  "&payType=" . $payType .
                  "&requestId=" . $requestId .
                  "&responseTime=" . $responseTime .
                  "&resultCode=" . $resultCode .
                  "&transId=" . $transId;

                  $signature = hash_hmac("sha256", $rawHash, $this->secret_key);

                 if (strcmp($signature, $response['signature']) == 0) {

                   if ($resultCode == 0) {
                     $verified = true;
                   }

                 }
                }
                if ($verified) {

                    $orderid = $this->momo_getInvoiceID($response['orderId']);
                    $order = new WC_Order( $orderid );
                    $order->payment_complete(esc_html($response['transId']));
                    $order->reduce_order_stock();
                    $order->add_order_note(esc_html($response['message']) . ' - Transaction id: ' .esc_html($response['transId']), true);

                    return true;
                }


              } catch (\Exception $e) {
                echo "bug";
              }

         }

         public function momo_execPostRequest($url, $data)
         {
             $ch = curl_init($url);
             curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
             curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
             curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
             curl_setopt(
                 $ch,
                 CURLOPT_HTTPHEADER,
                 array(
              'Content-Type: application/json',
              'Content-Length: ' . strlen($data))
             );
             curl_setopt($ch, CURLOPT_TIMEOUT, 10);
             curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
             $result = curl_exec($ch);
             curl_close($ch);
             return $result;
         }

         public function momo_genInvoiceID($invoiceId)
         {
             $invoiceId = $invoiceId .'MOMO'. time();
             return $invoiceId;
         }
         public function momo_getInvoiceID($invoiceId)
         {
             $invoiceId = strstr($invoiceId, 'MOMO', true);
             $invoiceId = str_replace('MOMO', '', $invoiceId);
             return $invoiceId;
         }
     }
 }