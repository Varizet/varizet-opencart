<?php

declare(strict_types=1);

namespace Varizet\OpenCart;

/** What differs between OpenCart 3 and 4 (implemented by each version's catalog model). */
interface Shop
{
    /**
     * @return array{order_id: int, order_status_id: int, total: string, currency_code: string, firstname: string, lastname: string, telephone: string, email: string, store_url: string}|null
     *         `total` is already in the order's currency.
     */
    public function order(int $orderId): ?array;

    /** `addOrderHistory` (3.x) / `addHistory` (4.x): status change, stock and e-mails. */
    public function addHistory(int $orderId, int $statusId, string $comment, bool $notify): void;

    public function returnUrl(int $orderId): string;

    /** Persian text for a language key of the module's catalog language file. */
    public function text(string $key): string;

    public function log(string $message): void;
}
