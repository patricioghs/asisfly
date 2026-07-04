ALTER TABLE action_center_items
  ADD COLUMN result_summary TEXT NULL AFTER payload_json,
  ADD COLUMN last_transition_at TIMESTAMP NULL AFTER executed_at;

CREATE TABLE IF NOT EXISTS action_center_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  notes TEXT NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_action_events_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_action_events_action FOREIGN KEY (action_id) REFERENCES action_center_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_action_events_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_action_events_action (action_id),
  INDEX idx_action_events_company_date (company_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
