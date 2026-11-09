# HSW365 Independent Store

A standalone HSW365 storefront foundation designed for IONOS Deploy Now PHP hosting. It is deliberately separate from the Shopify theme export so the original Shopify store remains available during migration.

## Hosting architecture

IONOS Deploy Now provides a PHP runtime and MariaDB for PHP projects. It does **not** provide a Node.js runtime for server-side applications. This project therefore uses PHP + MariaDB for checkout APIs and a lightweight browser storefront. GitHub Actions may use Node at build time, but the live site must not depend on Node running on IONOS.

## Included in this first build

- Responsive HSW365 storefront with product search, category filters and cart.
- Product catalog API backed by MariaDB.
- Server-side Stripe Checkout Session creation; prices are always read from the database, never trusted from the browser.
- Stripe webhook signature verification and paid-order recording.
- CSV product importer for Shopify's standard product export.
- Fulfillment queue schema and provider integration boundary for Printful and CJ Dropshipping.
- Environment-based secrets; no API keys committed.

## Required environment variables

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`
- `APP_BASE_URL`

Optional provider credentials (not active until configured):
- `PRINTFUL_API_TOKEN`
- `CJ_API_KEY`

Set these in the IONOS runtime configuration / secrets panel. Never place live credentials in GitHub.

## Database setup

1. Create a MariaDB database in IONOS.
2. Import `database/schema.sql` using phpMyAdmin or the IONOS database console.
3. Set the database environment variables.
4. Import the Shopify product CSV with `php scripts/import-shopify-csv.php /path/to/products_export.csv` from a PHP CLI environment. The importer is safe to rerun by Shopify handle.
5. Confirm product prices, variants, inventory, images and categories before opening the store.

## Stripe setup

1. Create a Stripe account or use the existing account.
2. Set `STRIPE_SECRET_KEY` as an environment secret.
3. Register `https://YOUR-DOMAIN/api/stripe-webhook.php` in Stripe for `checkout.session.completed` and `checkout.session.async_payment_succeeded`.
4. Set the signing secret as `STRIPE_WEBHOOK_SECRET`.
5. Test with Stripe test keys and test cards before switching to live mode.

The browser is redirected to Stripe-hosted Checkout. Card data never touches this server.

## Fulfillment rollout

Payment capture and fulfillment are intentionally separate. Paid orders enter a queue; no supplier order is submitted automatically until the product-to-provider variant mapping, shipping method, taxes/duties behavior, and provider credentials are verified. This prevents accidental supplier charges or incorrect shipments.

- Printful: configure a manual/API store and map each HSW365 SKU to its Printful sync variant. Use the Printful Orders API and webhooks for shipment updates.
- CJ Dropshipping: obtain API credentials, exchange them for an access token, map each HSW365 SKU to CJ variant IDs, then create/pay orders and receive logistics updates.
- Products without a provider mapping remain in the manual-fulfillment queue.

## Important migration gates

Do not cancel Shopify, change DNS, or redirect customers until:
- all 59 currently imported products and their variants/media are reconciled against Shopify;
- product prices, inventory, shipping, taxes and refund policies are checked;
- Stripe test and live checkout flows pass;
- paid orders persist and confirmation emails are tested;
- Printful and CJ test orders and tracking webhooks pass;
- customer/order history migration and privacy/consent requirements are addressed;
- backup, restore and admin access are tested.

The exported Liquid theme in `../shopify-theme/` is a design reference, not executable standalone code. This storefront rebuilds the commerce behavior independently.
