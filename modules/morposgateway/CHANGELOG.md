# Changelog

All notable changes to the MorPOS Payment Plugin for PrestaShop are documented here.

## 1.0.2

* Security: TLS certificate verification is now enforced for all API requests (production and sandbox); it was previously disabled.
* Security: A failed payment can no longer be recorded with the success/paid order status. When the success and failed order statuses were configured to the same value, a first failed attempt moved the order into the "paid" status and a later failed attempt was caught as an already-paid order and confirmed. The failed status now always falls back to a non-paid status when it would otherwise equal the success status.
* Fix: The "already paid" duplicate-callback guard now relies on a genuinely paid order state (OrderState->paid) instead of an order-status id match, so a failed order is never mistaken for a paid one.
* Fix: Payment retry now resolves the failed status the same way as the callback, so retrying a failed order no longer shows "This order is not awaiting payment".
* Improvement: Settings now reject identical success/failed statuses, and require the success status to be a paid status and the failed status to be non-paid.
* Improvement: Removed a misleading per-payment "concurrent callback detected" log entry that was logged on every normal payment, reducing log noise.

## 1.0.1

* Beta release.

## 1.0.0

* Initial release.
