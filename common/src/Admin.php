<?php

declare(strict_types=1);

namespace Varizet\OpenCart;

use Varizet\Exception\ExceptionInterface;
use Varizet\Exception\InvalidArgumentException;

/** Settings-page logic shared by the OpenCart 3 and 4 admin controllers. */
final class Admin
{
    /** Setting keys without the `payment_varizet_` prefix. */
    public const FIELDS = [
        'status', 'mode', 'api_key_live', 'api_key_test', 'webhook_secret_live', 'webhook_secret_test',
        'currency_unit', 'expires_in', 'api_base_url', 'debug', 'geo_zone_id', 'sort_order', 'cron_token',
        'order_status_pending', 'order_status_paid', 'order_status_review', 'order_status_expired',
        'order_status_canceled', 'order_status_refunded',
    ];

    /** OpenCart's default order status ids (Pending, Processing, Expired, Canceled, Refunded). */
    public const DEFAULTS = [
        'mode' => 'test',
        'currency_unit' => 'auto',
        'expires_in' => '1800',
        'order_status_pending' => '1',
        'order_status_paid' => '2',
        'order_status_review' => '1',
        'order_status_expired' => '14',
        'order_status_canceled' => '7',
        'order_status_refunded' => '11',
    ];

    private function __construct()
    {
    }

    /**
     * Form values: posted, else saved, else defaults (keys with the prefix).
     *
     * @param array<string, mixed>   $post
     * @param callable(string): mixed $saved
     * @return array<string, mixed>
     */
    public static function values(array $post, callable $saved): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            $key = Settings::PREFIX . $field;
            $out[$key] = $post[$key] ?? $saved($key) ?? (self::DEFAULTS[$field] ?? '');
        }
        if (($out[Settings::PREFIX . 'cron_token'] ?? '') === '') {
            $out[Settings::PREFIX . 'cron_token'] = bin2hex(random_bytes(16));
        }
        return $out;
    }

    /**
     * @param array<string, mixed>     $post
     * @param callable(string): string $text
     * @return array<string, string> field → message
     */
    public static function validate(array $post, callable $text): array
    {
        $errors = [];
        foreach (['api_key_live' => 'vz_live_', 'api_key_test' => 'vz_test_'] as $field => $prefix) {
            $key = trim((string) ($post[Settings::PREFIX . $field] ?? ''));
            if ($key !== '' && preg_match('/^' . $prefix . '[A-Za-z0-9_-]{8,200}$/D', $key) !== 1) {
                $errors[$field] = $text('error_api_key');
            }
        }
        $mode = ($post[Settings::PREFIX . 'mode'] ?? 'test') === 'live' ? 'live' : 'test';
        if (!empty($post[Settings::PREFIX . 'status']) && trim((string) ($post[Settings::PREFIX . 'api_key_' . $mode] ?? '')) === '') {
            $errors['api_key_' . $mode] = $text('error_api_key_required');
        }
        return $errors;
    }

    /**
     * «بررسی اتصال»: `GET /v1/me` with the posted settings.
     *
     * @param array<string, mixed>     $post
     * @param callable(string): string $text
     * @return array{success: string}|array{error: string}
     */
    public static function check(array $post, callable $text): array
    {
        $settings = new Settings(static fn (string $k): mixed => $post[$k] ?? null);
        try {
            $client = $settings->client();
            if ($client === null) {
                return ['error' => $text('error_api_key_required')];
            }
            $me = $client->me();
        } catch (InvalidArgumentException) {
            return ['error' => $text('error_api_key')];
        } catch (ExceptionInterface $e) {
            return ['error' => sprintf($text('error_check'), $e->getMessage())];
        }
        if ($me->livemode !== $settings->livemode()) {
            return ['error' => sprintf($text('error_check_mode'), $settings->livemode() ? 'Live' : 'Test')];
        }
        return ['success' => sprintf($text('text_check_ok'), $me->storeName, $me->livemode ? 'Live' : 'Test')];
    }
}
