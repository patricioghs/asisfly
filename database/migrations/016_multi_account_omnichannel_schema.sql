ALTER TABLE omnichannel_accounts
  DROP INDEX uq_omni_company_provider_channel,
  ADD COLUMN brain_key VARCHAR(80) NOT NULL DEFAULT 'commercial' AFTER requires_approval,
  ADD COLUMN assigned_user_id BIGINT UNSIGNED NULL AFTER brain_key,
  ADD INDEX idx_omni_company_channel (company_id, channel),
  ADD INDEX idx_omni_company_provider_channel (company_id, provider, channel),
  ADD CONSTRAINT fk_omni_accounts_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id);

ALTER TABLE inbox_conversations
  ADD COLUMN account_id BIGINT UNSIGNED NULL AFTER company_id,
  ADD CONSTRAINT fk_inbox_conversations_account FOREIGN KEY (account_id) REFERENCES omnichannel_accounts(id),
  ADD INDEX idx_inbox_conversations_account_status (company_id, account_id, status);

ALTER TABLE inbox_messages
  ADD COLUMN account_id BIGINT UNSIGNED NULL AFTER company_id,
  ADD CONSTRAINT fk_inbox_messages_account FOREIGN KEY (account_id) REFERENCES omnichannel_accounts(id),
  ADD INDEX idx_inbox_messages_account (company_id, account_id);

UPDATE inbox_conversations c
JOIN omnichannel_accounts a
  ON a.company_id = c.company_id
 AND a.channel = c.channel
 AND (c.provider IS NULL OR c.provider = a.provider)
SET c.account_id = a.id,
    c.provider = a.provider
WHERE c.account_id IS NULL;

UPDATE inbox_messages m
JOIN inbox_conversations c
  ON c.company_id = m.company_id
 AND c.id = m.conversation_id
SET m.account_id = c.account_id,
    m.provider = COALESCE(m.provider, c.provider)
WHERE m.account_id IS NULL;
