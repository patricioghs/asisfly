CREATE TABLE IF NOT EXISTS crm_task_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  comment TEXT NOT NULL,
  mentions_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_task_comments_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_task_comments_task FOREIGN KEY (task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_task_comments_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_crm_task_comments_task (company_id, task_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_task_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  summary TEXT NOT NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_task_events_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_task_events_task FOREIGN KEY (task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_task_events_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_crm_task_events_task (company_id, task_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_task_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('pending','sent','read','failed') NOT NULL DEFAULT 'sent',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  CONSTRAINT fk_crm_task_notifications_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_crm_task_notifications_task FOREIGN KEY (task_id) REFERENCES crm_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_crm_task_notifications_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_crm_task_notifications_user (company_id, user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
