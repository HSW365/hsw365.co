# HSW365 Independent Store

Standalone HSW365 storefront foundation for IONOS Deploy Now PHP hosting. This is a new independent commerce app, not a Shopify theme upload. The Shopify store remains available during migration.

## Hosting architecture

IONOS Deploy Now provides PHP and MariaDB for PHP projects, but no Node.js runtime for server-side applications. This project uses PHP + MariaDB for commerce APIs and browser JavaScript for the storefront. Node can be used by GitHub Actions during build only.

## Built in this branch

- Responsive storefront with search, collection filters, product images, variant selection and cart.
- Product catalog API backed by MariaDB.
- Export of all 59 active Shopify products and 216 variants from the connected HSW365 store.
- Password-protected admin area to review categories, product status, variant status and recent orders.
- Server-side Stripe Checkout; prices and inventory are read from MariaDB, not trusted from browser input.
- Monthly Stripe subscription checkout for app memberships, separated from one-time goods.
- Stripe webhook signature verification, order persistence and subscription status updates.
- Fulfillment queue foundation for Printful, CJ Dropshipping and manual/digital delivery.
- Environment-based credentials; no live secrets stored in GitHub.

## Deployment and secrets

Deploy the independent-store/ project directory as a PHP project in IONOS Deploy Now. Create a MariaDB database and set these environment secrets in the IONOS project:

- APP_BASE_URL
- DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
- STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET
- ADMIN_PASSWORD

Also set STRIPE_SHIPPING_RATE_ID before enabling physical products. Create and test the shipping rate in Stripe first. Set STRIPE_AUTOMATIC_TAX=true only after Stripe Tax is configured for the business. Initial shipping collection is limited to US and Canada.

Optional provider secrets (not active until product mappings and test orders are verified):
- PRINTFUL_API_TOKEN
- CJ_API_KEY

Copy config.example.php to config.php on the host (never commit the live config). The .htaccess blocks direct access to config.php.

## Database and product migration

1. Import database/schema.sql into the IONOS MariaDB database.
2. Upload data/shopify-products-export.json with the project.
3. Run php scripts/import-shopify-json.php data/shopify-products-export.json from a PHP CLI environment.
4. The importer loads 59 products and 216 variants as DRAFT. Nothing is published automatically.
5. Review every product and variant in /admin/; check images, prices, inventory, digital delivery, and supplier mappings before activating.

The exported Liquid theme in ../shopify-theme/ is a design reference, not standalone application code. Its Shopify-specific Liquid objects and checkout APIs are not usable on IONOS.

## Stripe setup

1. Set Stripe test keys first.
2. Register https://YOUR-DOMAIN/api/stripe-webhook.php in Stripe for checkout.session.completed, checkout.session.async_payment_succeeded, invoice.paid, customer.subscription.updated, and customer.subscription.deleted.
3. Store the webhook signing secret as STRIPE_WEBHOOK_SECRET.
4. Create a shipping rate in Stripe and set STRIPE_SHIPPING_RATE_ID before activating physical products.
5. Test one-time digital purchases, app subscriptions, and physical-product shipping separately. Only switch to live keys after all tests pass.

Card data is entered on Stripe-hosted Checkout and does not touch this server.

## Fulfillment rollout

Paid orders are recorded and placed in a fulfillment queue. Supplier orders are NOT submitted automatically yet. That requires provider credentials, exact variant mapping, address validation, supplier shipping methods and a test order.

- Printful: create a manual/API store, map HSW365 SKUs to Printful sync variants, then implement order submission and shipment webhooks.
- CJ Dropshipping: obtain API credentials, exchange them for an access token, map HSW365 SKUs to CJ variant IDs, then implement order creation, payment and logistics webhooks.
- Digital goods and memberships still need secure file delivery or app-specific membership provisioning.

## Do not switch DNS or cancel Shopify yet

Before launch, reconcile products/variants/images; confirm shipping and taxes; test Stripe; test email receipts; connect fulfillment and tracking; migrate required customer/order data; test backups and restore; and verify admin access. Keep Shopify intact until all launch gates pass.
