INSERT INTO abilities (ability_key, name, commercial_name, category, description, status, is_core, is_default_enabled, sort_order, metadata_json) VALUES
('core_workspace', 'Core Workspace', 'Operacion diaria', 'core', 'Dashboard, Mi dia, Bandeja de trabajo y Notificaciones.', 'active', TRUE, TRUE, 10, JSON_OBJECT('phase','compatibility')),
('core_ai_assistant', 'Core AI Assistant', 'Asistente inteligente', 'core', 'Chat IA, tareas, automatizaciones, aprobaciones, controles y reglas.', 'active', TRUE, TRUE, 20, JSON_OBJECT('phase','compatibility')),
('core_omnichannel', 'Core Omnichannel', 'Comunicacion inteligente', 'base', 'Bandeja omnicanal y cuentas conectadas.', 'active', TRUE, FALSE, 30, JSON_OBJECT('phase','compatibility','installable',true)),
('core_memory_documents', 'Core Memory Documents', 'Memoria empresarial', 'base', 'Documentos, lectura de archivos, base de conocimiento, catalogos, manuales y entrenamiento.', 'active', TRUE, TRUE, 40, JSON_OBJECT('phase','compatibility')),
('core_integrations', 'Core Integrations', 'Integraciones base', 'core', 'Integraciones, APIs y conectores base.', 'active', TRUE, TRUE, 50, JSON_OBJECT('phase','compatibility')),
('core_company_admin', 'Core Company Admin', 'Administracion de empresa', 'core', 'Empresa, usuarios, roles, plan y configuracion.', 'active', TRUE, TRUE, 60, JSON_OBJECT('phase','compatibility')),
('core_superadmin', 'Core Superadmin', 'Administracion global', 'core', 'Panel Superadmin para soporte, planes, empresas, IA y auditoria.', 'active', TRUE, TRUE, 70, JSON_OBJECT('phase','compatibility','requires','*')),
('crm', 'CRM', 'CRM comercial', 'professional', 'Clientes, contactos, oportunidades, tareas y seguimiento comercial.', 'active', FALSE, FALSE, 100, JSON_OBJECT('phase','compatibility','installable',true)),
('quotes', 'Quotes', 'Cotizaciones', 'professional', 'Cotizaciones, productos, impuestos, PDF y seguimiento.', 'active', FALSE, FALSE, 110, JSON_OBJECT('phase','compatibility','installable',true)),
('social_marketing', 'Social Marketing', 'Asisti Social', 'professional', 'Calendario editorial, copies, campanas y publicaciones.', 'active', FALSE, FALSE, 120, JSON_OBJECT('phase','compatibility','installable',true)),
('intelligence', 'Business Intelligence', 'Inteligencia empresarial', 'professional', 'Reportes, analytics, dashboards, Excel, indicadores y KPIs.', 'active', FALSE, FALSE, 130, JSON_OBJECT('phase','compatibility','installable',true)),
('ai_brains', 'AI Brains', 'Cerebros IA', 'base', 'Cerebros comercial, administrativo, analitico, operacional y ejecutivo.', 'active', TRUE, TRUE, 140, JSON_OBJECT('phase','compatibility'))
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  commercial_name = VALUES(commercial_name),
  category = VALUES(category),
  description = VALUES(description),
  status = VALUES(status),
  is_core = VALUES(is_core),
  is_default_enabled = VALUES(is_default_enabled),
  sort_order = VALUES(sort_order),
  metadata_json = VALUES(metadata_json);

INSERT INTO ability_versions (ability_id, version, status, manifest_json, released_at)
SELECT id, '1.0.0', 'stable', JSON_OBJECT('compatibility', TRUE, 'source', 'current-platform'), NOW()
FROM abilities
WHERE ability_key IN ('core_workspace','core_ai_assistant','core_omnichannel','core_memory_documents','core_integrations','core_company_admin','core_superadmin','crm','quotes','social_marketing','intelligence','ai_brains')
ON DUPLICATE KEY UPDATE
  status = VALUES(status),
  manifest_json = VALUES(manifest_json);

INSERT INTO ability_permissions (ability_id, permission_key, label, description)
SELECT a.id, p.permission_key, p.label, p.description
FROM abilities a
JOIN (
  SELECT 'core_workspace' ability_key, 'dashboard.view' permission_key, 'Ver dashboard' label, 'Acceso a dashboard y vistas de inicio.' description
  UNION ALL SELECT 'core_ai_assistant', 'chat.use', 'Usar asistente IA', 'Acceso al chat, acciones, tareas y automatizaciones.'
  UNION ALL SELECT 'core_memory_documents', 'documents.manage', 'Gestionar documentos', 'Subir, leer y administrar documentos.'
  UNION ALL SELECT 'core_company_admin', 'company.manage', 'Gestionar empresa', 'Editar configuracion general de empresa.'
  UNION ALL SELECT 'core_company_admin', 'users.manage', 'Gestionar usuarios', 'Crear y administrar usuarios.'
  UNION ALL SELECT 'core_company_admin', 'billing.manage', 'Gestionar facturacion', 'Administrar plan y facturacion.'
  UNION ALL SELECT 'core_company_admin', 'assistant.manage', 'Gestionar reglas', 'Editar personalidad, reglas y configuracion del asistente.'
  UNION ALL SELECT 'crm', 'crm.manage', 'Gestionar CRM', 'Administrar clientes, contactos y oportunidades.'
  UNION ALL SELECT 'quotes', 'quotes.manage', 'Gestionar cotizaciones', 'Crear y administrar cotizaciones.'
  UNION ALL SELECT 'social_marketing', 'chat.use', 'Usar Asisti Social', 'Generar ideas, copies y calendario editorial.'
  UNION ALL SELECT 'intelligence', 'dashboard.view', 'Ver inteligencia', 'Acceder a reportes, analytics y KPIs.'
  UNION ALL SELECT 'core_superadmin', '*', 'Superadmin', 'Acceso global de administracion.'
) p ON p.ability_key = a.ability_key
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  description = VALUES(description);

INSERT INTO ability_navigation_items (ability_id, section, label, route, icon, badge_key, permission_key, sort_order, is_active, metadata_json)
SELECT a.id, n.section, n.label, n.route, n.icon, NULL, n.permission_key, n.sort_order, TRUE, JSON_OBJECT('compatibility', TRUE)
FROM abilities a
JOIN (
  SELECT 'core_workspace' ability_key, 'Inicio' section, 'Dashboard' label, '/dashboard' route, 'bi-grid-1x2' icon, 'dashboard.view' permission_key, 10 sort_order
  UNION ALL SELECT 'core_workspace', 'Inicio', 'Mi dia', '/my-day', 'bi-calendar2-check', 'dashboard.view', 20
  UNION ALL SELECT 'core_workspace', 'Inicio', 'Bandeja de trabajo', '/workbench', 'bi-briefcase', 'dashboard.view', 30
  UNION ALL SELECT 'core_workspace', 'Inicio', 'Notificaciones', '/notifications', 'bi-bell', 'dashboard.view', 40
  UNION ALL SELECT 'core_ai_assistant', 'Asistente', 'Chat IA', '/chat', 'bi-stars', 'chat.use', 10
  UNION ALL SELECT 'core_ai_assistant', 'Asistente', 'Tareas', '/tasks', 'bi-list-task', 'chat.use', 20
  UNION ALL SELECT 'core_ai_assistant', 'Asistente', 'Automatizaciones', '/automations', 'bi-magic', 'chat.use', 30
  UNION ALL SELECT 'core_ai_assistant', 'Asistente', 'Aprobaciones', '/actions', 'bi-check2-square', 'chat.use', 40
  UNION ALL SELECT 'core_ai_assistant', 'Asistente', 'Controles', '/controls', 'bi-sliders', 'chat.use', 50
  UNION ALL SELECT 'core_memory_documents', 'Asistente', 'Memoria', '/documents', 'bi-database-check', 'documents.manage', 60
  UNION ALL SELECT 'core_ai_assistant', 'Asistente', 'Reglas', '/assistant', 'bi-sliders2', 'assistant.manage', 70
  UNION ALL SELECT 'core_omnichannel', 'Comunicacion', 'Omnicanal', '/inbox', 'bi-inboxes', 'chat.use', 10
  UNION ALL SELECT 'social_marketing', 'Comunicacion', 'Asisti Social', '/social', 'bi-megaphone', 'chat.use', 20
  UNION ALL SELECT 'crm', 'Comercial', 'CRM', '/crm', 'bi-people', 'crm.manage', 10
  UNION ALL SELECT 'quotes', 'Comercial', 'Cotizaciones', '/quotes', 'bi-file-earmark-text', 'quotes.manage', 20
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'Reportes', '/reports', 'bi-clipboard-data', 'dashboard.view', 10
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'Analytics', '/analytics', 'bi-graph-up-arrow', 'dashboard.view', 20
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'Dashboards', '/dashboards', 'bi-columns-gap', 'dashboard.view', 30
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'Documentos', '/intelligence-documents', 'bi-file-earmark-text', 'dashboard.view', 40
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'Excel', '/excel', 'bi-file-earmark-spreadsheet', 'dashboard.view', 50
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'Indicadores', '/indicators', 'bi-bullseye', 'dashboard.view', 60
  UNION ALL SELECT 'intelligence', 'Inteligencia', 'KPIs', '/kpis', 'bi-speedometer2', 'dashboard.view', 70
  UNION ALL SELECT 'ai_brains', 'Inteligencia', 'Cerebros IA', '/brains', 'bi-diagram-3', 'chat.use', 80
  UNION ALL SELECT 'core_memory_documents', 'Conocimiento', 'Base de conocimiento', '/knowledge-base', 'bi-book', 'documents.manage', 10
  UNION ALL SELECT 'core_memory_documents', 'Conocimiento', 'Documentos', '/documents', 'bi-file-earmark', 'documents.manage', 20
  UNION ALL SELECT 'core_memory_documents', 'Conocimiento', 'Catalogos', '/catalogs', 'bi-folder2-open', 'documents.manage', 30
  UNION ALL SELECT 'core_memory_documents', 'Conocimiento', 'Manuales', '/manuals', 'bi-journal-text', 'documents.manage', 40
  UNION ALL SELECT 'core_memory_documents', 'Conocimiento', 'Entrenamiento', '/training', 'bi-mortarboard', 'documents.manage', 50
  UNION ALL SELECT 'core_integrations', 'Integraciones', 'Integraciones', '/integrations', 'bi-diagram-3', NULL, 10
  UNION ALL SELECT 'core_omnichannel', 'Integraciones', 'Cuentas conectadas', '/integrations/accounts', 'bi-plug', NULL, 20
  UNION ALL SELECT 'core_integrations', 'Integraciones', 'APIs', '/apis', 'bi-code-slash', NULL, 30
  UNION ALL SELECT 'core_company_admin', 'Empresa', 'Empresa', '/company', 'bi-building', 'company.manage', 10
  UNION ALL SELECT 'core_company_admin', 'Empresa', 'Usuarios', '/users', 'bi-people', 'users.manage', 20
  UNION ALL SELECT 'core_company_admin', 'Empresa', 'Roles', '/roles', 'bi-shield-lock', 'users.manage', 30
  UNION ALL SELECT 'core_company_admin', 'Empresa', 'Plan y facturacion', '/billing', 'bi-credit-card', 'billing.manage', 40
  UNION ALL SELECT 'core_company_admin', 'Empresa', 'Marketplace', '/marketplace', 'bi-boxes', 'billing.manage', 45
  UNION ALL SELECT 'core_company_admin', 'Empresa', 'Configuracion', '/company-settings', 'bi-gear', 'company.manage', 50
  UNION ALL SELECT 'core_superadmin', 'Administracion (Superadmin)', 'Empresas', '/admin/companies', 'bi-buildings', '*', 10
  UNION ALL SELECT 'core_superadmin', 'Administracion (Superadmin)', 'Planes', '/admin/plans', 'bi-check2-square', '*', 20
  UNION ALL SELECT 'core_superadmin', 'Administracion (Superadmin)', 'IA y tokens', '/admin/ai-tokens', 'bi-cpu', '*', 30
  UNION ALL SELECT 'core_superadmin', 'Administracion (Superadmin)', 'Auditoria y logs', '/admin/audit-logs', 'bi-file-lock', '*', 40
  UNION ALL SELECT 'core_superadmin', 'Administracion (Superadmin)', 'Estado del sistema', '/admin/system-status', 'bi-record-circle', '*', 50
) n ON n.ability_key = a.ability_key
ON DUPLICATE KEY UPDATE
  section = VALUES(section),
  label = VALUES(label),
  icon = VALUES(icon),
  permission_key = VALUES(permission_key),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  metadata_json = VALUES(metadata_json);

INSERT INTO ability_dependencies (ability_id, depends_on_ability_id, dependency_type)
SELECT child.id, parent.id, d.dependency_type
FROM abilities child
JOIN (
  SELECT 'crm' ability_key, 'core_workspace' depends_on, 'required' dependency_type
  UNION ALL SELECT 'quotes', 'crm', 'recommended'
  UNION ALL SELECT 'quotes', 'core_workspace', 'required'
  UNION ALL SELECT 'social_marketing', 'core_ai_assistant', 'required'
  UNION ALL SELECT 'intelligence', 'core_memory_documents', 'recommended'
  UNION ALL SELECT 'core_omnichannel', 'core_integrations', 'recommended'
) d ON d.ability_key = child.ability_key
JOIN abilities parent ON parent.ability_key = d.depends_on
ON DUPLICATE KEY UPDATE dependency_type = VALUES(dependency_type);

INSERT INTO marketplace_items (ability_id, slug, title, short_description, long_description, pricing_model, monthly_price, currency, status, sort_order, metadata_json)
SELECT a.id, a.ability_key, a.commercial_name, COALESCE(a.description, a.commercial_name), a.description, IF(a.category IN ('core','base'), 'included', 'addon'), 0, 'USD', 'published', a.sort_order, JSON_OBJECT('compatibility', TRUE)
FROM abilities a
WHERE a.ability_key IN ('core_workspace','core_ai_assistant','core_omnichannel','core_memory_documents','core_integrations','core_company_admin','core_superadmin','crm','quotes','social_marketing','intelligence','ai_brains')
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  short_description = VALUES(short_description),
  long_description = VALUES(long_description),
  pricing_model = VALUES(pricing_model),
  status = VALUES(status),
  sort_order = VALUES(sort_order),
  metadata_json = VALUES(metadata_json);

INSERT INTO tenant_abilities (company_id, ability_id, ability_version_id, status, installed_at, activated_at, settings_json)
SELECT c.id, a.id, av.id, 'active', NOW(), NOW(), JSON_OBJECT('compatibility_seed', TRUE)
FROM companies c
JOIN abilities a ON a.is_default_enabled = TRUE AND a.status = 'active'
LEFT JOIN ability_versions av ON av.ability_id = a.id AND av.version = '1.0.0'
ON DUPLICATE KEY UPDATE
  ability_version_id = COALESCE(tenant_abilities.ability_version_id, VALUES(ability_version_id));
