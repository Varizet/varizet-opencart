<?php

/**
 * Varizet for OpenCart 3: checkout button, payment start, return, webhook and cron.
 *   index.php?route=extension/payment/varizet/webhook     signed webhooks (POST)
 *   index.php?route=extension/payment/varizet/callback    return from the payment page
 *   index.php?route=extension/payment/varizet/cron&token= polling fallback
 */
class ControllerExtensionPaymentVarizet extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/varizet');
        $data['text_instruction'] = $this->language->get('text_instruction');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['confirm'] = html_entity_decode($this->url->link('extension/payment/varizet/confirm', '', true), ENT_QUOTES, 'UTF-8');
        return $this->load->view('extension/payment/varizet', $data);
    }

    /** AJAX from the confirm button: `{redirect}` to the payment page or `{error}`. */
    public function confirm()
    {
        $json = array();
        if (!isset($this->session->data['order_id']) || ($this->session->data['payment_method']['code'] ?? '') !== 'varizet') {
            $this->load->language('extension/payment/varizet');
            $json['error'] = $this->language->get('error_order');
        } else {
            $this->load->model('extension/payment/varizet');
            $json = $this->model_extension_payment_varizet->gateway()->start((int) $this->session->data['order_id']);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function callback()
    {
        $this->load->model('extension/payment/varizet');
        $orderId = (int) ($this->request->get['order_id'] ?? 0);
        $status = $orderId > 0 ? $this->model_extension_payment_varizet->gateway()->sync($orderId) : null;
        if ($status === 'expired' || $status === 'canceled') {
            $this->load->language('extension/payment/varizet');
            $this->session->data['error'] = $this->language->get('error_payment_closed');
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }
        // Paid, under review or still waiting: the order is placed; checkout/success clears the cart.
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function webhook()
    {
        $this->load->model('extension/payment/varizet');
        $header = $this->request->server['HTTP_VARIZET_SIGNATURE'] ?? null;
        $result = $this->model_extension_payment_varizet->gateway()->webhook((string) file_get_contents('php://input'), $header);
        http_response_code($result['status']);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result['body']));
    }

    public function cron()
    {
        $token = (string) $this->config->get('payment_varizet_cron_token');
        if ($token === '' || !hash_equals($token, (string) ($this->request->get['token'] ?? ''))) {
            http_response_code(403);
            $this->response->setOutput('forbidden');
            return;
        }
        $this->load->model('extension/payment/varizet');
        $stats = $this->model_extension_payment_varizet->gateway()->poll(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($stats));
    }
}
