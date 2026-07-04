INSERT INTO plans (name, monthly_price, limits_json) VALUES
('Starter', 29.00, JSON_OBJECT('users', 3, 'documents', 100, 'ai_messages', 1000, 'tokens', 500000, 'integrations', 2, 'automations', 5, 'storage_gb', 5)),
('Pro', 79.00, JSON_OBJECT('users', 10, 'documents', 1000, 'ai_messages', 5000, 'tokens', 3000000, 'integrations', 6, 'automations', 25, 'storage_gb', 50)),
('Business', 199.00, JSON_OBJECT('users', 30, 'documents', 5000, 'ai_messages', 20000, 'tokens', 15000000, 'integrations', 15, 'automations', 100, 'storage_gb', 250)),
('Enterprise', 0.00, JSON_OBJECT('users', -1, 'documents', -1, 'ai_messages', -1, 'tokens', -1, 'integrations', -1, 'automations', -1, 'storage_gb', -1));

INSERT INTO roles (name, permissions_json) VALUES
('Superadmin', JSON_ARRAY('*')),
('Dueno de empresa', JSON_ARRAY('company.manage','users.manage','assistant.manage','billing.manage','crm.manage','quotes.manage','documents.manage','chat.use','dashboard.view')),
('Administrador', JSON_ARRAY('users.manage','assistant.manage','crm.manage','quotes.manage','documents.manage','chat.use','dashboard.view')),
('Ejecutivo', JSON_ARRAY('crm.manage','quotes.manage','chat.use','dashboard.view')),
('Analista', JSON_ARRAY('reports.view','documents.manage','chat.use','dashboard.view')),
('Solo lectura', JSON_ARRAY('dashboard.view','reports.view'));

INSERT INTO companies (name, legal_name, country, currency, timezone, locale, plan_id, status)
VALUES ('Andes Demo SpA', 'Andes Demo SpA', 'Chile', 'CLP', 'America/Santiago', 'es_CL', 3, 'active');

INSERT INTO users (company_id, role_id, name, email, password_hash, locale, timezone)
VALUES (1, 1, 'Admin AsisFly', 'admin@asisfly.ai', '$2y$10$sw71bYAodbruIl52Q1DjDe/wcAC.iQ8FbJyOP6HnLHxwUES80fLs2', 'es_CL', 'America/Santiago');

INSERT INTO assistant_settings (company_id, assistant_name, tone, language, country, currency, work_hours, signature, rules, forbidden_words, required_phrases, human_escalation)
VALUES (1, 'AsisFly', 'Cercano y ejecutivo', 'Espanol latino', 'Chile', 'CLP', 'Lunes a viernes 09:00 a 18:00', 'Equipo AsisFly', 'Pedir aprobacion antes de enviar correos, cotizaciones o mensajes comerciales.', '', 'Quedo atento/a', 'Reclamos, descuentos especiales, riesgos legales o clientes molestos.');

INSERT INTO crm_customers (company_id, name, contact_name, email, phone, stage, estimated_value, currency) VALUES
(1, 'Comercial Pacifico', 'Valentina Rojas', 'valentina@pacifico.test', '+56911111111', 'Negociacion', 2450000, 'CLP'),
(1, 'Grupo Norte', 'Diego Morales', 'diego@gruponorte.test', '+56922222222', 'Propuesta enviada', 980000, 'CLP');

INSERT INTO integrations (company_id, provider, status, settings_json) VALUES
(1, 'gmail', 'simulated', JSON_OBJECT('scopes', JSON_ARRAY('read','draft','labels'))),
(1, 'outlook', 'simulated', JSON_OBJECT('scopes', JSON_ARRAY('mail','calendar'))),
(1, 'google_calendar', 'simulated', JSON_OBJECT('scopes', JSON_ARRAY('events','availability'))),
(1, 'whatsapp_business', 'sandbox', JSON_OBJECT('requires_human_approval', true)),
(1, 'instagram', 'simulated', JSON_OBJECT('dm_enabled', true)),
(1, 'facebook', 'simulated', JSON_OBJECT('messenger_enabled', true)),
(1, 'telegram', 'simulated', JSON_OBJECT('bot_enabled', true));

INSERT INTO automation_rules (company_id, name, trigger_json, action_json, requires_approval) VALUES
(1, 'Correo solicita cotizacion', JSON_OBJECT('event','email.received','contains','cotizacion'), JSON_OBJECT('create','opportunity'), true),
(1, 'Cliente sin respuesta 3 dias', JSON_OBJECT('event','crm.customer.inactive','days',3), JSON_OBJECT('create','task'), false);
