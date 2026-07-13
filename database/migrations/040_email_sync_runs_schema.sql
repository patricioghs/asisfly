CREATE TABLE IF NOT EXISTS email_sync_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  status ENUM('running','success','warning','failed','skipped') NOT NULL DEFAULT 'running',
  imported_count INT UNSIGNED NOT NULL DEFAULT 0,
  message VARCHAR(1000) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_sync_runs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_sync_runs_account FOREIGN KEY (account_id) REFERENCES omnichannel_accounts(id) ON DELETE CASCADE,
  INDEX idx_email_sync_runs_account (company_id, account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
