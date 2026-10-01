# Implementation notes

## Import direction

WooCommerce is read-only source data. Salla is the destination. MVP is one-time import, not bidirectional sync.

## Salla

- Merchant API base: `https://api.salla.dev/admin/v2`
- OAuth access uses Bearer tokens.
- Published App Store apps should implement the authorization lifecycle supported by Salla Partners; Easy Mode authorization is required for published apps according to the current Salla authorization documentation.
- Embedded frontend tokens must be verified server-side using Salla token introspection before trusting merchant identity.
- Creating options for a physical product can generate variants. Variant price updates therefore happen after options are created and generated variants are resolved.

## WooCommerce

- REST API v3 base: `{store}/wp-json/wc/v3/`
- Use read-only REST API credentials.
- HTTPS Basic Auth is used for Consumer Key / Consumer Secret.
- Products, variations and categories are paginated and scanned asynchronously.

## Security blockers before production

The initial URL validation only rejects obvious invalid/local hosts. Before accepting arbitrary merchant URLs in production, implement a dedicated SSRF-safe HTTP transport that resolves DNS, rejects private/link-local/reserved addresses, pins/validates redirects, and revalidates every redirect target. Do not ship catalog fetching until that layer is covered by tests.

Credentials and Salla tokens must use Laravel encrypted casts or an equivalent application encryption mechanism; never log decrypted values.

## Next implementation slice

1. Complete Laravel bootstrap files and application providers.
2. Add Salla Easy Mode webhook authorization lifecycle and uninstall handling.
3. Add encrypted Merchant/WooCommerceConnection models.
4. Add SSRF-safe WooCommerce transport.
5. Add queued catalog scanner and pagination checkpoints.
6. Add validation and simple-product importer.
7. Add categories, images, options and deterministic variant mapping.
