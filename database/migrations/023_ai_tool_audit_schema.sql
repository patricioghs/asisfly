CREATE TABLE IF NOT EXISTS ai_tool_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  ability_key VARCHAR(120) NULL,
  tool_key VARCHAR(160) NOT NULL,
  action VARCHAR(80) NOT NULL,
  status ENUM('allowed','blocked','executed','fallback') NOT NULL DEFAULT 'allowed',
  reason VARCHAR(255) NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_tool_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_tool_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ai_tool_audit_company_date (company_id, created_at),
  INDEX idx_ai_tool_audit_tool (tool_key, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
