# WooCommerce → Salla Importer

Salla application for importing a WooCommerce product catalog into the merchant's current Salla store.

## MVP

- Salla merchant authorization/session foundation
- Read-only WooCommerce REST API connection
- Catalog scan with pagination
- Normalized product model
- Simple and variable product migration architecture
- Queue-based imports, retry/resume and persistent mappings

## Stack

- PHP 8.2+
- Laravel 12
- PostgreSQL
- Redis / Laravel queues
- Salla Merchant API
- WooCommerce REST API v3

> This repository is under active development. Never commit API keys, Salla tokens, WooCommerce consumer secrets, or `.env` files.
