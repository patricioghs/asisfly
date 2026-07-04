CREATE TABLE IF NOT EXISTS social_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  industry VARCHAR(100) NOT NULL,
  name VARCHAR(160) NOT NULL,
  channel VARCHAR(40) NOT NULL,
  objective VARCHAR(140) NOT NULL,
  content_pillar VARCHAR(120) NOT NULL,
  caption_pattern TEXT NOT NULL,
  hashtag_pattern VARCHAR(255) NOT NULL,
  image_pattern TEXT NOT NULL,
  cta_pattern VARCHAR(180) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_social_templates_industry (company_id, industry, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_post_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  post_id BIGINT UNSIGNED NOT NULL,
  impressions INT UNSIGNED NOT NULL DEFAULT 0,
  clicks INT UNSIGNED NOT NULL DEFAULT 0,
  messages INT UNSIGNED NOT NULL DEFAULT 0,
  leads INT UNSIGNED NOT NULL DEFAULT 0,
  sales_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  measured_at DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_social_metrics_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_social_metrics_post FOREIGN KEY (post_id) REFERENCES social_posts(id) ON DELETE CASCADE,
  UNIQUE KEY uq_social_metrics_post_date (post_id, measured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE social_posts
  ADD COLUMN campaign_id BIGINT UNSIGNED NULL AFTER company_id,
  ADD COLUMN template_id BIGINT UNSIGNED NULL AFTER campaign_id,
  ADD COLUMN industry VARCHAR(100) NULL AFTER template_id,
  ADD COLUMN content_pillar VARCHAR(120) NULL AFTER objective,
  ADD COLUMN publish_notes TEXT NULL AFTER season,
  ADD COLUMN published_at TIMESTAMP NULL AFTER publish_notes,
  ADD CONSTRAINT fk_social_posts_campaign FOREIGN KEY (campaign_id) REFERENCES social_campaigns(id),
  ADD CONSTRAINT fk_social_posts_template FOREIGN KEY (template_id) REFERENCES social_templates(id);
