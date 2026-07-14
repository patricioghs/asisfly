-- Cerebro routing remains active internally; this removes the technical screen from daily navigation.
UPDATE ability_navigation_items
SET is_active = FALSE
WHERE route = '/brains'
  AND label = 'Cerebros IA';
