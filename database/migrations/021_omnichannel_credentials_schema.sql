ALTER TABLE omnichannel_accounts
  ADD COLUMN encrypted_credentials TEXT NULL AFTER settings_json,
  ADD COLUMN credentials_last4 VARCHAR(24) NULL AFTER encrypted_credentials,
  ADD COLUMN credentials_updated_at TIMESTAMP NULL AFTER credentials_last4;
