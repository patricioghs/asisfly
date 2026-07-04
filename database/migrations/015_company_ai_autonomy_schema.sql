CREATE TABLE IF NOT EXISTS company_ai_autonomy (
  company_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  learning_progress TINYINT UNSIGNED NOT NULL DEFAULT 18,
  mode ENUM('supervised_learning','copilot','controlled_autonomy','autonomous') NOT NULL DEFAULT 'supervised_learning',
  approvals_count INT UNSIGNED NOT NULL DEFAULT 0,
  corrections_count INT UNSIGNED NOT NULL DEFAULT 0,
  autonomous_actions_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_signal_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_company_ai_autonomy_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
