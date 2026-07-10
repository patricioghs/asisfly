UPDATE marketplace_items mi
INNER JOIN abilities a ON a.id = mi.ability_id
SET mi.status = 'hidden'
WHERE a.ability_key NOT IN (
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
