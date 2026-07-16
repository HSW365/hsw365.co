#!/usr/bin/env python3
"""
HSW365.co — Inventory Guardian
================================
Automates the hsw365.co Shopify store (HOODSTARWORLD streetwear):

1. SOLD-OUT HANDLING (one-of-one Survivor Collection):
   Every Survivor Collection tee is a hand-bleached 1-of-1. The moment a
   variant's inventory hits 0, this script:
     - archives the product (status -> ARCHIVED) so it drops off the
       live storefront instead of showing a dead "sold out" listing
     - tags it "sold" and removes "trending" if present
     - emails a notification so Elvin knows which piece sold

2. LOW-STOCK ALERTS (Founder's Print Series, limited run of 25/design):
   When a print design's remaining inventory drops below LOW_STOCK_THRESHOLD,
   emails a restock/urgency-messaging alert (does not change status).

3. DAILY DIGEST (optional, only fires on the first run of the UTC day):
   Summarizes total active listings + total units remaining across the
   Survivor Collection and Print Series, so Elvin has a standing pulse
   check without opening Shopify Admin.

Run on a schedule via GitHub Actions (see
.github/workflows/inventory_guardian.yml). Requires these repo secrets:

  SHOPIFY_STORE_DOMAIN        e.g. pc9bg8-gv.myshopify.com
  SHOPIFY_ADMIN_ACCESS_TOKEN  Admin API access token from a custom app
                               (Shopify Admin > Settings > Apps and sales
                               channels > Develop apps). Needs scopes:
                               read_products, write_products,
                               read_inventory, write_inventory
  SMTP_USER                   Gmail address used to send alerts
  SMTP_PASSWORD               Gmail app password (not the regular password)
  NOTIFY_EMAIL                where alerts are sent (defaults to
                               hsw365media@gmail.com if unset)
"""

import os
import sys
import json
import smtplib
import urllib.request
from email.mime.text import MIMEText
from datetime import datetime, timezone

STORE_DOMAIN = os.environ.get("SHOPIFY_STORE_DOMAIN", "pc9bg8-gv.myshopify.com")
ADMIN_TOKEN = os.environ.get("SHOPIFY_ADMIN_ACCESS_TOKEN", "")
SMTP_USER = os.environ.get("SMTP_USER", "")
SMTP_PASSWORD = os.environ.get("SMTP_PASSWORD", "")
NOTIFY_EMAIL = os.environ.get("NOTIFY_EMAIL", "hsw365media@gmail.com")

API_VERSION = "2025-01"
GRAPHQL_URL = f"https://{STORE_DOMAIN}/admin/api/{API_VERSION}/graphql.json"

LOW_STOCK_THRESHOLD = 5


def gql(query: str, variables: dict | None = None) -> dict:
    body = json.dumps({"query": query, "variables": variables or {}}).encode("utf-8")
    req = urllib.request.Request(
        GRAPHQL_URL,
        data=body,
        headers={
            "Content-Type": "application/json",
            "X-Shopify-Access-Token": ADMIN_TOKEN,
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=30) as resp:
        payload = json.loads(resp.read().decode("utf-8"))
    if "errors" in payload:
        raise RuntimeError(f"Shopify GraphQL error: {payload['errors']}")
    return payload["data"]


def fetch_products() -> list[dict]:
    query = """
    query {
      products(first: 100, query: "status:active") {
        edges {
          node {
            id
            title
            status
            tags
            totalInventory
            tracksInventory
            variants(first: 5) {
              edges { node { id inventoryQuantity } }
            }
          }
        }
      }
    }
    """
    data = gql(query)
    return [e["node"] for e in data["products"]["edges"]]


def archive_and_tag_sold(product: dict) -> None:
    pid = product["id"]
    gql(
        """
        mutation($id: ID!, $status: ProductStatus!) {
          productUpdate(input: {id: $id, status: $status}) {
            userErrors { field message }
          }
        }
        """,
        {"id": pid, "status": "ARCHIVED"},
    )
    gql(
        """
        mutation($id: ID!, $tags: [String!]!) {
          tagsAdd(id: $id, tags: $tags) { userErrors { field message } }
        }
        """,
        {"id": pid, "tags": ["sold"]},
    )
    if "trending" in product["tags"]:
        gql(
            """
            mutation($id: ID!, $tags: [String!]!) {
              tagsRemove(id: $id, tags: $tags) { userErrors { field message } }
            }
            """,
            {"id": pid, "tags": ["trending"]},
        )


def send_email(subject: str, body: str) -> None:
    if not SMTP_USER or not SMTP_PASSWORD:
        print("[inventory_guardian] SMTP not configured — skipping email send.")
        print(f"[inventory_guardian] Would have sent: {subject}\n{body}")
        return
    msg = MIMEText(body)
    msg["Subject"] = subject
    msg["From"] = SMTP_USER
    msg["To"] = NOTIFY_EMAIL
    with smtplib.SMTP_SSL("smtp.gmail.com", 465) as server:
        server.login(SMTP_USER, SMTP_PASSWORD)
        server.sendmail(SMTP_USER, [NOTIFY_EMAIL], msg.as_string())
    print(f"[inventory_guardian] Sent email: {subject}")


def main() -> None:
    if not ADMIN_TOKEN:
        print(
            "[inventory_guardian] SHOPIFY_ADMIN_ACCESS_TOKEN is not set. "
            "Create a custom app in Shopify Admin (Settings > Apps and sales "
            "channels > Develop apps) with read/write on products + inventory, "
            "then add the token as a repo secret. Exiting without changes."
        )
        sys.exit(0)

    products = fetch_products()

    sold_out_actions = []
    low_stock_alerts = []
    survivor_units_left = 0
    survivor_pieces_live = 0
    print_units_left = 0

    for p in products:
        if not p["tracksInventory"]:
            continue

        variants = [v["node"] for v in p["variants"]["edges"]]
        total_qty = sum(v["inventoryQuantity"] for v in variants)
        tags = p["tags"]

        is_survivor_one_of_one = (
            "survivor-collection" in tags and "one-of-one" in tags
        )
        is_print_series = "print" in tags or "Print Series" in p["title"]

        if is_survivor_one_of_one:
            if total_qty <= 0:
                archive_and_tag_sold(p)
                sold_out_actions.append(p["title"])
            else:
                survivor_units_left += total_qty
                survivor_pieces_live += 1

        elif is_print_series:
            print_units_left += total_qty
            if 0 < total_qty < LOW_STOCK_THRESHOLD:
                low_stock_alerts.append(f"{p['title']} — {total_qty} left")

    # --- Sold-out notification ---
    if sold_out_actions:
        body = (
            "The following one-of-one Survivor Collection pieces just sold "
            "and were automatically archived + tagged 'sold':\n\n"
            + "\n".join(f"- {t}" for t in sold_out_actions)
            + "\n\nConsider posting a 'SOLD' story/reel to drive urgency on "
            "the remaining pieces."
        )
        send_email("hsw365.co — Piece(s) Sold, Auto-Archived", body)

    # --- Low stock notification ---
    if low_stock_alerts:
        body = (
            "Founder's Print Series designs running low:\n\n"
            + "\n".join(f"- {a}" for a in low_stock_alerts)
            + "\n\nConsider a restock push or 'almost gone' content."
        )
        send_email("hsw365.co — Low Stock Alert", body)

    # --- Daily digest (first run after 00:00 UTC) ---
    now = datetime.now(timezone.utc)
    if now.hour == 0 and now.minute < 30:
        body = (
            f"Daily hsw365.co snapshot ({now.date().isoformat()}):\n\n"
            f"- Survivor Collection pieces still live: {survivor_pieces_live}\n"
            f"- Survivor Collection units remaining: {survivor_units_left}\n"
            f"- Founder's Print Series units remaining: {print_units_left}\n"
        )
        send_email("hsw365.co — Daily Inventory Snapshot", body)

    if not sold_out_actions and not low_stock_alerts:
        print("[inventory_guardian] No action needed this run.")


if __name__ == "__main__":
    main()
