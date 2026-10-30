# Varizet for OpenCart

Payment extension «واریزت» for **OpenCart 3.0.x** and **OpenCart 4.0.2+**: checkout confirm →
Varizet order → hosted payment page (unique amount + destination card); signed webhook, return
sync and a polling fallback update the order. Uses the PHP SDK
([varizet/varizet-php](https://github.com/Varizet/varizet-php)), bundled into each package. Kind in `Varizet-Plugin`: `opencart/1.1.0; oc=<version>; sdk=…; php=…`.

## Layout

```
common/                     shared by both majors (copied into system/library/varizet/ at build)
├─ autoload.php             SDK (lib/varizet-php) + PSR-4 for Varizet\OpenCart\
├─ src/Gateway.php          start · webhook · sync (return) · poll · apply (the single writer)
├─ src/StatusMap.php        pure: Varizet status × order_status_id → history entry
├─ src/Repository.php       {DB_PREFIX}varizet_order (MySQL DDL; plain SQL over OpenCart's DB)
├─ src/Settings.php · Admin.php · Shop.php · CallbackShop.php · Db.php · OpenCartDb.php · Record.php
└─ language/{fa-ir,en-gb}/{admin,catalog}.php
oc3/upload/                 OpenCart 3 tree (extension/payment/varizet: controllers, model, twig)
oc4/                        OpenCart 4 extension "varizet" (install.json, admin/, catalog/ incl. cron/)
bin/assemble.sh 3|4 <dir>   installable tree with languages, shared code and the SDK
bin/build-zip.sh            both packages + .sha256
```

Only the catalog model differs between the majors: it wires `CallbackShop` to `getOrder()`,
`addOrderHistory()` (3.x) / `addHistory()` (4.x), `url->link()` and the language. Everything else
runs the same code.

## Flows

- **Confirm** (`…/varizet/confirm`, AJAX from the checkout button): the order total in the order
  currency → integer rial (`Amount::toRialWithFactor`; currency code or the «واحد پول فروشگاه»
  setting), reuse of an open Varizet order with the same amount, else attempt *n* reserved in
  `varizet_order` before `POST /v1/orders` with `Idempotency-Key: opencart-{order}-{n}-{site8}` and
  `merchant_order_id: OC-{order}[-n]`. The order is confirmed into the «pending» status with the
  unique amount in the history, then the browser goes to `payment_url`.
- **Webhook** (`index.php?route=extension/payment/varizet/webhook` on 3.x,
  `…route=extension/varizet/payment/varizet.webhook` on 4.x): 401 bad signature, 400 not an event or
  wrong mode (secrets are `['live' => …, 'test' => …]`), 200 ping / test events / other
  integrations' orders, 409 an order created < 10 min ago that is not saved yet, 500 apply failed.
- **Mapping** (`StatusMap`, status ids from the settings): paid → paid status (customer notified);
  paid after expiry/cancel → paid + warning; needs_review/underpaid → review status; expired /
  canceled → their statuses (OpenCart restores stock); refunded → refunded when it was paid.
  Human-set statuses (shipped, complete, …) are never overwritten; settled payments never regress;
  replayed and older events are ignored.
- **Return** (`…/callback&order_id=`): one `GET /v1/orders/{id}`, apply, then `checkout/success`
  (or the cart with a message when the payment window closed).
- **Polling**: `…/cron&token=` (token generated on first save, shown on the settings page; call
  it every 5 minutes) and, on 4.x, OpenCart's own scheduler (hourly, registered on install). Open
  records until `grace_until` + 6 h, `GET /v1/orders?id[]=` 100 per request, per mode.

## Settings

Status, mode (Test/Live), API key Live/Test, webhook secret Live/Test, currency unit (auto / ریال /
تومان), payment window, geo zone, sort order, order statuses (pending, paid, review, expired,
canceled, refunded), API URL (advanced; or `define('VARIZET_API_BASE_URL', …)` in `config.php`),
debug log. «بررسی اتصال» calls `GET /v1/me` and checks the key's mode.

## Install

- **OpenCart 4**: unzip `varizet-opencart4-1.1.0.zip`, upload `varizet.ocmod.zip` in Extensions →
  Installer (keep that file name: OpenCart 4 derives the extension folder from it), install it,
  then Extensions → Payments → واریزت.
- **OpenCart 3**: upload `varizet-opencart3-1.1.0.ocmod.zip` in Extensions → Installer, then
  Extensions → Payments → واریزت → install / edit.

## Tests

```bash
bin/sdk.sh              # PHP SDK: the monorepo copy, or fetched into .sdk/ (standalone)
composer install
vendor/bin/phpunit    # common/ against SQLite + fakes
# without a local PHP:
# docker run --rm -v "$PWD":/app -w /app composer:2 sh -c 'bin/sdk.sh && composer install && vendor/bin/phpunit'
bin/build-zip.sh
```

The repository's SQL runs against in-memory SQLite (it accepts MySQL's backticks); `Shop` and the
HTTP transport are fakes.

**Smoke test: not run.** OpenCart has no official Docker image (the Bitnami images are no longer
maintained), so a smoke test means building an image from `php:8.1-apache` plus the release zip and
scripting the AJAX checkout. The same `Gateway` code path was smoke-tested end to end through the
PrestaShop module (shared design, see its README). TODO: `dev/` compose for OpenCart 4.

## One-click connect

«اتصال یک‌کلیکی به واریزت» on the settings page → `…/connect` (needs the modify permission) →
`OneClick::start()` with the PKCE flow in the admin session, the admin's `…/connected&user_token=…`
as `redirect_uri` and the major's `webhook_path` → the Varizet consent page → back to `…/connected`,
which exchanges the code and merges `payment_varizet_api_key_<mode>` + `payment_varizet_webhook_secret_<mode>`
(and `mode` when no key existed) into the settings. Varizet creates and enables the endpoint at
`HTTP_CATALOG + webhook_path`. OpenCart's admin URL needs a session token, so the panel cannot open
it: the connect always starts here. Manual entry stays. Dev: `define('VARIZET_APP_URL', …)`.

## Needs from the API

Nothing open; the shared OpenCart/PrestaShop sync engine could move into the SDK later.

---

## Contributing and security

Issues and pull requests are welcome. This repository is published from Varizet's main repository:
accepted changes are applied there and appear here with the next release.

Please report security vulnerabilities privately to support@varizet.ir, not in public issues.

## License

MIT, see [LICENSE](LICENSE).
