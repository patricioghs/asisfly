UPDATE abilities
SET is_default_enabled = TRUE,
    is_core = TRUE,
    status = 'active',
    metadata_json = JSON_SET(COALESCE(metadata_json, JSON_OBJECT()), '$.core', TRUE)
WHERE ability_key = 'core_omnichannel';

INSERT INTO tenant_abilities (company_id, ability_id, ability_version_id, status, installed_at, activated_at, disabled_at, settings_json)
SELECT c.id, a.id, av.id, 'active', NOW(), NOW(), NULL, JSON_OBJECT('core_default', TRUE)
FROM companies c
JOIN abilities a ON a.ability_key = 'core_omnichannel'
LEFT JOIN ability_versions av ON av.ability_id = a.id AND av.version = '1.0.0'
ON DUPLICATE KEY UPDATE
  status = 'active',
  activated_at = NOW(),
  disabled_at = NULL,
  ability_version_id = COALESCE(tenant_abilities.ability_version_id, VALUES(ability_version_id)),
  settings_json = JSON_SET(COALESCE(tenant_abilities.settings_json, JSON_OBJECT()), '$.core_default', TRUE);
