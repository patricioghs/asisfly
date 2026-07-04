CREATE TABLE companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  legal_name VARCHAR(220) NULL,
  country VARCHAR(80) NOT NULL,
  currency CHAR(3) NOT NULL,
  timezone VARCHAR(80) NOT NULL,
  locale VARCHAR(20) NOT NULL DEFAULT 'es_CL',
  plan_id BIGINT UNSIGNED NULL,
  status ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  limits_json JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE companies ADD CONSTRAINT fk_companies_plan FOREIGN KEY (plan_id) REFERENCES plans(id);

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  permissions_json JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  locale VARCHAR(20) NOT NULL DEFAULT 'es_CL',
  timezone VARCHAR(80) NOT NULL DEFAULT 'America/Santiago',
  status ENUM('active','invited','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assistant_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL UNIQUE,
  assistant_name VARCHAR(120) NOT NULL DEFAULT 'AsisFly',
  tone VARCHAR(120) NOT NULL,
  language VARCHAR(60) NOT NULL,
  country VARCHAR(80) NOT NULL,
  currency CHAR(3) NOT NULL,
  work_hours VARCHAR(180) NOT NULL,
  signature TEXT NULL,
  rules TEXT NULL,
  forbidden_words TEXT NULL,
  required_phrases TEXT NULL,
  human_escalation TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assistant_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  storage_path VARCHAR(255) NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  processing_status ENUM('pending','processing','processed','failed') NOT NULL DEFAULT 'pending',
  summary TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_documents_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id),
  INDEX idx_documents_company_status (company_id, processing_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE memory_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  title VARCHAR(220) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  embedding_provider VARCHAR(80) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_memory_company FOREIGN KEY (company_id) REFERENCES companies(id),
  FULLTEXT idx_memory_content (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crm_customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  contact_name VARCHAR(160) NULL,
  email VARCHAR(180) NULL,
  phone VARCHAR(60) NULL,
  stage VARCHAR(80) NOT NULL DEFAULT 'Nuevo',
  estimated_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_crm_company FOREIGN KEY (company_id) REFERENCES companies(id),
  INDEX idx_crm_company_stage (company_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  quote_number VARCHAR(60) NOT NULL,
  customer_name VARCHAR(180) NULL,
  status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quotes_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_quotes_customer FOREIGN KEY (customer_id) REFERENCES crm_customers(id),
  UNIQUE KEY uq_quote_company_number (company_id, quote_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(80) NOT NULL,
  status ENUM('simulated','sandbox','connected','disabled','error') NOT NULL DEFAULT 'simulated',
  encrypted_credentials TEXT NULL,
  settings_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_integrations_company FOREIGN KEY (company_id) REFERENCES companies(id),
  UNIQUE KEY uq_integrations_company_provider (company_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_usage_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  module VARCHAR(80) NOT NULL,
  provider VARCHAR(80) NOT NULL,
  model VARCHAR(120) NOT NULL,
  prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_cost DECIMAL(12,6) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_usage_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_ai_usage_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_ai_usage_company_date (company_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  module VARCHAR(80) NOT NULL,
  ip_address VARCHAR(64) NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_audit_company_module (company_id, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE automation_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  trigger_json JSON NOT NULL,
  action_json JSON NOT NULL,
  requires_approval BOOLEAN NOT NULL DEFAULT TRUE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_automation_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
