INSERT IGNORE INTO omnichannel_accounts
  (company_id, provider, channel, display_name, external_account_id, webhook_token, status, inbound_enabled, outbound_enabled, requires_approval, brain_key, settings_json)
VALUES
  (1, 'whatsapp_cloud', 'WhatsApp', 'WhatsApp Ventas', 'wa_sales_001', 'demo-wa-token-001', 'sandbox', TRUE, FALSE, TRUE, 'commercial', JSON_OBJECT('send_mode', 'approval_required')),
  (1, 'whatsapp_cloud', 'WhatsApp', 'WhatsApp Soporte', 'wa_support_001', 'demo-wa-token-002', 'sandbox', TRUE, FALSE, TRUE, 'administrative', JSON_OBJECT('send_mode', 'approval_required')),
  (1, 'meta', 'Instagram', 'Instagram Principal', 'ig_main_001', 'demo-ig-token-001', 'sandbox', TRUE, FALSE, TRUE, 'commercial', JSON_OBJECT('send_mode', 'approval_required')),
  (1, 'meta', 'Messenger', 'Messenger Facebook', 'fb_main_001', 'demo-fb-token-001', 'sandbox', TRUE, FALSE, TRUE, 'commercial', JSON_OBJECT('send_mode', 'approval_required')),
  (1, 'gmail', 'Email', 'Gmail Comercial', 'gmail_sales_001', 'demo-gmail-token-001', 'sandbox', TRUE, FALSE, TRUE, 'commercial', JSON_OBJECT('send_mode', 'draft_first')),
  (1, 'gmail', 'Email', 'Gmail Administracion', 'gmail_admin_001', 'demo-gmail-token-002', 'sandbox', TRUE, FALSE, TRUE, 'administrative', JSON_OBJECT('send_mode', 'draft_first')),
  (1, 'imap', 'Email', 'Correo Ventas Corporativo', 'ventas@empresa.demo', 'demo-imap-token-001', 'sandbox', TRUE, FALSE, TRUE, 'commercial', JSON_OBJECT('send_mode', 'draft_first')),
  (1, 'imap', 'Email', 'Correo Gerencia', 'gerencia@empresa.demo', 'demo-imap-token-002', 'sandbox', TRUE, FALSE, TRUE, 'executive', JSON_OBJECT('send_mode', 'draft_first')),
  (1, 'outlook', 'Email', 'Outlook Finanzas', 'outlook_finance_001', 'demo-outlook-token-001', 'sandbox', TRUE, FALSE, TRUE, 'administrative', JSON_OBJECT('send_mode', 'draft_first'))
ON DUPLICATE KEY UPDATE
  display_name = VALUES(display_name),
  external_account_id = VALUES(external_account_id),
  brain_key = VALUES(brain_key),
  settings_json = VALUES(settings_json),
  updated_at = CURRENT_TIMESTAMP;
