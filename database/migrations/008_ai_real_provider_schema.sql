CREATE TABLE IF NOT EXISTS ai_provider_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL UNIQUE,
  provider VARCHAR(80) NOT NULL DEFAULT 'simulated',
  model VARCHAR(120) NOT NULL DEFAULT 'asisfly-demo-latam',
  fallback_provider VARCHAR(80) NOT NULL DEFAULT 'simulated',
  fallback_model VARCHAR(120) NOT NULL DEFAULT 'asisfly-demo-latam',
  api_key_env VARCHAR(120) NULL,
  temperature DECIMAL(4,2) NOT NULL DEFAULT 0.40,
  monthly_token_limit INT NOT NULL DEFAULT 500000,
  monthly_cost_limit DECIMAL(12,2) NOT NULL DEFAULT 25.00,
  is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_provider_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ai_usage_logs
  ADD COLUMN prompt_text MEDIUMTEXT NULL AFTER module,
  ADD COLUMN response_text MEDIUMTEXT NULL AFTER prompt_text,
  ADD COLUMN status ENUM('success','fallback','blocked','error') NOT NULL DEFAULT 'success' AFTER estimated_cost,
  ADD COLUMN error_message TEXT NULL AFTER status,
  ADD COLUMN request_id VARCHAR(160) NULL AFTER error_message,
  ADD COLUMN metadata_json JSON NULL AFTER request_id;
