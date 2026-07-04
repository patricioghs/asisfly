CREATE TABLE IF NOT EXISTS inbox_conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  channel VARCHAR(40) NOT NULL,
  external_id VARCHAR(120) NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_handle VARCHAR(160) NULL,
  subject VARCHAR(180) NOT NULL,
  status ENUM('new','open','pending_approval','answered','closed') NOT NULL DEFAULT 'new',
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  source_brain VARCHAR(80) NOT NULL DEFAULT 'commercial',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inbox_conversations_company FOREIGN KEY (company_id) REFERENCES companies(id),
  INDEX idx_inbox_conversations_company_status (company_id, status),
  INDEX idx_inbox_conversations_company_channel (company_id, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inbox_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('inbound','outbound') NOT NULL,
  sender_name VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  ai_generated BOOLEAN NOT NULL DEFAULT FALSE,
  status ENUM('received','draft','approved','sent','failed') NOT NULL DEFAULT 'received',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inbox_messages_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_inbox_messages_conversation FOREIGN KEY (conversation_id) REFERENCES inbox_conversations(id) ON DELETE CASCADE,
  INDEX idx_inbox_messages_conversation (conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
