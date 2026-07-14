-- Documents remain the single visible entry point for company knowledge.
-- The underlying memory and document capabilities remain active.
UPDATE ability_navigation_items
SET is_active = FALSE
WHERE section = 'Asistente'
  AND route = '/documents'
  AND label = 'Memoria';
