# API

Browser SaaS endpoints use the encrypted Laravel session established by Salla OAuth and CSRF protection.

- `GET /api/v1/dashboard` — tenant connections and recent imports.
- `POST /api/v1/woocommerce/connections` — verify and save WooCommerce HTTPS REST credentials.
- `POST /api/v1/imports` — create a queued catalog scan.
- `GET /api/v1/imports/{id}` — progress and product preview.
- `POST /api/v1/imports/{id}/run` — queue all products or the supplied `product_ids`.
- `POST /api/webhooks/salla` — Salla lifecycle/authorization events; signature required.
- `GET /auth/salla` / `GET /auth/salla/callback` — Salla Custom Mode OAuth.
- `GET /health` and `/up` — health endpoints.

Never expose WooCommerce secrets or Salla tokens in API responses.
