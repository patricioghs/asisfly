UPDATE ability_navigation_items
SET is_active = FALSE
WHERE section = 'Conocimiento'
  AND route IN ('/knowledge-base', '/catalogs', '/manuals', '/training');

UPDATE ability_navigation_items
SET is_active = TRUE,
    sort_order = CASE route
        WHEN '/ai-training' THEN 5
        WHEN '/documents' THEN 10
        ELSE sort_order
    END
WHERE section = 'Conocimiento'
  AND route IN ('/ai-training', '/documents');
