ALTER TABLE companies
  ADD COLUMN trial_ends_at DATETIME NULL AFTER status,
  ADD COLUMN subscription_status ENUM('trialing','active','past_due','cancelled','blocked') NOT NULL DEFAULT 'trialing' AFTER trial_ends_at,
  ADD COLUMN billing_provider VARCHAR(40) NULL AFTER subscription_status,
  ADD COLUMN billing_customer_id VARCHAR(180) NULL AFTER billing_provider;

CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'manual',
  provider_subscription_id VARCHAR(180) NULL,
  status ENUM('trialing','active','past_due','cancelled','blocked') NOT NULL DEFAULT 'trialing',
  current_period_start DATE NULL,
  current_period_end DATE NULL,
  trial_ends_at DATETIME NULL,
  cancel_at_period_end BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscriptions_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
  INDEX idx_subscriptions_company_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'manual',
  plan_from VARCHAR(80) NULL,
  plan_to VARCHAR(80) NULL,
  amount DECIMAL(12,2) NULL,
  currency CHAR(3) NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_billing_events_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_billing_events_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_billing_events_company_date (company_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
