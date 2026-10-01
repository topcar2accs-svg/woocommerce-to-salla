# Final acceptance test

The owner only needs to participate in this final stage because real Salla/WooCommerce credentials cannot be fabricated.

1. Deploy the release with production environment variables and run `php artisan migrate --force`.
2. Run `php artisan app:health-check`; all checks must pass.
3. Configure Salla redirect URI `/auth/salla/callback` and webhook `/api/webhooks/salla`.
4. Install/authorize the app in a Salla development store and confirm the dashboard session is established.
5. Authorize a WooCommerce test store through `/auth/woocommerce` or save read-only REST credentials.
6. Scan a catalog containing: simple product, variable product, sale price, SKU, stock, multiple images, Arabic text.
7. Import selected simple products and confirm name, SKU, price, quantity and description in Salla.
8. Re-run the same import and confirm mapped products are not duplicated.
9. Send an invalid Salla webhook signature and confirm HTTP 401; replay a valid payload and confirm it is treated as duplicate.
10. Revoke/uninstall the Salla app and confirm stored OAuth tokens are cleared.

Variable options, categories and media transfer are not part of the current acceptance gate and must not be represented as complete until their dedicated importer is implemented.
