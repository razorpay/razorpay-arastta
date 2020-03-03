<?php

require_once __DIR__.'/razorpay-sdk/Razorpay.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors;

class ControllerPaymentRazorpay extends Controller
{
    const RAZORPAY_ARASTTA_VERSION = '2.0.0';
    const RAZORPAY_PAYMENT_ID      = 'razorpay_payment_id';
    const ARASTTA_ORDER_ID         = 'arastta_order_id';
    const RAZORPAY_ORDER_ID        = 'razorpay_order_id';
    const RAZORPAY_SIGNATURE       = 'razorpay_signature';

    const CAPTURE    = 'capture';
    const AUTHORIZE  = 'authorize';

    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['key_id'] = $this->config->get('razorpay_key_id');
        $data['currency_code'] = $order_info['currency_code'];
        $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) * 100;
        $data['merchant_order_id'] = $this->session->data['order_id'];

        $razorpay_order_id = $this->createRazorpayOrderId($data);

        $data['razorpay_order_id'] = $razorpay_order_id;
        $data['card_holder_name'] = $order_info['payment_firstname'].' '.$order_info['payment_lastname'];
        $data['email'] = $order_info['email'];
        $data['phone'] = $order_info['telephone'];
        $data['name'] = $this->config->get('config_name');
        $data['lang'] = $this->session->data['language'];
        $data['version'] = self::RAZORPAY_ARASTTA_VERSION;
        $data['return_url'] = $this->url->link('payment/razorpay/callback', '', 'SSL');

        if (file_exists(DIR_TEMPLATE.$this->config->get('config_template').'/template/payment/razorpay.tpl')) {
            return $this->load->view($this->config->get('config_template').'/template/payment/razorpay.tpl', $data);
        } else {
            return $this->load->view('default/template/payment/razorpay.tpl', $data);
        }
    }

    public function callback()
    {
        $this->load->model('checkout/order');
        if (isset($this->request->request['razorpay_payment_id']) and isset($this->request->request['merchant_order_id'])) {
            $razorpay_payment_id = $this->request->request[self::RAZORPAY_PAYMENT_ID];
            $razorpay_signature  = $this->request->request[self::RAZORPAY_SIGNATURE];
            $merchant_order_id   = $this->request->request['merchant_order_id'];

            $order_info = $this->model_checkout_order->getOrder($merchant_order_id);

            $success = false;
            $error = 'Payment failed. Please try again.';

            try
            {
                $this->verifySignature($merchant_order_id, $razorpay_payment_id, $razorpay_signature);
                $success = true;
            }
            catch (Errors\SignatureVerificationError $e)
            {
                $error = 'ARASTTA_ERROR: Payment to Razorpay Failed. ' . $e->getMessage();
            }

            if ($success === true) {
                if (!$order_info['order_status_id']) {
                    $this->model_checkout_order->addOrderHistory($merchant_order_id, $this->config->get('razorpay_order_status_id'), 'Payment Successful. Razorpay Payment Id:'.$razorpay_payment_id);
                } else {
                    $this->model_checkout_order->addOrderHistory($merchant_order_id, $this->config->get('razorpay_order_status_id'), 'Payment Successful. Razorpay Payment Id:'.$razorpay_payment_id);
                }

                echo '<html>'."\n";
                echo '<head>'."\n";
                echo '  <meta http-equiv="Refresh" content="0; url='.$this->url->link('checkout/success').'">'."\n";
                echo '</head>'."\n";
                echo '<body>'."\n";
                echo '  <p>Please follow <a href="'.$this->url->link('checkout/success').'">link</a>!</p>'."\n";
                echo '</body>'."\n";
                echo '</html>'."\n";
                exit();
            } else {
                $this->model_checkout_order->addOrderHistory($this->request->request['merchant_order_id'], 10, $error.' Payment Failed! Check Razorpay dashboard for details of Payment Id:'.$razorpay_payment_id);
                echo '<html>'."\n";
                echo '<head>'."\n";
                echo '  <meta http-equiv="Refresh" content="0; url='.$this->url->link('checkout/failure').'">'."\n";
                echo '</head>'."\n";
                echo '<body>'."\n";
                echo '  <p>Please follow <a href="'.$this->url->link('checkout/failure').'">link</a>!</p>'."\n";
                echo '</body>'."\n";
                echo '</html>'."\n";
                exit();
            }
        } else {
            echo 'An error occured. Contact site administrator, please!';
        }
    }

    /**
     * Create the session key name
     * @param  int $order_no
     * @return
     */
    protected function getOrderSessionKey($order_no)
    {
        return self::RAZORPAY_ORDER_ID . $order_no;
    }

    /**
    * @codeCoverageIgnore
    */
    protected function getRazorpayApiInstance()
    {
        $key    = $this->config->get('razorpay_key_id');
        $secret = $this->config->get('razorpay_key_secret');

        return new Api($key, $secret);
    }

    /**
     * Create razorpay order id
     * @param  int    $order_no
     * @param  array  $payment
     * @return string
     */
    protected function createRazorpayOrderId(array $payment)
    {
        $api = $this->getRazorpayApiInstance();

        $data = array(
            'receipt'         => $payment['merchant_order_id'],
            'amount'          => (int) round($payment['total']),
            'currency'        => $payment['currency_code'],
            'payment_capture' => ($this->config->get('razorpay_payment_action') === self::AUTHORIZE) ? 0 : 1,
            'notes'           => array(
                self::ARASTTA_ORDER_ID  => (string) $payment['merchant_order_id'],
            ),
        );

        try
        {
            $razorpayOrder = $api->order->create($data);
        }
        catch (Exception $e)
        {
            return $e;
        }

        $razorpayOrderId = $razorpayOrder['id'];

        $sessionKey = $this->getOrderSessionKey($payment['merchant_order_id']);

        $this->session->data[$sessionKey] = $razorpayOrderId;

        return $razorpayOrderId;
    }

    /**
     * Verify the signature on payment success
     * @param  int $order_no
     * @param  array $response
     * @return
     */
    protected function verifySignature(int $order_no, $razorpay_payment_id, $razorpay_signature)
    {
        $api = $this->getRazorpayApiInstance();

        $attributes = array(
            self::RAZORPAY_PAYMENT_ID => $razorpay_payment_id,
            self::RAZORPAY_SIGNATURE  => $razorpay_signature,
        );

        $sessionKey = $this->getOrderSessionKey($order_no);
        $attributes[self::RAZORPAY_ORDER_ID] = $this->session->data[$sessionKey];

        $api->utility->verifyPaymentSignature($attributes);
    }

}
