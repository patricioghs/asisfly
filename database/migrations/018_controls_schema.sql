CREATE TABLE IF NOT EXISTS business_controls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  owner_id BIGINT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  control_key VARCHAR(120) NOT NULL,
  objective TEXT NOT NULL,
  category ENUM('financial','commercial','operations','administrative','inventory','custom') NOT NULL DEFAULT 'custom',
  brain_key VARCHAR(80) NOT NULL DEFAULT 'executive',
  status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'active',
  frequency ENUM('daily','weekly','monthly','on_demand') NOT NULL DEFAULT 'weekly',
  source_type ENUM('manual','spreadsheet','documents','email','integration','mixed') NOT NULL DEFAULT 'manual',
  rules_json JSON NULL,
  metrics_json JSON NULL,
  next_review_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_business_controls_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_business_controls_owner FOREIGN KEY (owner_id) REFERENCES users(id),
  UNIQUE KEY uq_business_controls_company_key (company_id, control_key),
  INDEX idx_business_controls_company_status (company_id, status),
  INDEX idx_business_controls_company_category (company_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_control_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  control_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  entry_type VARCHAR(80) NOT NULL DEFAULT 'note',
  amount DECIMAL(14,2) NULL,
  currency CHAR(3) NULL,
  period_label VARCHAR(80) NULL,
  status ENUM('pending','reviewed','ignored','resolved') NOT NULL DEFAULT 'pending',
  data_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_control_entries_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_control_entries_control FOREIGN KEY (control_id) REFERENCES business_controls(id) ON DELETE CASCADE,
  CONSTRAINT fk_control_entries_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_control_entries_control (company_id, control_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_control_alerts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  control_id BIGINT UNSIGNED NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  due_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  CONSTRAINT fk_control_alerts_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_control_alerts_control FOREIGN KEY (control_id) REFERENCES business_controls(id) ON DELETE CASCADE,
  INDEX idx_control_alerts_company_status (company_id, status, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_control_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  control_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  summary TEXT NOT NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_control_events_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_control_events_control FOREIGN KEY (control_id) REFERENCES business_controls(id) ON DELETE CASCADE,
  CONSTRAINT fk_control_events_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_control_events_control (company_id, control_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
