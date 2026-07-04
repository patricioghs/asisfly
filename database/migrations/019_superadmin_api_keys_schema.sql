ALTER TABLE ai_provider_settings
  ADD COLUMN encrypted_api_key TEXT NULL AFTER api_key_env,
  ADD COLUMN api_key_last4 VARCHAR(12) NULL AFTER encrypted_api_key,
  ADD COLUMN api_key_updated_at TIMESTAMP NULL AFTER api_key_last4;
