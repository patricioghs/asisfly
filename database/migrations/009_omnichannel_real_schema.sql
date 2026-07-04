CREATE TABLE IF NOT EXISTS omnichannel_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(80) NOT NULL,
  channel VARCHAR(40) NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  external_account_id VARCHAR(180) NULL,
  webhook_token VARCHAR(120) NOT NULL,
  status ENUM('simulated','sandbox','connected','disabled','error') NOT NULL DEFAULT 'sandbox',
  inbound_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  outbound_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  requires_approval BOOLEAN NOT NULL DEFAULT TRUE,
  settings_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_omni_accounts_company FOREIGN KEY (company_id) REFERENCES companies(id),
  UNIQUE KEY uq_omni_company_provider_channel (company_id, provider, channel),
  UNIQUE KEY uq_omni_webhook_token (webhook_token),
  INDEX idx_omni_company_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS omnichannel_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  provider VARCHAR(80) NOT NULL,
  channel VARCHAR(40) NOT NULL,
  external_message_id VARCHAR(180) NULL,
  direction ENUM('inbound','outbound') NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  status ENUM('received','queued','sent','failed','ignored') NOT NULL DEFAULT 'received',
  payload_json JSON NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_omni_events_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_omni_events_account FOREIGN KEY (account_id) REFERENCES omnichannel_accounts(id),
  CONSTRAINT fk_omni_events_conversation FOREIGN KEY (conversation_id) REFERENCES inbox_conversations(id),
  INDEX idx_omni_events_company_channel (company_id, channel, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inbox_conversations
  ADD COLUMN provider VARCHAR(80) NULL AFTER channel,
  ADD COLUMN assigned_to BIGINT UNSIGNED NULL AFTER source_brain,
  ADD COLUMN last_inbound_at TIMESTAMP NULL AFTER assigned_to,
  ADD COLUMN last_outbound_at TIMESTAMP NULL AFTER last_inbound_at,
  ADD INDEX idx_inbox_conversations_external (company_id, channel, external_id);

ALTER TABLE inbox_messages
  ADD COLUMN provider VARCHAR(80) NULL AFTER conversation_id,
  ADD COLUMN external_message_id VARCHAR(180) NULL AFTER provider,
  ADD COLUMN sent_at TIMESTAMP NULL AFTER created_at,
  ADD INDEX idx_inbox_messages_external (company_id, external_message_id);
