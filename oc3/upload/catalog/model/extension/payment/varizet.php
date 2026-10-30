<?php

/**
 * Varizet for OpenCart 3: the payment method and the wiring of the shared gateway.
 */
class ModelExtensionPaymentVarizet extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/varizet');
        $zone = (int) $this->config->get('payment_varizet_geo_zone_id');
        if ($zone) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $zone . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");
            if (!$query->num_rows) {
                return array();
            }
        }
        if (!$this->config->get('payment_varizet_status')) {
            return array();
        }
        return array(
            'code' => 'varizet',
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => $this->config->get('payment_varizet_sort_order'),
        );
    }

    /** @return \Varizet\OpenCart\Gateway */
    public function gateway()
    {
        require_once DIR_SYSTEM . 'library/varizet/autoload.php';
        $this->load->language('extension/payment/varizet');
        $this->load->model('checkout/order');
        $config = $this->config;
        $settings = new \Varizet\OpenCart\Settings(function ($key) use ($config) {
            return $config->get($key);
        }, defined('VERSION') ? VERSION : '');
        $shop = new \Varizet\OpenCart\CallbackShop(
            function ($orderId) {
                return \Varizet\OpenCart\CallbackShop::normalizeOrder($this->model_checkout_order->getOrder($orderId), function ($total, $code, $value) {
                    return $this->currency->format($total, $code, $value, false);
                });
            },
            function ($orderId, $statusId, $comment, $notify) {
                $this->model_checkout_order->addOrderHistory($orderId, $statusId, $comment, $notify);
            },
            function ($orderId) {
                return html_entity_decode($this->url->link('extension/payment/varizet/callback', 'order_id=' . (int) $orderId, true), ENT_QUOTES, 'UTF-8');
            },
            function ($key) {
                return $this->language->get($key);
            },
            function ($message) {
                $this->log->write('Varizet: ' . $message);
            }
        );
        $records = new \Varizet\OpenCart\Repository(new \Varizet\OpenCart\OpenCartDb($this->db), DB_PREFIX);
        return new \Varizet\OpenCart\Gateway($settings, $records, $shop);
    }
}
