<?php

declare(strict_types=1);

namespace Varizet\OpenCart;

use Varizet\Connect\ConnectOutcome;
use Varizet\Connect\OneClickConnect;
use Varizet\VarizetClient;

/**
 * «اتصال یک‌کلیکی» (docs/10 §1.4) shared by the OpenCart 3 and 4 admin controllers: `start()` sends
 * the admin to the Varizet consent page (the PKCE flow is kept in the admin session), `finish()`
 * exchanges the returned code and gives the settings to merge (key + webhook secret of the
 * connected environment). The webhook path differs per major, so the plugin reports it.
 */
final class OneClick
{
    public const SESSION_KEY = 'varizet_connect';
    public const WEBHOOK_PATH_3 = '/index.php?route=extension/payment/varizet/webhook';
    public const WEBHOOK_PATH_4 = '/index.php?route=extension/varizet/payment/varizet.webhook';

    public function __construct(
        private readonly Settings $settings,
        /** HTTP(S)_CATALOG: the shop's front URL (the site that connects). */
        private readonly string $catalogUrl,
        private readonly string $webhookPath,
        private readonly ?string $appUrl = null,
    ) {
    }

    /**
     * @param string               $redirectUri the admin «connected» action, with the admin token
     * @param array<string, mixed> $session     OpenCart's `$this->session->data`
     */
    public function start(string $redirectUri, array &$session, ?string $intent = null, ?int $now = null): string
    {
        $start = $this->connect()->start(array_filter([
            'client' => 'opencart',
            'site_url' => $this->siteUrl(),
            'redirect_uri' => $redirectUri,
            'plugin_version' => Settings::VERSION,
            'webhook_path' => $this->webhookPath,
            'intent' => $intent !== null && preg_match('/^[A-Za-z0-9._~-]{10,400}$/D', $intent) === 1 ? $intent : null,
        ], static fn ($v): bool => $v !== null), $now);
        $session[self::SESSION_KEY] = $start->flow();
        return $start->url;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $session
     * @return array{ok: bool, error?: string, values?: array<string, string>, mode?: string, store?: string}
     *         `error` is a language key; `values` the `payment_varizet_*` settings to merge.
     */
    public function finish(array $query, array &$session, ?int $now = null): array
    {
        $flow = is_array($session[self::SESSION_KEY] ?? null) ? $session[self::SESSION_KEY] : null;
        unset($session[self::SESSION_KEY]);
        $outcome = $this->connect()->finish($query, $flow, $this->siteUrl(), $now);
        if (!$outcome->ok() || $outcome->result === null) {
            return ['ok' => false, 'error' => match ($outcome->error) {
                ConnectOutcome::CANCELLED => 'error_connect_cancelled',
                ConnectOutcome::EXCHANGE_FAILED => 'error_connect_exchange',
                default => 'error_connect_expired',
            }];
        }
        $result = $outcome->result;
        $mode = $result->mode();
        $values = [
            Settings::PREFIX . 'api_key_' . $mode => $result->apiKey,
            Settings::PREFIX . 'webhook_secret_' . $mode => $result->webhookSecret,
        ];
        if ($this->settings->apiKey() === null) {
            $values[Settings::PREFIX . 'mode'] = $mode;
        }
        return ['ok' => true, 'values' => $values, 'mode' => $mode, 'store' => $result->storeName];
    }

    private function siteUrl(): string
    {
        return rtrim($this->catalogUrl, '/');
    }

    private function connect(): OneClickConnect
    {
        $appUrl = $this->appUrl ?? (defined('VARIZET_APP_URL') ? (string) constant('VARIZET_APP_URL') : OneClickConnect::DEFAULT_APP_URL);
        return VarizetClient::oneClickConnect(array_filter([
            'app_url' => $appUrl,
            'base_url' => $this->settings->baseUrl(),
            'http_client' => Settings::$transport,
            'plugin' => ['kind' => 'opencart', 'version' => Settings::VERSION],
        ], static fn ($v): bool => $v !== null));
    }
}
