CREATE TABLE IF NOT EXISTS social_posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(40) NOT NULL,
  objective VARCHAR(140) NOT NULL,
  status ENUM('draft','approved','scheduled','published') NOT NULL DEFAULT 'draft',
  post_text TEXT NOT NULL,
  image_idea TEXT NOT NULL,
  hashtags VARCHAR(255) NOT NULL,
  cta VARCHAR(180) NOT NULL,
  recommended_time CHAR(5) NOT NULL,
  scheduled_at DATETIME NULL,
  product_focus VARCHAR(180) NULL,
  season VARCHAR(120) NULL,
  source_brain VARCHAR(80) NOT NULL DEFAULT 'commercial',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_social_posts_company FOREIGN KEY (company_id) REFERENCES companies(id),
  INDEX idx_social_posts_company_status (company_id, status),
  INDEX idx_social_posts_company_schedule (company_id, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  goal VARCHAR(180) NOT NULL,
  season VARCHAR(120) NULL,
  channels_json JSON NOT NULL,
  status ENUM('draft','active','paused','finished') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_social_campaigns_company FOREIGN KEY (company_id) REFERENCES companies(id),
  INDEX idx_social_campaigns_company_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
