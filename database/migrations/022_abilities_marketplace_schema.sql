CREATE TABLE IF NOT EXISTS abilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ability_key VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  commercial_name VARCHAR(180) NOT NULL,
  category ENUM('core','base','professional') NOT NULL DEFAULT 'professional',
  description TEXT NULL,
  status ENUM('active','deprecated','hidden') NOT NULL DEFAULT 'active',
  is_core BOOLEAN NOT NULL DEFAULT FALSE,
  is_default_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  sort_order INT NOT NULL DEFAULT 100,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_abilities_category_status (category, status),
  INDEX idx_abilities_default (is_default_enabled, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ability_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ability_id BIGINT UNSIGNED NOT NULL,
  version VARCHAR(40) NOT NULL,
  status ENUM('draft','stable','deprecated') NOT NULL DEFAULT 'stable',
  manifest_json JSON NOT NULL,
  released_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ability_versions_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ability_version (ability_id, version),
  INDEX idx_ability_versions_status (ability_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_abilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  ability_id BIGINT UNSIGNED NOT NULL,
  ability_version_id BIGINT UNSIGNED NULL,
  status ENUM('installed','active','disabled','updatable','deprecated') NOT NULL DEFAULT 'active',
  installed_by BIGINT UNSIGNED NULL,
  installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  activated_at TIMESTAMP NULL,
  disabled_at TIMESTAMP NULL,
  settings_json JSON NULL,
  CONSTRAINT fk_tenant_abilities_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_tenant_abilities_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  CONSTRAINT fk_tenant_abilities_version FOREIGN KEY (ability_version_id) REFERENCES ability_versions(id) ON DELETE SET NULL,
  CONSTRAINT fk_tenant_abilities_installed_by FOREIGN KEY (installed_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_tenant_ability (company_id, ability_id),
  INDEX idx_tenant_abilities_company_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ability_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ability_id BIGINT UNSIGNED NOT NULL,
  permission_key VARCHAR(140) NOT NULL,
  label VARCHAR(160) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ability_permissions_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ability_permission (ability_id, permission_key),
  INDEX idx_ability_permissions_key (permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ability_navigation_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ability_id BIGINT UNSIGNED NOT NULL,
  section VARCHAR(100) NOT NULL,
  label VARCHAR(120) NOT NULL,
  route VARCHAR(180) NOT NULL,
  icon VARCHAR(80) NOT NULL DEFAULT 'bi-circle',
  badge_key VARCHAR(80) NULL,
  permission_key VARCHAR(140) NULL,
  sort_order INT NOT NULL DEFAULT 100,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ability_navigation_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ability_navigation_route (ability_id, section, route),
  INDEX idx_ability_navigation_section (section, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ability_dependencies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ability_id BIGINT UNSIGNED NOT NULL,
  depends_on_ability_id BIGINT UNSIGNED NOT NULL,
  dependency_type ENUM('required','recommended','conflicts') NOT NULL DEFAULT 'required',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ability_dependencies_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  CONSTRAINT fk_ability_dependencies_depends FOREIGN KEY (depends_on_ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ability_dependency (ability_id, depends_on_ability_id, dependency_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ability_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  short_description VARCHAR(255) NOT NULL,
  long_description TEXT NULL,
  pricing_model ENUM('included','addon','custom') NOT NULL DEFAULT 'included',
  monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('draft','published','hidden') NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 100,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_marketplace_items_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE CASCADE,
  INDEX idx_marketplace_items_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ability_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  ability_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  previous_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ability_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
  CONSTRAINT fk_ability_audit_ability FOREIGN KEY (ability_id) REFERENCES abilities(id) ON DELETE SET NULL,
  CONSTRAINT fk_ability_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ability_audit_company (company_id, created_at),
  INDEX idx_ability_audit_ability (ability_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
