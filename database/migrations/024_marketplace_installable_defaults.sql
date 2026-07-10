UPDATE abilities
SET is_default_enabled = FALSE,
    metadata_json = JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.installable', TRUE)
WHERE ability_key IN ('crm', 'quotes', 'social_marketing', 'intelligence');

UPDATE tenant_abilities ta
INNER JOIN abilities a ON a.id = ta.ability_id
SET ta.status = 'disabled',
    ta.disabled_at = COALESCE(ta.disabled_at, NOW()),
    ta.settings_json = JSON_SET(COALESCE(ta.settings_json, JSON_OBJECT()), '$.installable_default', TRUE)
WHERE a.ability_key IN ('crm', 'quotes', 'social_marketing', 'intelligence')
  AND ta.status = 'active'
  AND JSON_EXTRACT(COALESCE(ta.settings_json, JSON_OBJECT()), '$.marketplace') IS NULL;
