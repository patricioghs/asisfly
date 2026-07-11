INSERT IGNORE INTO ai_channel_settings
  (company_id, channel, mode, min_confidence, require_approval_for_sensitive, auto_reply_schedule, status)
SELECT id, 'all', 'manual', 75, TRUE, NULL, 'active'
FROM companies;

INSERT IGNORE INTO ai_channel_settings
  (company_id, channel, mode, min_confidence, require_approval_for_sensitive, auto_reply_schedule, status)
SELECT id, 'email', 'manual', 78, TRUE, NULL, 'active'
FROM companies;

INSERT IGNORE INTO ai_channel_settings
  (company_id, channel, mode, min_confidence, require_approval_for_sensitive, auto_reply_schedule, status)
SELECT id, 'whatsapp', 'manual', 80, TRUE, NULL, 'active'
FROM companies;

INSERT IGNORE INTO ai_channel_settings
  (company_id, channel, mode, min_confidence, require_approval_for_sensitive, auto_reply_schedule, status)
SELECT id, 'instagram', 'manual', 80, TRUE, NULL, 'active'
FROM companies;

INSERT IGNORE INTO ai_channel_settings
  (company_id, channel, mode, min_confidence, require_approval_for_sensitive, auto_reply_schedule, status)
SELECT id, 'facebook', 'manual', 80, TRUE, NULL, 'active'
FROM companies;
