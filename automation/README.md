# hsw365.co Automation

Automates the Shopify store for HOODSTARWORLD streetwear (hsw365.co /
pc9bg8-gv.myshopify.com).

## What it does (`scripts/inventory_guardian.py`)

Runs every 30 minutes via GitHub Actions (`.github/workflows/inventory_guardian.yml`):

1. **Auto-archives sold 1-of-1 pieces.** Every Survivor Collection tee is a
   single unit. The instant a variant's inventory hits 0, the product is
   archived, tagged `sold`, and `trending` is stripped — so the storefront
   never shows a dead sold-out listing.
2. **Low-stock alerts.** Founder's Print Series designs (limited runs of 25)
   trigger an email once remaining stock drops under 5.
3. **Daily digest.** Once per day, emails a one-line snapshot of pieces still
   live and units remaining across both product lines.

Emails go to `hsw365media@gmail.com` by default (override with the
`NOTIFY_EMAIL` secret).

## One-time setup required (cannot be done from here)

The script needs a Shopify **Admin API access token**, which only a store
owner can generate:

1. In Shopify Admin: **Settings > Apps and sales channels > Develop apps**
2. **Create an app** (e.g. "HSW365 Inventory Guardian")
3. Configure Admin API scopes: `read_products`, `write_products`,
   `read_inventory`, `write_inventory`
4. Install the app, then copy the **Admin API access token**

Then in this GitHub repo: **Settings > Secrets and variables > Actions**, add:

| Secret | Value |
|---|---|
| `SHOPIFY_STORE_DOMAIN` | `pc9bg8-gv.myshopify.com` |
| `SHOPIFY_ADMIN_ACCESS_TOKEN` | the token from step 4 above |
| `SMTP_USER` | `hsw365media@gmail.com` |
| `SMTP_PASSWORD` | a Gmail **app password** (not the regular password) |
| `NOTIFY_EMAIL` | `hsw365media@gmail.com` (optional, this is the default) |

Once those secrets are set, the workflow starts running automatically on its
30-minute schedule — no further action needed. You can also trigger it
manually any time from the **Actions** tab ("Run workflow").

Until `SHOPIFY_ADMIN_ACCESS_TOKEN` is set, the script exits immediately
without making changes (safe no-op).

## Extending this

Same pattern as the CallTwin lead hunter — add new scripts under
`automation/scripts/` and a matching workflow under `.github/workflows/`.
Natural next additions:
- Auto-post "SOLD" / "restock" content prompts to a Canva or content queue
- Auto-apply/rotate discount codes on slow-moving Print Series designs
- Sync FAITH OVER FEAR / digital vault sales into a standing revenue log
