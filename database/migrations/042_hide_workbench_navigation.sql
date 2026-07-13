-- Tareas reemplaza la Bandeja de trabajo como centro operativo.
UPDATE ability_navigation_items
SET is_active = FALSE
WHERE route = '/workbench';

UPDATE abilities
SET description = 'Dashboard para el resumen ejecutivo diario. Las acciones operativas se gestionan desde Tareas y sus modulos de origen.',
    commercial_name = 'Inicio y resumen'
WHERE ability_key = 'core_workspace';

UPDATE marketplace_items
SET short_description = 'Dashboard para el resumen diario. Tareas centraliza los pendientes y lleva cada caso a su modulo de origen.'
WHERE slug = 'core_workspace'
   OR ability_id IN (SELECT id FROM abilities WHERE ability_key = 'core_workspace');
