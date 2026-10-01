# WooCommerce → Salla SaaS MVP

## Goal
A multi-tenant Salla App that lets each authorized Salla merchant connect a WooCommerce store, scan its catalog, preview normalized products, and import selected products into that merchant's Salla store.

## Primary flow
1. Merchant installs/authorizes the Salla App.
2. Salla authorization webhook creates or updates the tenant and stores encrypted OAuth tokens.
3. Merchant connects WooCommerce using HTTPS REST API credentials; credentials are encrypted at rest.
4. A queued scan reads `/wp-json/wc/v3/products` and variations and stores immutable source snapshots plus normalized data.
5. Merchant starts all or selected products.
6. Queue jobs create products in Salla, persist source→destination mappings, and expose progress/failures for retries.

## Tenant isolation
Every connection and import belongs to a merchant. API queries scope resources to the merchant context and never accept merchant IDs in request bodies.

## Security
- HTTPS only for WooCommerce stores; SSRF checks reject local/private targets.
- Salla and WooCommerce credentials use Laravel encrypted casts.
- Salla webhook signature is verified before processing.
- Secrets never belong in Git.
- Least-privilege Salla scopes: product read/write plus scopes required by later category/media features.

## MVP acceptance
- Salla authorize/uninstall lifecycle is persisted.
- WooCommerce credentials can be verified and saved.
- Catalog scan is paginated and queued.
- Product import is queued and idempotent through mapping records.
- Per-product failures are visible and retryable.
- MySQL schema and database queue are supported.
- CI validates PHP syntax and unit tests.

## Not yet production-complete
Full variable-product option/value creation, category recreation, media upload verification, billing/subscriptions, admin/support tooling, rate-limit orchestration, and a browser UI remain later milestones. Production release also requires real Salla Partner credentials and an end-to-end test store.
