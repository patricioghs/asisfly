UPDATE crm_customers
SET source = 'WhatsApp', temperature = 'hot', last_activity_at = DATE_SUB(NOW(), INTERVAL 1 DAY), next_follow_up_at = DATE_ADD(NOW(), INTERVAL 1 DAY)
WHERE company_id = 1 AND name = 'Comercial Pacifico';

UPDATE crm_customers
SET source = 'Instagram', temperature = 'cold', last_activity_at = DATE_SUB(NOW(), INTERVAL 9 DAY), next_follow_up_at = DATE_SUB(NOW(), INTERVAL 2 DAY)
WHERE company_id = 1 AND name = 'Grupo Norte';

INSERT IGNORE INTO crm_contacts (company_id, customer_id, name, email, phone, role, is_primary)
SELECT company_id, id, contact_name, email, phone, 'Compras', TRUE FROM crm_customers WHERE company_id = 1;

INSERT IGNORE INTO crm_opportunities (company_id, customer_id, title, stage, amount, currency, probability, expected_close_date, source)
SELECT company_id, id, CONCAT('Venta asistida - ', name),
  CASE
    WHEN stage LIKE '%Propuesta%' THEN 'propuesta'
    WHEN stage LIKE '%Negoci%' THEN 'negociacion'
    WHEN stage LIKE '%Gan%' THEN 'ganado'
    WHEN stage LIKE '%Perd%' THEN 'perdido'
    WHEN stage LIKE '%Calif%' THEN 'calificado'
    ELSE 'nuevo'
  END,
  estimated_value, currency, 65, DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY), source
FROM crm_customers
WHERE company_id = 1;

INSERT IGNORE INTO crm_tasks (company_id, customer_id, assigned_to, title, task_type, due_at, status, priority)
SELECT company_id, id, 1, CONCAT('Hacer seguimiento a ', name), 'follow_up', COALESCE(next_follow_up_at, DATE_ADD(NOW(), INTERVAL 1 DAY)), 'pending', IF(temperature = 'cold', 'high', 'medium')
FROM crm_customers
WHERE company_id = 1;

INSERT IGNORE INTO crm_notes (company_id, customer_id, user_id, note)
SELECT company_id, id, 1, CONCAT('Cliente creado para demo CRM vendible. Estado actual: ', stage, '. Fuente: ', COALESCE(source, 'manual'), '.')
FROM crm_customers
WHERE company_id = 1;

INSERT IGNORE INTO crm_activities (company_id, customer_id, user_id, activity_type, summary, metadata_json)
SELECT company_id, id, 1, 'seed', CONCAT('Actividad inicial registrada para ', name), JSON_OBJECT('stage', stage, 'temperature', temperature)
FROM crm_customers
WHERE company_id = 1;
