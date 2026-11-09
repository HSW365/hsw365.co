CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  shopify_product_id VARCHAR(64) NULL UNIQUE,
  handle VARCHAR(255) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description MEDIUMTEXT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'merch',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  image_url TEXT NULL,
  image_alt VARCHAR(500) NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_status_category (status, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  shopify_variant_id VARCHAR(64) NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  sku VARCHAR(100) NULL,
  price DECIMAL(10,2) NOT NULL,
  inventory_quantity INT NULL,
  options_json JSON NULL,
  provider ENUM('unassigned','printful','cj','manual','digital') NOT NULL DEFAULT 'unassigned',
  provider_variant_id VARCHAR(255) NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_variant_product_status (product_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_ref CHAR(32) NOT NULL UNIQUE,
  stripe_session_id VARCHAR(255) NULL UNIQUE,
  stripe_payment_intent VARCHAR(255) NULL,
  customer_email VARCHAR(255) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pending_payment',
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  subtotal_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
  items_json JSON NOT NULL,
  shipping_json JSON NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_orders_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stripe_webhook_events (
  event_id VARCHAR(255) NOT NULL PRIMARY KEY,
  event_type VARCHAR(120) NOT NULL,
  received_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fulfillment_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_ref CHAR(32) NOT NULL,
  provider ENUM('unassigned','printful','cj','manual','digital') NOT NULL DEFAULT 'unassigned',
  provider_order_id VARCHAR(255) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'needs_mapping',
  tracking_number VARCHAR(255) NULL,
  tracking_url TEXT NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_order_provider (order_ref, provider),
  INDEX idx_fulfillment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;