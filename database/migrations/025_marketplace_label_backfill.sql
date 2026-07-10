UPDATE abilities
SET
  name = CASE ability_key
    WHEN 'core_workspace' THEN 'Core Workspace'
    WHEN 'core_ai_assistant' THEN 'Core AI Assistant'
    WHEN 'core_omnichannel' THEN 'Core Omnichannel'
    WHEN 'core_memory_documents' THEN 'Core Memory Documents'
    WHEN 'core_integrations' THEN 'Core Integrations'
    WHEN 'core_company_admin' THEN 'Core Company Admin'
    WHEN 'core_superadmin' THEN 'Core Superadmin'
    WHEN 'crm' THEN 'CRM'
    WHEN 'quotes' THEN 'Quotes'
    WHEN 'social_marketing' THEN 'Social Marketing'
    WHEN 'intelligence' THEN 'Business Intelligence'
    WHEN 'ai_brains' THEN 'AI Brains'
    ELSE name
  END,
  commercial_name = CASE ability_key
    WHEN 'core_workspace' THEN 'Operacion diaria'
    WHEN 'core_ai_assistant' THEN 'Asistente inteligente'
    WHEN 'core_omnichannel' THEN 'Comunicacion inteligente'
    WHEN 'core_memory_documents' THEN 'Memoria empresarial'
    WHEN 'core_integrations' THEN 'Integraciones base'
    WHEN 'core_company_admin' THEN 'Administracion de empresa'
    WHEN 'core_superadmin' THEN 'Administracion global'
    WHEN 'crm' THEN 'CRM comercial'
    WHEN 'quotes' THEN 'Cotizaciones'
    WHEN 'social_marketing' THEN 'Asisti Social'
    WHEN 'intelligence' THEN 'Inteligencia empresarial'
    WHEN 'ai_brains' THEN 'Cerebros IA'
    ELSE commercial_name
  END,
  description = CASE ability_key
    WHEN 'core_workspace' THEN 'Dashboard, Mi dia, Bandeja de trabajo y Notificaciones.'
    WHEN 'core_ai_assistant' THEN 'Chat IA, tareas, automatizaciones, aprobaciones, controles y reglas.'
    WHEN 'core_omnichannel' THEN 'Bandeja omnicanal y cuentas conectadas.'
    WHEN 'core_memory_documents' THEN 'Documentos, lectura de archivos, base de conocimiento, catalogos, manuales y entrenamiento.'
    WHEN 'core_integrations' THEN 'Integraciones, APIs y conectores base.'
    WHEN 'core_company_admin' THEN 'Empresa, usuarios, roles, plan y configuracion.'
    WHEN 'core_superadmin' THEN 'Panel Superadmin para soporte, planes, empresas, IA y auditoria.'
    WHEN 'crm' THEN 'Clientes, contactos, oportunidades, tareas y seguimiento comercial.'
    WHEN 'quotes' THEN 'Cotizaciones, productos, impuestos, PDF y seguimiento.'
    WHEN 'social_marketing' THEN 'Calendario editorial, copies, campanas y publicaciones.'
    WHEN 'intelligence' THEN 'Reportes, analytics, dashboards, Excel, indicadores y KPIs.'
    WHEN 'ai_brains' THEN 'Cerebros comercial, administrativo, analitico, operacional y ejecutivo.'
    ELSE description
  END
WHERE ability_key IN (
  'core_workspace',
  'core_ai_assistant',
  'core_omnichannel',
  'core_memory_documents',
  'core_integrations',
  'core_company_admin',
  'core_superadmin',
  'crm',
  'quotes',
  'social_marketing',
  'intelligence',
  'ai_brains'
);

UPDATE marketplace_items mi
INNER JOIN abilities a ON a.id = mi.ability_id
SET
  mi.slug = a.ability_key,
  mi.title = a.commercial_name,
  mi.short_description = LEFT(COALESCE(a.description, a.commercial_name), 255),
  mi.long_description = COALESCE(a.description, a.commercial_name),
  mi.status = 'published',
  mi.sort_order = a.sort_order
WHERE a.ability_key IN (
  'core_workspace',
  'core_ai_assistant',
  'core_omnichannel',
  'core_memory_documents',
  'core_integrations',
  'core_company_admin',
  'core_superadmin',
  'crm',
  'quotes',
  'social_marketing',
  'intelligence',
  'ai_brains'
);
