# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## 1.6.0 - 2026-11-17

### Added
- Every API exception now carries `$errorCode`, `$action`, `$docsUrl`, `$requestId` and `$retryAfterSeconds` (parsed from the `Retry-After` header), read from the API's `error_detail` object, plus `getStatusCode()`.
- `ShopSavvyCreditsExhaustedException` for `ERR_CREDITS_EXHAUSTED`.
- README section "Errors, rate limits and credits".

### Changed
- Credits exhausted (`ERR_CREDITS_EXHAUSTED`, HTTP 429) is no longer thrown as `ShopSavvyRateLimitException`. Code that caught every 429 as a rate limit and retried should retry only `ShopSavvyRateLimitException` and stop on `ShopSavvyCreditsExhaustedException`. A 429 without a code (older server) is still a `ShopSavvyRateLimitException`.
- README quick start reads the key from `SHOPSAVVY_API_KEY` and prints the cheapest offer.

## 1.5.0 - 2026-11-09

### Added
- `in_store_prices` (user-collected receipt prices) on current offers and batch results.

## 1.4.1 - 2026-09-28

### Fixed
- Scheduling responses typed to the real shapes; `format=csv` returns the raw CSV text.

## 1.4.0 - 2026-09-27

### Changed
- `getPriceHistory` is typed as products -> offers -> history (`PriceHistoryResponse`).

### Fixed
- Scheduling used the wrong contract; errors lost the API message.
- One class per file so Composer's PSR-4 autoloader finds every class.

## 1.2.0 - 2026-07-19

### Changed
- Score docs match the real API (0-1 scale, free-form aspects).

## 1.1.0 - 2026-04-01

### Added
- Deals and reviews endpoints and expanded product types. Later additions before 1.2.0: `batchLookup` and `getBatchStatus`, webhook management (create, list, test, delete, update).
