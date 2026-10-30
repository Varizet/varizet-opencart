<?php

namespace Opencart\Admin\Controller\Extension\Varizet\Payment;

/**
 * Varizet settings for OpenCart 4.0.2+ (Extensions → Payments → واریزت).
 */
class Varizet extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        $this->load->language('extension/varizet/payment/varizet');
        $this->document->setTitle($this->language->get('heading_title'));
        $token = 'user_token=' . $this->session->data['user_token'];

        $data = \Varizet\OpenCart\Admin::values([], fn (string $k) => $this->config->get($k));
        $data['breadcrumbs'] = [
            ['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token)],
            ['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=payment')],
            ['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/varizet/payment/varizet', $token)],
        ];
        $data['save'] = $this->url->link('extension/varizet/payment/varizet.save', $token);
        $data['check'] = $this->url->link('extension/varizet/payment/varizet.check', $token);
        $data['connect'] = $this->url->link('extension/varizet/payment/varizet.connect', $token);
        $data['connect_success'] = $this->session->data['varizet_connect_success'] ?? '';
        $data['connect_error'] = $this->session->data['varizet_connect_error'] ?? '';
        unset($this->session->data['varizet_connect_success'], $this->session->data['varizet_connect_error']);
        $data['back'] = $this->url->link('marketplace/extension', $token . '&type=payment');
        $data['webhook_url'] = HTTP_CATALOG . 'index.php?route=extension/varizet/payment/varizet.webhook';
        $data['cron_url'] = HTTP_CATALOG . 'index.php?route=extension/varizet/payment/varizet.cron&token=' . $data['payment_varizet_cron_token'];

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['status_fields'] = ['pending', 'paid', 'review', 'expired', 'canceled', 'refunded'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/varizet/payment/varizet', $data));
    }

    public function save(): void
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        $this->load->language('extension/varizet/payment/varizet');
        $json = [];
        if (!$this->user->hasPermission('modify', 'extension/varizet/payment/varizet')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }
        $errors = \Varizet\OpenCart\Admin::validate($this->request->post, fn (string $k) => $this->language->get($k));
        foreach ($errors as $field => $message) {
            $json['error'][str_replace('_', '-', $field)] = $message;
        }
        if (!$json) {
            $this->load->model('setting/setting');
            $values = \Varizet\OpenCart\Admin::values($this->request->post, fn (string $k) => $this->config->get($k));
            $this->model_setting_setting->editSetting('payment_varizet', $values);
            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function check(): void
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        $this->load->language('extension/varizet/payment/varizet');
        $json = $this->user->hasPermission('modify', 'extension/varizet/payment/varizet')
            ? \Varizet\OpenCart\Admin::check($this->request->post, fn (string $k) => $this->language->get($k))
            : ['error' => $this->language->get('error_permission')];
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** «اتصال یک‌کلیکی»: to the Varizet consent page; Varizet comes back to connected(). */
    public function connect(): void
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        $token = 'user_token=' . $this->session->data['user_token'];
        if (!$this->user->hasPermission('modify', 'extension/varizet/payment/varizet')) {
            $this->load->language('extension/varizet/payment/varizet');
            $this->session->data['varizet_connect_error'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/varizet/payment/varizet', $token));
            return;
        }
        $redirect = html_entity_decode($this->url->link('extension/varizet/payment/varizet.connected', $token), ENT_QUOTES, 'UTF-8');
        $intent = isset($this->request->get['intent']) ? (string) $this->request->get['intent'] : null;
        $this->response->redirect($this->oneClick()->start($redirect, $this->session->data, $intent));
    }

    /** Back from Varizet with `code` + `state` (or `error`): save the key and webhook secret. */
    public function connected(): void
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        $this->load->language('extension/varizet/payment/varizet');
        $token = 'user_token=' . $this->session->data['user_token'];
        if ($this->user->hasPermission('modify', 'extension/varizet/payment/varizet')) {
            $out = $this->oneClick()->finish($this->request->get, $this->session->data);
            if ($out['ok']) {
                $this->load->model('setting/setting');
                $values = \Varizet\OpenCart\Admin::values([], fn (string $k) => $this->config->get($k));
                $this->model_setting_setting->editSetting('payment_varizet', array_merge($values, $out['values']));
                $this->session->data['varizet_connect_success'] = sprintf($this->language->get('text_connect_done'), $out['store'], $out['mode'] === 'live' ? 'Live' : 'Test');
            } else {
                $this->session->data['varizet_connect_error'] = $this->language->get($out['error']);
            }
        } else {
            $this->session->data['varizet_connect_error'] = $this->language->get('error_permission');
        }
        $this->response->redirect($this->url->link('extension/varizet/payment/varizet', $token));
    }

    private function oneClick(): \Varizet\OpenCart\OneClick
    {
        $settings = new \Varizet\OpenCart\Settings(fn (string $k) => $this->config->get($k));
        return new \Varizet\OpenCart\OneClick($settings, HTTP_CATALOG, \Varizet\OpenCart\OneClick::WEBHOOK_PATH_4);
    }

    public function install(): void
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        foreach (\Varizet\OpenCart\Repository::installSql(DB_PREFIX) as $sql) {
            $this->db->query($sql);
        }
        // OpenCart's own scheduler runs hourly at most; the cron URL (every 5 min) is the main fallback.
        $this->load->model('setting/cron');
        if (!$this->model_setting_cron->getCronByCode('varizet')) {
            $this->model_setting_cron->addCron('varizet', 'Varizet: sync open payments', 'hour', 'extension/varizet/cron/varizet', true);
        }
    }

    public function uninstall(): void
    {
        $this->load->model('setting/cron');
        $this->model_setting_cron->deleteCronByCode('varizet');
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('payment_varizet');
    }
}
