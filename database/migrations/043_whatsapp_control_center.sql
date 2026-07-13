CREATE TABLE IF NOT EXISTS whatsapp_control_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  account_id BIGINT UNSIGNED NULL,
  resource_id BIGINT UNSIGNED NULL,
  display_name VARCHAR(180) NOT NULL,
  phone_number VARCHAR(32) NOT NULL,
  control_role ENUM('owner','manager','schedule_operator','sales','observer') NOT NULL DEFAULT 'observer',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_whatsapp_control_user_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_whatsapp_control_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_whatsapp_control_user_account FOREIGN KEY (account_id) REFERENCES omnichannel_accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_whatsapp_control_user_resource FOREIGN KEY (resource_id) REFERENCES booking_resources(id) ON DELETE SET NULL,
  UNIQUE KEY uq_whatsapp_control_user_phone (company_id, phone_number),
  INDEX idx_whatsapp_control_user_lookup (company_id, account_id, phone_number, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_control_commands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  controller_id BIGINT UNSIGNED NOT NULL,
  command_text TEXT NOT NULL,
  command_type VARCHAR(80) NOT NULL,
  payload_json JSON NULL,
  confirmation_code VARCHAR(20) NULL,
  status ENUM('received','pending_confirmation','executed','cancelled','failed') NOT NULL DEFAULT 'received',
  result_message VARCHAR(1000) NULL,
  executed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_whatsapp_control_command_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_whatsapp_control_command_account FOREIGN KEY (account_id) REFERENCES omnichannel_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_whatsapp_control_command_controller FOREIGN KEY (controller_id) REFERENCES whatsapp_control_users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_whatsapp_control_confirmation (company_id, confirmation_code),
  INDEX idx_whatsapp_control_command_recent (company_id, controller_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
