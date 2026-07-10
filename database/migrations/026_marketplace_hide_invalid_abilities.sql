UPDATE marketplace_items mi
INNER JOIN abilities a ON a.id = mi.ability_id
SET mi.status = 'hidden'
WHERE TRIM(COALESCE(a.ability_key, '')) = '';

UPDATE abilities
SET status = 'hidden'
WHERE TRIM(COALESCE(ability_key, '')) = '';
