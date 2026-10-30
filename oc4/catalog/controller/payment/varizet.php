<?php

namespace Opencart\Catalog\Controller\Extension\Varizet\Payment;

/**
 * Varizet for OpenCart 4: checkout button, payment start, return, webhook and cron.
 *   index.php?route=extension/varizet/payment/varizet.webhook     signed webhooks (POST)
 *   index.php?route=extension/varizet/payment/varizet.callback    return from the payment page
 *   index.php?route=extension/varizet/payment/varizet.cron&token= polling fallback
 */
class Varizet extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/varizet/payment/varizet');
        $data['text_instruction'] = $this->language->get('text_instruction');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['language'] = $this->config->get('config_language');
        $data['confirm'] = html_entity_decode($this->url->link('extension/varizet/payment/varizet.confirm', 'language=' . $this->config->get('config_language')), ENT_QUOTES, 'UTF-8');
        return $this->load->view('extension/varizet/payment/varizet', $data);
    }

    public function confirm(): void
    {
        $code = $this->session->data['payment_method']['code'] ?? $this->session->data['payment_method'] ?? '';
        $code = is_array($code) ? '' : (string) $code;
        if (!isset($this->session->data['order_id']) || !str_starts_with($code, 'varizet')) {
            $this->load->language('extension/varizet/payment/varizet');
            $json = ['error' => $this->language->get('error_order')];
        } else {
            $this->load->model('extension/varizet/payment/varizet');
            $json = $this->model_extension_varizet_payment_varizet->gateway()->start((int) $this->session->data['order_id']);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function callback(): void
    {
        $this->load->model('extension/varizet/payment/varizet');
        $orderId = (int) ($this->request->get['order_id'] ?? 0);
        $status = $orderId > 0 ? $this->model_extension_varizet_payment_varizet->gateway()->sync($orderId) : null;
        $language = 'language=' . $this->config->get('config_language');
        if ($status === 'expired' || $status === 'canceled') {
            $this->load->language('extension/varizet/payment/varizet');
            $this->session->data['error'] = $this->language->get('error_payment_closed');
            $this->response->redirect($this->url->link('checkout/cart', $language, true));
            return;
        }
        $this->response->redirect($this->url->link('checkout/success', $language, true));
    }

    public function webhook(): void
    {
        $this->load->model('extension/varizet/payment/varizet');
        $header = $this->request->server['HTTP_VARIZET_SIGNATURE'] ?? null;
        $result = $this->model_extension_varizet_payment_varizet->gateway()->webhook((string) file_get_contents('php://input'), $header);
        http_response_code($result['status']);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result['body']));
    }

    public function cron(): void
    {
        $token = (string) $this->config->get('payment_varizet_cron_token');
        if ($token === '' || !hash_equals($token, (string) ($this->request->get['token'] ?? ''))) {
            http_response_code(403);
            $this->response->setOutput('forbidden');
            return;
        }
        $this->load->model('extension/varizet/payment/varizet');
        $stats = $this->model_extension_varizet_payment_varizet->gateway()->poll(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($stats));
    }
}
