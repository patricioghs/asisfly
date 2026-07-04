ALTER TABLE crm_customers
  ADD COLUMN owner_id BIGINT UNSIGNED NULL AFTER company_id,
  ADD COLUMN source VARCHAR(80) NULL AFTER phone,
  ADD COLUMN temperature ENUM('hot','warm','cold') NOT NULL DEFAULT 'warm' AFTER source,
  ADD COLUMN last_activity_at TIMESTAMP NULL AFTER currency,
  ADD COLUMN next_follow_up_at TIMESTAMP NULL AFTER last_activity_at,
  ADD COLUMN lost_reason VARCHAR(180) NULL AFTER next_follow_up_at,
  ADD CONSTRAINT fk_crm_owner FOREIGN KEY (owner_id) REFERENCES users(id),
  ADD INDEX idx_crm_company_temperature (company_id, temperature),
  ADD INDEX idx_crm_company_followup (company_id, next_follow_up_at);

CREATE TABLE IF NOT EXISTS crm_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(180) NULL,
  phone VARCHAR(60) NULL,
  role VARCHAR(120) NULL,
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_contacts_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_contacts_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE,
  INDEX idx_crm_contacts_customer (company_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_opportunities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  stage ENUM('nuevo','calificado','propuesta','negociacion','ganado','perdido') NOT NULL DEFAULT 'nuevo',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  probability TINYINT UNSIGNED NOT NULL DEFAULT 30,
  expected_close_date DATE NULL,
  source VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_opp_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_opp_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE,
  INDEX idx_crm_opp_company_stage (company_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  task_type ENUM('follow_up','call','email','meeting','quote','todo') NOT NULL DEFAULT 'follow_up',
  due_at DATETIME NULL,
  status ENUM('pending','done','cancelled') NOT NULL DEFAULT 'pending',
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  CONSTRAINT fk_crm_tasks_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_tasks_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_tasks_user FOREIGN KEY (assigned_to) REFERENCES users(id),
  INDEX idx_crm_tasks_company_due (company_id, status, due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_notes_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_notes_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_notes_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_crm_notes_customer (company_id, customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  activity_type VARCHAR(80) NOT NULL,
  summary VARCHAR(220) NOT NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_activities_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_activities_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_activities_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_crm_activities_customer (company_id, customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
