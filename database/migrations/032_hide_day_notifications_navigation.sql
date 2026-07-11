UPDATE ability_navigation_items
SET is_active = FALSE
WHERE route IN ('/my-day', '/notifications');

UPDATE abilities
SET description = 'Dashboard y Bandeja de trabajo para operar cada jornada.',
    commercial_name = 'Operacion diaria'
WHERE ability_key = 'core_workspace';

UPDATE marketplace_items
SET short_description = 'Dashboard y Bandeja de trabajo para operar cada jornada.'
WHERE ability_key = 'core_workspace';
