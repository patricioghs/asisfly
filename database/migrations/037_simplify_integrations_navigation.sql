UPDATE ability_navigation_items
SET is_active = FALSE
WHERE section = 'Integraciones'
  AND route IN ('/integrations/accounts', '/apis');

UPDATE ability_navigation_items
SET is_active = TRUE,
    sort_order = 10
WHERE section = 'Integraciones'
  AND route = '/integrations';
