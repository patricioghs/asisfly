CREATE TABLE IF NOT EXISTS booking_settings (
  company_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  public_token VARCHAR(80) NOT NULL UNIQUE,
  public_title VARCHAR(180) NOT NULL DEFAULT 'Reserva una hora',
  public_description VARCHAR(500) NULL,
  timezone VARCHAR(80) NOT NULL DEFAULT 'America/Santiago',
  default_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  minimum_notice_hours SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  confirmation_mode ENUM('automatic','manual') NOT NULL DEFAULT 'manual',
  public_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_settings_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_resources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  resource_type ENUM('staff','room','service','equipment','general') NOT NULL DEFAULT 'staff',
  duration_minutes SMALLINT UNSIGNED NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_resources_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_resources_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_booking_resources_company_active (company_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_availability_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_rules_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_rules_resource FOREIGN KEY (resource_id) REFERENCES booking_resources(id) ON DELETE CASCADE,
  INDEX idx_booking_rules_resource_day (company_id, resource_id, day_of_week, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_blocks_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_blocks_resource FOREIGN KEY (resource_id) REFERENCES booking_resources(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_blocks_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_booking_blocks_resource_time (company_id, resource_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(20) NOT NULL UNIQUE,
  company_id BIGINT UNSIGNED NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(180) NOT NULL,
  customer_email VARCHAR(180) NULL,
  customer_phone VARCHAR(60) NULL,
  service_name VARCHAR(180) NULL,
  notes TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('pending','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  source ENUM('public','internal','whatsapp','omnichannel') NOT NULL DEFAULT 'internal',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_appointments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_appointments_resource FOREIGN KEY (resource_id) REFERENCES booking_resources(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_appointments_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_booking_appointments_resource_time (company_id, resource_id, starts_at, ends_at, status),
  INDEX idx_booking_appointments_company_status (company_id, status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO abilities (ability_key, name, commercial_name, category, description, status, is_core, is_default_enabled, sort_order, metadata_json)
VALUES ('smart_booking', 'Smart Booking', 'Agenda y reservas', 'base', 'Disponibilidad, reservas, bloqueos y enlace publico para que clientes reserven una hora.', 'active', FALSE, TRUE, 35, JSON_OBJECT('phase', 'booking_mvp', 'core_workflow', TRUE))
ON DUPLICATE KEY UPDATE
  name = VALUES(name), commercial_name = VALUES(commercial_name), category = VALUES(category), description = VALUES(description),
  status = VALUES(status), is_core = VALUES(is_core), is_default_enabled = VALUES(is_default_enabled), sort_order = VALUES(sort_order), metadata_json = VALUES(metadata_json);

INSERT INTO ability_versions (ability_id, version, status, manifest_json, released_at)
SELECT id, '1.0.0', 'stable', JSON_OBJECT('source', 'smart_booking_mvp'), NOW()
FROM abilities WHERE ability_key = 'smart_booking'
ON DUPLICATE KEY UPDATE status = VALUES(status), manifest_json = VALUES(manifest_json);

INSERT INTO ability_permissions (ability_id, permission_key, label, description)
SELECT id, 'chat.use', 'Usar agenda y reservas', 'Consultar agenda, crear reservas y bloquear disponibilidad.'
FROM abilities WHERE ability_key = 'smart_booking'
ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description);

INSERT INTO ability_navigation_items (ability_id, section, label, route, icon, badge_key, permission_key, sort_order, is_active, metadata_json)
SELECT id, 'Asistente', 'Agenda y reservas', '/bookings', 'bi-calendar-week', NULL, 'chat.use', 25, TRUE, JSON_OBJECT('booking', TRUE)
FROM abilities WHERE ability_key = 'smart_booking'
ON DUPLICATE KEY UPDATE section = VALUES(section), label = VALUES(label), icon = VALUES(icon), permission_key = VALUES(permission_key), sort_order = VALUES(sort_order), is_active = VALUES(is_active), metadata_json = VALUES(metadata_json);

INSERT INTO marketplace_items (ability_id, slug, title, short_description, long_description, pricing_model, monthly_price, currency, status, sort_order, metadata_json)
SELECT id, 'smart_booking', 'Agenda y reservas', 'Disponibilidad, reservas, bloqueos y enlace publico para clientes.', 'Agenda operativa para equipos y clientes: define responsables, horarios, bloqueos, confirmacion automatica o manual y comparte un enlace de reserva.', 'included', 0, 'USD', 'published', 35, JSON_OBJECT('booking', TRUE)
FROM abilities WHERE ability_key = 'smart_booking'
ON DUPLICATE KEY UPDATE title = VALUES(title), short_description = VALUES(short_description), long_description = VALUES(long_description), pricing_model = VALUES(pricing_model), status = VALUES(status), sort_order = VALUES(sort_order), metadata_json = VALUES(metadata_json);

INSERT INTO tenant_abilities (company_id, ability_id, ability_version_id, status, installed_at, activated_at, settings_json)
SELECT c.id, a.id, av.id, 'active', NOW(), NOW(), JSON_OBJECT('booking_mvp', TRUE)
FROM companies c
INNER JOIN abilities a ON a.ability_key = 'smart_booking'
LEFT JOIN ability_versions av ON av.ability_id = a.id AND av.version = '1.0.0'
ON DUPLICATE KEY UPDATE ability_version_id = COALESCE(tenant_abilities.ability_version_id, VALUES(ability_version_id));
