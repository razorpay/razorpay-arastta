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

                $this->model_checkout_order->addOrderHistory($merchant_order_id, $this->config->get('razorpay_order_status_id'), 'Payment Successful. Razorpay Payment Id:'.$razorpay_payment_id);

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

    public function processWebhook()
    {
        $post = file_get_contents('php://input');

        $data = json_decode($post, true);

        if (json_last_error() !== 0)
        {
            return;
        }

        $enabled = $this->config->get('razorpay_enable_webhook');;

        if (($enabled === '1') and
            (empty($data['event']) === false))
        {
            if (isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) === true)
            {
                $razorpayWebhookSecret = $this->config->get('razorpay_webhook_secret');;

                //
                // If the webhook secret isn't set on arastta dashboard, return
                //
                if (empty($razorpayWebhookSecret) === true)
                {
                    return;
                }

                try
                {
                    $api = $this->getRazorpayApiInstance();

                    $api->utility->verifyWebhookSignature($post,
                                                                $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'],
                                                                $razorpayWebhookSecret);
                }
                catch (Errors\SignatureVerificationError $e)
                {
                    $this->load->model('checkout/order');

                    $this->model_checkout_order->addOrderHistory($data['payload']['order']['entity']['notes']['arastta_order_id'], 10, $e->getMessage().' Payment Failed! Check Razorpay dashboard for details of Payment Id:'.$data['payload']['payment']['entity']['id']);

                    return;
                }

                switch ($data['event'])
                {
                    case self::ORDER_PAID:
                        return $this->orderPaid($data);

                    default:
                        return;
                }
            }
        }
    }

    /**
     * Order Paid webhook
     *
     * @param array $data
     */
    protected function orderPaid(array $data)
    {
        //
        // Order entity should be sent as part of the webhook payload
        //
        $orderId = $data['payload']['order']['entity']['notes']['arastta_order_id'];

        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($orderId);

        // If it is already marked as paid or failed, ignore the event
        if ($order['order_status'] === 'Processing' or $order['order_status'] === 'Failed')
        {
            return;
        }

        $success = false;
        $error = "";
        $errorMessage = 'The payment has failed.';

        $razorpayPaymentId = $data['payload']['payment']['entity']['id'];

        $amount = $this->getOrderAmountAsInteger($order);

        if($data['payload']['payment']['entity']['amount'] === $amount)
        {
            $success = true;

            $this->model_checkout_order->addOrderHistory($orderId, $this->config->get('razorpay_order_status_id'), 'Payment Successful. Razorpay Payment Id:'.$razorpayPaymentId);
        }
        else
        {
            $error = 'ARASTTA_ERROR: Payment to Razorpay Failed. Amount mismatch.';

            $this->model_checkout_order->addOrderHistory($orderId, 10, $error.' Payment Failed! Check Razorpay dashboard for details of Payment Id:'.$razorpayPaymentId);
        }

        // Graceful exit since payment is now processed.
        exit;
    }


    /**
     * Returns the order amount, rounded as integer
     * @param Arastta_Order $order Arastta Order instance
     * @return int Order Amount
     */
    public function getOrderAmountAsInteger($order)
    {
        return (int) round($order['total'] * 100);
    }

    /**
     * Create the session key name
     * @param  int $order_id
     * @return
     */
    protected function getOrderSessionKey($order_id)
    {
        return self::RAZORPAY_ORDER_ID . $order_id;
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
     * @param  int    $order_id
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
     * @param  int $order_id
     * @param  array $response
     * @return
     */
    protected function verifySignature(int $order_id, $razorpay_payment_id, $razorpay_signature)
    {
        $api = $this->getRazorpayApiInstance();

        $attributes = array(
            self::RAZORPAY_PAYMENT_ID => $razorpay_payment_id,
            self::RAZORPAY_SIGNATURE  => $razorpay_signature,
        );

        $sessionKey = $this->getOrderSessionKey($order_id);
        $attributes[self::RAZORPAY_ORDER_ID] = $this->session->data[$sessionKey];

        $api->utility->verifyPaymentSignature($attributes);
    }

}
