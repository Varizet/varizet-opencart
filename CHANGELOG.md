# Changelog

## [1.1.0] - 2026-10-29

- «اتصال یک‌کلیکی به واریزت» on the settings page (both majors): the admin approves in the Varizet
  panel (PKCE, flow kept in the admin session) and comes back to `…/connected`, which stores the API
  key and webhook secret of that environment. The module reports its webhook route
  (`extension/payment/varizet/webhook` on 3.x, `extension/varizet/payment/varizet.webhook` on 4.x),
  so Varizet registers the right URL. Manual key entry is unchanged.
- PHP SDK 1.1.0 bundled.

## [1.0.0] - 2026-10-29

- First release for OpenCart 3.0.x and 4.0.2+: «واریزت» payment method, settings with Test/Live
  keys and webhook secrets and «بررسی اتصال», checkout confirm → hosted payment page, signed webhook,
  return sync, polling via a token cron URL (and OpenCart 4's scheduler), configurable order
  statuses, Persian (fa-ir) and English languages.
