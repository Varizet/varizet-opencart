<?php

declare(strict_types=1);

namespace Varizet\OpenCart\Tests\Unit;

use Varizet\OpenCart\Admin;
use Varizet\OpenCart\CallbackShop;
use Varizet\OpenCart\Repository;
use Varizet\OpenCart\Settings;

final class AdminTest extends TestCase
{
    private function text(): callable
    {
        $_ = [];
        require __DIR__ . '/../../common/language/fa-ir/admin.php';
        return static fn (string $k): string => $_[$k] ?? $k;
    }

    public function testValuesFallBackToSavedThenDefaultsAndMintACronToken(): void
    {
        $values = Admin::values(['payment_varizet_mode' => 'live'], static fn (string $k) => $k === 'payment_varizet_sort_order' ? '3' : null);
        self::assertSame('live', $values['payment_varizet_mode']);
        self::assertSame('3', $values['payment_varizet_sort_order']);
        self::assertSame('14', $values['payment_varizet_order_status_expired']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $values['payment_varizet_cron_token']);
        self::assertCount(count(Admin::FIELDS), $values);
    }

    public function testValidate(): void
    {
        $t = $this->text();
        self::assertSame([], Admin::validate(self::config(), $t));
        $errors = Admin::validate(self::config(['api_key_live' => 'vz_test_wrongMode0001', 'mode' => 'test']), $t);
        self::assertSame(['api_key_live'], array_keys($errors));
        self::assertSame(['api_key_live' => 'کلید API محیط انتخاب‌شده را وارد کنید.'], Admin::validate(self::config(['mode' => 'live', 'api_key_live' => '']), $t));
    }

    public function testCheck(): void
    {
        $t = $this->text();
        $this->http->json(200, ['store' => ['id' => 'sto_1', 'name' => 'کتاب‌نما', 'slug' => 'k'], 'livemode' => false, 'scopes' => [], 'key' => ['prefix' => 'vz_test_', 'last4' => '0001']]);
        self::assertSame(['success' => 'اتصال برقرار است: فروشگاه «کتاب‌نما»، حالت Test.'], Admin::check(self::config(), $t));
        $this->http->json(200, ['store' => ['id' => 'sto_1', 'name' => 'n', 'slug' => 'k'], 'livemode' => false, 'scopes' => [], 'key' => ['prefix' => 'vz_test_', 'last4' => '0001']]);
        self::assertSame(['error' => 'این کلید متعلق به حالت Live نیست.'], Admin::check(self::config(['mode' => 'live']), $t));
        $this->http->json(401, ['error' => ['type' => 'authentication_error', 'code' => 'invalid_api_key', 'message' => 'کلید API نامعتبر است.', 'param' => null, 'request_id' => 'r']]);
        self::assertSame(['error' => 'اتصال ناموفق بود: کلید API نامعتبر است.'], Admin::check(self::config(), $t));
        self::assertSame(['error' => 'کلید API باید با vz_live_ یا vz_test_ شروع شود.'], Admin::check(self::config(['api_key_test' => 'nope']), $t));
    }

    public function testSettingsAndHelpers(): void
    {
        $s = $this->settings(['expires_in' => '10', 'currency_unit' => 'IRR']);
        self::assertSame(300, $s->expiresIn());
        self::assertSame(1, $s->factorFor('USD'));
        self::assertSame(['live' => self::SECRET_LIVE, 'test' => self::SECRET_TEST], $s->webhookSecrets());
        self::assertSame(21, $s->statusId('review'));
        self::assertSame(1, (new Settings(static fn () => null))->statusId('pending'));
        self::assertSame('https://api.varizet.ir', $s->baseUrl());
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `oc_varizet_order`', Repository::installSql('oc_')[0]);

        $normalized = CallbackShop::normalizeOrder(
            ['order_id' => '5', 'order_status_id' => '1', 'total' => '85000.0000', 'currency_code' => 'IRT', 'currency_value' => '1.00000000', 'firstname' => 'a', 'lastname' => 'b', 'telephone' => '', 'email' => '', 'store_url' => 'https://x/'],
            static fn ($total, $code, $value) => (float) $total * (float) $value,
        );
        self::assertSame(['order_id' => 5, 'order_status_id' => 1, 'total' => '85000'], array_slice((array) $normalized, 0, 3));
        self::assertNull(CallbackShop::normalizeOrder(false, static fn () => 0));
    }
}
