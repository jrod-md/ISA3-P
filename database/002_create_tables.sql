USE bus_meta;

CREATE TABLE IF NOT EXISTS search_sessions (
    id CHAR(36) PRIMARY KEY,
    criteria_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    ttl_seconds INT UNSIGNED NOT NULL,
    requested_providers VARCHAR(100) NOT NULL,
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_search_sessions_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS search_cache (
    search_id CHAR(36) PRIMARY KEY,
    results_json JSON NOT NULL,
    providers_json JSON NOT NULL,
    warnings_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    CONSTRAINT fk_search_cache_session
      FOREIGN KEY (search_id) REFERENCES search_sessions(id)
      ON DELETE CASCADE
) ENGINE=InnoDB;

USE market_alpha;
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category VARCHAR(80) NOT NULL,
    brand VARCHAR(80) NULL,
    price DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_products_name (name),
    INDEX idx_products_category (category),
    INDEX idx_products_price (price),
    INDEX idx_products_brand (brand)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS market_beta.products LIKE market_alpha.products;
CREATE TABLE IF NOT EXISTS market_gamma.products LIKE market_alpha.products;

