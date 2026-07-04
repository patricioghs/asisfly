ALTER TABLE action_center_items
  ADD COLUMN assigned_to BIGINT UNSIGNED NULL AFTER approved_by,
  ADD COLUMN required_permission VARCHAR(120) NULL AFTER requires_approval,
  ADD COLUMN required_role VARCHAR(80) NULL AFTER required_permission,
  ADD COLUMN execution_status ENUM('not_started','queued','running','succeeded','failed','cancelled') NOT NULL DEFAULT 'not_started' AFTER status,
  ADD COLUMN retry_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER execution_status,
  ADD COLUMN max_retries INT UNSIGNED NOT NULL DEFAULT 2 AFTER retry_count,
  ADD COLUMN due_at DATETIME NULL AFTER max_retries,
  ADD COLUMN notified_at TIMESTAMP NULL AFTER due_at,
  ADD CONSTRAINT fk_action_center_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id),
  ADD INDEX idx_action_center_company_due (company_id, status, due_at),
  ADD INDEX idx_action_center_company_risk (company_id, risk_level);

CREATE TABLE IF NOT EXISTS action_center_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_action_comments_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_action_comments_action FOREIGN KEY (action_id) REFERENCES action_center_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_action_comments_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_action_comments_action (action_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_center_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  channel VARCHAR(40) NOT NULL DEFAULT 'in_app',
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('pending','sent','read','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  read_at TIMESTAMP NULL,
  CONSTRAINT fk_action_notifications_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_action_notifications_action FOREIGN KEY (action_id) REFERENCES action_center_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_action_notifications_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_action_notifications_user_status (company_id, user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
