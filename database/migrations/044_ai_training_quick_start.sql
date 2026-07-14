CREATE TABLE IF NOT EXISTS ai_training_quick_starts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  brand_route_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  source_text MEDIUMTEXT NOT NULL,
  analysis_json JSON NULL,
  source ENUM('openai','fallback') NOT NULL DEFAULT 'fallback',
  status ENUM('draft','applied','failed','archived') NOT NULL DEFAULT 'draft',
  error_message TEXT NULL,
  applied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_quick_start_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_quick_start_brand FOREIGN KEY (brand_route_id) REFERENCES ai_brand_routes(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_quick_start_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ai_quick_start_scope (company_id, brand_route_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
