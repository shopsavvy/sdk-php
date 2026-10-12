# AGENTS.md

Context for coding agents (Claude Code, Codex, Cursor, Copilot and others) working in this repository. Humans: see README.md and CONTRIBUTING.md if present.

## Overview

The official SDK for the [ShopSavvy Data API](https://shopsavvy.com/data) in PHP (8.1+). It wraps the REST API at `https://api.shopsavvy.com/v1`: look up products by barcode (UPC/EAN/ISBN), ASIN, URL, model number or MPN, and get current retailer offers, price history, scheduled refreshes and usage.

- Package: `shopsavvy/shopsavvy-sdk-php` on Packagist (git tags). Install: `composer require shopsavvy/shopsavvy-sdk-php`
- API docs: https://shopsavvy.com/data/documentation
- OpenAPI spec (the contract this SDK follows): https://api.shopsavvy.com/v1/openapi.json
- Error codes: https://shopsavvy.com/data/documentation/errors
- This is one of 10 official SDKs (Python, TypeScript, Go, Rust, Java, Kotlin, Swift, PHP, Ruby, C#). They track the same API, so a new endpoint or field lands in all of them.

## Layout

- `src/ShopSavvyClient.php`: the client and `VERSION`
- `src/Models/`: response models
- `src/Exceptions/`: error types
- `tests/`: PHPUnit tests with `fixture-*.json`

## Commands

```bash
# setup
composer install

# test (offline, no API key needed)
composer test   # phpunit

# lint / build checks
composer phpstan   # level 8
```

## Code conventions

- Serialized field names (JSON tags, annotations, CodingKeys, model attributes) match the API JSON exactly (`shopsavvy`, `barcode`, `amazon`, `model`, `mpn`, `offers`, `retailer`, `price`, `availability`, `timestamp`, `history`, `meta.credits_used`, `meta.credits_remaining`, `meta.rate_limit_remaining`). Add a friendlier alias only alongside the real name, never instead of it.
- Identifiers go in the `ids` query parameter, comma-separated, up to 100 per request (`id` for reviews). The API does not read `barcode=`, `upc=` or `asin=`.
- `availability` is `"in"` or `"out"` and is absent when unknown. Treat absent as unknown, never as in stock. `currency` on a history point can be null; never assume USD for it.
- Map errors from `error_detail.code` (`ERR_AUTH_MISSING`, `ERR_RATE_LIMIT`, `ERR_CREDITS_EXHAUSTED`, ...), not from the HTTP status: two different codes share 429. Surface `Retry-After` on `ERR_RATE_LIMIT`.
- Authenticate with `Authorization: Bearer <key>`. Examples and docs read the key from the `SHOPSAVVY_API_KEY` environment variable.
- Strict types; models are typed classes built from the API array, keeping the API field names.
- `format=csv` methods return the raw CSV text.
- Version lives in `VERSION` in `src/ShopSavvyClient.php`, then a git tag (Packagist reads tags). Bump it in the same change as any public API change.

## Testing

- Unit tests must run offline: mock HTTP and use recorded fixtures. Use obviously fake keys (`ss_test_...`).
- HTTP is mocked; no network.
- When adding an endpoint or field, add a fixture copied from a real response shape in the API docs or OpenAPI spec, and a test that parses it.
- Run the full test command above before committing.

## What NOT to do

- **Never commit an API key, token or password.** Not in code, tests, examples, fixtures, `.env` files or build config. This repo is public. Publishing credentials (registry tokens, signing passwords) stay on the maintainer's machine. If you find one in the tree, remove it and tell a maintainer so it can be rotated.
- Never hardcode a key in an example; use `SHOPSAVVY_API_KEY`.
- Don't invent endpoints, parameters or response fields. If it isn't in the API docs or OpenAPI spec, it doesn't exist yet.
- Don't put plan prices, plan names, dollar amounts or credit costs in the README; link to https://shopsavvy.com/data and https://shopsavvy.com/data/documentation instead.
- Don't add claims about scale, uptime or customers (no "99.9% uptime", "trusted by", retailer or product counts). The approved wording is "tens of thousands of retailers" and "tens of millions of products".
- Don't publish a release (Packagist (git tags)) from an agent session. Prepare the version bump and changelog; a maintainer publishes.
- Don't change public method names or signatures without a deprecation path; other people's code depends on them.
