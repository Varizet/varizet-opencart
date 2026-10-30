<?php

/**
 * Varizet settings for OpenCart 3.0.x (Extensions → Payments → واریزت).
 */
class ControllerExtensionPaymentVarizet extends Controller
{
    private $error = array();

    public function index()
    {
        require_once DIR_SYSTEM . 'library/varizet/autoload.php';
        $this->load->language('extension/payment/varizet');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $token = 'user_token=' . $this->session->data['user_token'];

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $values = \Varizet\OpenCart\Admin::values($this->request->post, function ($k) {
                return $this->config->get($k);
            });
            $this->model_setting_setting->editSetting('payment_varizet', $values);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', $token . '&type=payment', true));
        }

        $data = \Varizet\OpenCart\Admin::values($this->request->post, function ($k) {
            return $this->config->get($k);
        });
        $data['error'] = $this->error;
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=payment', true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/payment/varizet', $token, true)),
        );
        $data['action'] = $this->url->link('extension/payment/varizet', $token, true);
        $data['connect'] = $this->url->link('extension/payment/varizet/connect', $token, true);
        $data['connect_success'] = isset($this->session->data['varizet_connect_success']) ? $this->session->data['varizet_connect_success'] : '';
        $data['connect_error'] = isset($this->session->data['varizet_connect_error']) ? $this->session->data['varizet_connect_error'] : '';
        unset($this->session->data['varizet_connect_success'], $this->session->data['varizet_connect_error']);
        $data['check'] = html_entity_decode($this->url->link('extension/payment/varizet/check', $token, true), ENT_QUOTES, 'UTF-8');
        $data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=payment', true);
        $catalog = !empty($this->request->server['HTTPS']) ? HTTPS_CATALOG : HTTP_CATALOG;
        $data['webhook_url'] = $catalog . 'index.php?route=extension/payment/varizet/webhook';
        $data['cron_url'] = $catalog . 'index.php?route=extension/payment/varizet/cron&token=' . $data['payment_varizet_cron_token'];

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['status_fields'] = array('pending', 'paid', 'review', 'expired', 'canceled', 'refunded');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/varizet', $data));
    }

    /** AJAX «بررسی اتصال» with the values currently in the form. */
    public function check()
    {
        require_once DIR_SYSTEM . 'library/varizet/autoload.php';
        $this->load->language('extension/payment/varizet');
        $json = $this->user->hasPermission('modify', 'extension/payment/varizet')
            ? \Varizet\OpenCart\Admin::check($this->request->post, function ($k) {
                return $this->language->get($k);
            })
            : array('error' => $this->language->get('error_permission'));
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** «اتصال یک‌کلیکی»: to the Varizet consent page; Varizet comes back to connected(). */
    public function connect()
    {
        require_once DIR_SYSTEM . 'library/varizet/autoload.php';
        $token = 'user_token=' . $this->session->data['user_token'];
        if (!$this->user->hasPermission('modify', 'extension/payment/varizet')) {
            $this->load->language('extension/payment/varizet');
            $this->session->data['varizet_connect_error'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/payment/varizet', $token, true));
            return;
        }
        $redirect = html_entity_decode($this->url->link('extension/payment/varizet/connected', $token, true), ENT_QUOTES, 'UTF-8');
        $intent = isset($this->request->get['intent']) ? (string) $this->request->get['intent'] : null;
        $this->response->redirect($this->oneClick()->start($redirect, $this->session->data, $intent));
    }

    /** Back from Varizet with `code` + `state` (or `error`): save the key and webhook secret. */
    public function connected()
    {
        require_once DIR_SYSTEM . 'library/varizet/autoload.php';
        $this->load->language('extension/payment/varizet');
        $token = 'user_token=' . $this->session->data['user_token'];
        if ($this->user->hasPermission('modify', 'extension/payment/varizet')) {
            $out = $this->oneClick()->finish($this->request->get, $this->session->data);
            if ($out['ok']) {
                $this->load->model('setting/setting');
                $values = \Varizet\OpenCart\Admin::values(array(), function ($k) {
                    return $this->config->get($k);
                });
                $this->model_setting_setting->editSetting('payment_varizet', array_merge($values, $out['values']));
                $this->session->data['varizet_connect_success'] = sprintf($this->language->get('text_connect_done'), $out['store'], $out['mode'] === 'live' ? 'Live' : 'Test');
            } else {
                $this->session->data['varizet_connect_error'] = $this->language->get($out['error']);
            }
        } else {
            $this->session->data['varizet_connect_error'] = $this->language->get('error_permission');
        }
        $this->response->redirect($this->url->link('extension/payment/varizet', $token, true));
    }

    private function oneClick()
    {
        $settings = new \Varizet\OpenCart\Settings(function ($k) {
            return $this->config->get($k);
        });
        $catalog = !empty($this->request->server['HTTPS']) ? HTTPS_CATALOG : HTTP_CATALOG;
        return new \Varizet\OpenCart\OneClick($settings, $catalog, \Varizet\OpenCart\OneClick::WEBHOOK_PATH_3);
    }

    public function install()
    {
        require_once DIR_SYSTEM . 'library/varizet/autoload.php';
        foreach (\Varizet\OpenCart\Repository::installSql(DB_PREFIX) as $sql) {
            $this->db->query($sql);
        }
    }

    public function uninstall()
    {
        // The mapping table stays: orders keep their Varizet reference if the module comes back.
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('payment_varizet');
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/varizet')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        $this->error += \Varizet\OpenCart\Admin::validate($this->request->post, function ($k) {
            return $this->language->get($k);
        });
        return !$this->error;
    }
}
