#!/usr/bin/env python3
"""
HSW365.co — Print Series Discount Rotator
===========================================
Targets the Survivor Collection Founder's Print Series (8 designs, limited
run of 25 each, $149). Every run:

1. Pulls current inventory per design (variant) and the product's age.
2. Flags a design as a "slow mover" once it has been live for at least
   AGE_THRESHOLD_DAYS and has sold under MIN_SOLD_RATIO of its run.
3. If no sitewide/all-items discount is currently active (so we don't stack
   confusing offers on top of a storewide push like PUSH10K), creates a
   7-day, variant-scoped discount code for each slow-moving design:
     code:  SLOW-<DESIGN-SLUG>   e.g. SLOW-MIDNIGHT-SHRAPNEL
     value: DISCOUNT_PERCENTAGE off, that one design only
4. Skips designs that already have an active SLOW-* code (checked by title
   prefix) so re-runs don't create duplicates.
5. Emails a summary of anything created.

This is stateless by design — no local state file to keep in sync. It only
relies on Shopify's live inventory + createdAt + existing discount list on
every run, so it's safe to run on a schedule.

Requires the same secrets as inventory_guardian.py:
  SHOPIFY_STORE_DOMAIN, SHOPIFY_ADMIN_ACCESS_TOKEN, SMTP_USER,
  SMTP_PASSWORD, NOTIFY_EMAIL
"""

import os
import sys
import json
import re
import smtplib
import urllib.request
from email.mime.text import MIMEText
from datetime import datetime, timezone, timedelta

STORE_DOMAIN = os.environ.get("SHOPIFY_STORE_DOMAIN", "pc9bg8-gv.myshopify.com")
ADMIN_TOKEN = os.environ.get("SHOPIFY_ADMIN_ACCESS_TOKEN", "")
SMTP_USER = os.environ.get("SMTP_USER", "")
SMTP_PASSWORD = os.environ.get("SMTP_PASSWORD", "")
NOTIFY_EMAIL = os.environ.get("NOTIFY_EMAIL", "hsw365media@gmail.com")

API_VERSION = "2025-01"
GRAPHQL_URL = f"https://{STORE_DOMAIN}/admin/api/{API_VERSION}/graphql.json"

PRINT_SERIES_PRODUCT_ID = "gid://shopify/Product/8582199967905"
RUN_SIZE = 25
AGE_THRESHOLD_DAYS = 14
MIN_SOLD_RATIO = 0.12           # under 12% sold (i.e. <3 of 25) after the age threshold
DISCOUNT_PERCENTAGE = 15.0
DISCOUNT_VALID_DAYS = 7


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


def slugify(title: str) -> str:
    return re.sub(r"[^A-Z0-9]+", "-", title.upper()).strip("-")


def fetch_print_series():
    query = """
    query($id: ID!) {
      product(id: $id) {
        createdAt
        variants(first: 20) {
          edges { node { id title inventoryQuantity } }
        }
      }
    }
    """
    data = gql(query, {"id": PRINT_SERIES_PRODUCT_ID})
    return data["product"]


def fetch_active_discount_codes():
    query = """
    query {
      codeDiscountNodes(first: 50) {
        edges {
          node {
            codeDiscount {
              ... on DiscountCodeBasic {
                status
                codes(first: 1) { edges { node { code } } }
                customerGets { items { ... on AllDiscountItems { allItems } } }
              }
            }
          }
        }
      }
    }
    """
    data = gql(query)
    codes = []
    sitewide_active = False
    for edge in data["codeDiscountNodes"]["edges"]:
        cd = edge["node"]["codeDiscount"]
        if not cd:
            continue
        code = cd["codes"]["edges"][0]["node"]["code"] if cd["codes"]["edges"] else ""
        codes.append({"code": code, "status": cd["status"]})
        items = cd.get("customerGets", {}).get("items", {}) or {}
        if cd["status"] == "ACTIVE" and items.get("allItems"):
            sitewide_active = True
    return codes, sitewide_active


def create_discount(variant_id: str, design_title: str) -> str:
    code = f"SLOW-{slugify(design_title)}"
    now = datetime.now(timezone.utc)
    ends = now + timedelta(days=DISCOUNT_VALID_DAYS)
    gql(
        """
        mutation($input: DiscountCodeBasicInput!) {
          discountCodeBasicCreate(basicCodeDiscount: $input) {
            userErrors { field message }
          }
        }
        """,
        {
            "input": {
                "title": f"Slow-Mover Push — {design_title}",
                "code": code,
                "startsAt": now.isoformat(),
                "endsAt": ends.isoformat(),
                "customerSelection": {"all": True},
                "customerGets": {
                    "value": {"percentage": DISCOUNT_PERCENTAGE / 100},
                    "items": {"products": {"productVariantsToAdd": [variant_id]}},
                },
                "appliesOncePerCustomer": False,
            }
        },
    )
    return code


def send_email(subject: str, body: str) -> None:
    if not SMTP_USER or not SMTP_PASSWORD:
        print("[discount_rotator] SMTP not configured — skipping email send.")
        print(f"[discount_rotator] Would have sent: {subject}\n{body}")
        return
    msg = MIMEText(body)
    msg["Subject"] = subject
    msg["From"] = SMTP_USER
    msg["To"] = NOTIFY_EMAIL
    with smtplib.SMTP_SSL("smtp.gmail.com", 465) as server:
        server.login(SMTP_USER, SMTP_PASSWORD)
        server.sendmail(SMTP_USER, [NOTIFY_EMAIL], msg.as_string())
    print(f"[discount_rotator] Sent email: {subject}")


def main() -> None:
    if not ADMIN_TOKEN:
        print(
            "[discount_rotator] SHOPIFY_ADMIN_ACCESS_TOKEN is not set. "
            "See automation/README.md for setup. Exiting without changes."
        )
        sys.exit(0)

    product = fetch_print_series()
    created_at = datetime.fromisoformat(product["createdAt"].replace("Z", "+00:00"))
    age_days = (datetime.now(timezone.utc) - created_at).days

    existing_codes, sitewide_active = fetch_active_discount_codes()
    existing_code_names = {c["code"] for c in existing_codes}

    if sitewide_active:
        print(
            "[discount_rotator] A sitewide (all-items) discount is currently "
            "active — skipping slow-mover discount creation this run to avoid "
            "stacking offers."
        )
        return

    created = []
    for edge in product["variants"]["edges"]:
        variant = edge["node"]
        sold = RUN_SIZE - variant["inventoryQuantity"]
        sold_ratio = sold / RUN_SIZE

        is_slow_mover = age_days >= AGE_THRESHOLD_DAYS and sold_ratio < MIN_SOLD_RATIO
        if not is_slow_mover:
            continue

        code_name = f"SLOW-{slugify(variant['title'])}"
        if code_name in existing_code_names:
            continue  # already has an active push

        create_discount(variant["id"], variant["title"])
        created.append((variant["title"], code_name))

    if created:
        body = (
            f"Print Series is {age_days} days old. The following designs are "
            f"under {int(MIN_SOLD_RATIO * 100)}% sold and got a "
            f"{int(DISCOUNT_PERCENTAGE)}% off, {DISCOUNT_VALID_DAYS}-day code:\n\n"
            + "\n".join(f"- {title}: code {code}" for title, code in created)
            + "\n\nConsider pairing each with a quick story/post using the code."
        )
        send_email("hsw365.co — Slow-Mover Discounts Created", body)
    else:
        print("[discount_rotator] No slow movers to flag this run.")


if __name__ == "__main__":
    main()
