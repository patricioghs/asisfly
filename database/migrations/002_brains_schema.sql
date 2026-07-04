CREATE TABLE IF NOT EXISTS ai_brains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  brain_key VARCHAR(80) NOT NULL,
  name VARCHAR(140) NOT NULL,
  mission TEXT NOT NULL,
  status ENUM('active','sandbox','disabled') NOT NULL DEFAULT 'active',
  primary_metric VARCHAR(120) NOT NULL,
  channels_json JSON NOT NULL,
  actions_json JSON NOT NULL,
  signals_json JSON NOT NULL,
  examples_json JSON NOT NULL,
  connectors_json JSON NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_brains_company FOREIGN KEY (company_id) REFERENCES companies(id),
  UNIQUE KEY uq_ai_brains_company_key (company_id, brain_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
