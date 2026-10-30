<?php

namespace Opencart\Catalog\Model\Extension\Varizet\Payment;

/**
 * Varizet for OpenCart 4: the payment method (4.0.2+ `getMethods`, 4.0.0–4.0.1 `getMethod`) and
 * the wiring of the shared gateway.
 */
class Varizet extends \Opencart\System\Engine\Model
{
    public function getMethods(array $address = []): array
    {
        if (!$this->available($address)) {
            return [];
        }
        $this->load->language('extension/varizet/payment/varizet');
        $title = $this->language->get('text_title');
        return [
            'code' => 'varizet',
            'name' => $title,
            'option' => ['varizet' => ['code' => 'varizet.varizet', 'name' => $title]],
            'sort_order' => $this->config->get('payment_varizet_sort_order'),
        ];
    }

    public function getMethod(array $address = []): array
    {
        if (!$this->available($address)) {
            return [];
        }
        $this->load->language('extension/varizet/payment/varizet');
        return [
            'code' => 'varizet',
            'title' => $this->language->get('text_title'),
            'sort_order' => $this->config->get('payment_varizet_sort_order'),
        ];
    }

    private function available(array $address): bool
    {
        if (!$this->config->get('payment_varizet_status')) {
            return false;
        }
        $zone = (int) $this->config->get('payment_varizet_geo_zone_id');
        if (!$zone) {
            return true;
        }
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $zone . "' AND `country_id` = '" . (int) ($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int) ($address['zone_id'] ?? 0) . "' OR `zone_id` = '0')");
        return (bool) $query->num_rows;
    }

    public function gateway(): \Varizet\OpenCart\Gateway
    {
        require_once DIR_EXTENSION . 'varizet/system/library/varizet/autoload.php';
        $this->load->language('extension/varizet/payment/varizet');
        $this->load->model('checkout/order');
        $settings = new \Varizet\OpenCart\Settings(fn (string $key) => $this->config->get($key), VERSION);
        $shop = new \Varizet\OpenCart\CallbackShop(
            fn (int $orderId) => \Varizet\OpenCart\CallbackShop::normalizeOrder(
                $this->model_checkout_order->getOrder($orderId),
                fn ($total, $code, $value) => $this->currency->format((float) $total, $code, (float) $value, false),
            ),
            function (int $orderId, int $statusId, string $comment, bool $notify): void {
                $this->model_checkout_order->addHistory($orderId, $statusId, $comment, $notify);
            },
            fn (int $orderId) => html_entity_decode($this->url->link('extension/varizet/payment/varizet.callback', 'order_id=' . $orderId), ENT_QUOTES, 'UTF-8'),
            fn (string $key) => $this->language->get($key),
            function (string $message): void {
                $this->log->write('Varizet: ' . $message);
            },
        );
        $records = new \Varizet\OpenCart\Repository(new \Varizet\OpenCart\OpenCartDb($this->db), DB_PREFIX);
        return new \Varizet\OpenCart\Gateway($settings, $records, $shop);
    }
}
