<?php

declare(strict_types=1);

namespace Varizet\OpenCart;

/** `Shop` from closures, so the OpenCart 3 and 4 catalog models stay a few lines each. */
final class CallbackShop implements Shop
{
    public function __construct(
        private readonly \Closure $order,
        private readonly \Closure $addHistory,
        private readonly \Closure $returnUrl,
        private readonly \Closure $text,
        private readonly \Closure $log,
    ) {
    }

    public function order(int $orderId): ?array
    {
        return ($this->order)($orderId);
    }

    public function addHistory(int $orderId, int $statusId, string $comment, bool $notify): void
    {
        ($this->addHistory)($orderId, $statusId, $comment, $notify);
    }

    public function returnUrl(int $orderId): string
    {
        return ($this->returnUrl)($orderId);
    }

    public function text(string $key): string
    {
        return ($this->text)($key);
    }

    public function log(string $message): void
    {
        ($this->log)($message);
    }

    /**
     * OpenCart's `getOrder()` row → the shape `Shop::order()` promises (total in the order currency).
     *
     * @param array<string, mixed>|false|null $row
     * @param callable(float|string, string, float|string): (float|string) $format `$this->currency->format(..., false)`
     * @return array{order_id: int, order_status_id: int, total: string, currency_code: string, firstname: string, lastname: string, telephone: string, email: string, store_url: string}|null
     */
    public static function normalizeOrder(mixed $row, callable $format): ?array
    {
        if (!is_array($row) || empty($row['order_id'])) {
            return null;
        }
        $total = $format($row['total'] ?? 0, (string) ($row['currency_code'] ?? ''), $row['currency_value'] ?? 1);
        return [
            'order_id' => (int) $row['order_id'],
            'order_status_id' => (int) ($row['order_status_id'] ?? 0),
            'total' => is_float($total) ? rtrim(rtrim(number_format($total, 4, '.', ''), '0'), '.') : (string) $total,
            'currency_code' => (string) ($row['currency_code'] ?? ''),
            'firstname' => (string) ($row['firstname'] ?? ''),
            'lastname' => (string) ($row['lastname'] ?? ''),
            'telephone' => (string) ($row['telephone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'store_url' => (string) ($row['store_url'] ?? ''),
        ];
    }
}
