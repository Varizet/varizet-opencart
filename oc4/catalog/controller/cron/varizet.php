<?php

namespace Opencart\Catalog\Controller\Extension\Varizet\Cron;

/** OpenCart 4 scheduler task (hourly): the same polling as the cron URL. */
class Varizet extends \Opencart\System\Engine\Controller
{
    public function index(mixed ...$args): void
    {
        if (!$this->config->get('payment_varizet_status')) {
            return;
        }
        $this->load->model('extension/varizet/payment/varizet');
        $this->model_extension_varizet_payment_varizet->gateway()->poll(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }
}
