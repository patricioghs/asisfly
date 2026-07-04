CREATE TABLE IF NOT EXISTS quote_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(80) NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 19.00,
  currency CHAR(3) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_quote_products_company FOREIGN KEY (company_id) REFERENCES companies(id),
  UNIQUE KEY uq_quote_products_company_sku (company_id, sku),
  INDEX idx_quote_products_company_active (company_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE quotes
  ADD COLUMN opportunity_id BIGINT UNSIGNED NULL AFTER customer_id,
  ADD COLUMN valid_until DATE NULL AFTER currency,
  ADD COLUMN notes TEXT NULL AFTER valid_until,
  ADD COLUMN terms TEXT NULL AFTER notes,
  ADD COLUMN pdf_path VARCHAR(255) NULL AFTER terms,
  ADD COLUMN sent_channel VARCHAR(40) NULL AFTER pdf_path,
  ADD COLUMN sent_to VARCHAR(180) NULL AFTER sent_channel,
  ADD COLUMN sent_at TIMESTAMP NULL AFTER sent_to,
  ADD COLUMN accepted_at TIMESTAMP NULL AFTER sent_at,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER accepted_at,
  ADD CONSTRAINT fk_quotes_opportunity FOREIGN KEY (opportunity_id) REFERENCES crm_opportunities(id);

ALTER TABLE quote_items
  ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER quote_id,
  ADD COLUMN sku VARCHAR(80) NULL AFTER product_id,
  ADD CONSTRAINT fk_quote_items_product FOREIGN KEY (product_id) REFERENCES quote_products(id);
