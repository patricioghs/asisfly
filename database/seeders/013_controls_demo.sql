INSERT INTO business_controls
  (company_id, owner_id, name, control_key, objective, category, brain_key, status, frequency, source_type, rules_json, metrics_json, next_review_at)
VALUES
  (1, 1, 'Control de gastos', 'control-gastos', 'Clasificar gastos, detectar aumentos, encontrar duplicados y preparar resumen mensual para gerencia.', 'financial', 'analytical', 'active', 'weekly', 'mixed',
    JSON_OBJECT('approval_required', true, 'alert_if_amount_over', 500000, 'categories', JSON_ARRAY('Marketing', 'Operaciones', 'Proveedores', 'Software', 'Administracion')),
    JSON_OBJECT('monthly_total', 4280000, 'variation', 18, 'pending_items', 4, 'currency', 'CLP'),
    DATE_ADD(NOW(), INTERVAL 2 DAY)),
  (1, 1, 'Control de pagos pendientes', 'control-pagos-pendientes', 'Recordar pagos, vencimientos, proveedores criticos y compromisos comerciales antes de que se atrasen.', 'administrative', 'administrative', 'active', 'daily', 'email',
    JSON_OBJECT('approval_required', false, 'notify_days_before', 3, 'critical_suppliers', JSON_ARRAY('Proveedor ABC', 'Arriendo', 'Servicios')),
    JSON_OBJECT('pending_payments', 7, 'due_today', 2, 'overdue', 1),
    DATE_ADD(NOW(), INTERVAL 1 DAY)),
  (1, 1, 'Control de inventario comercial', 'control-inventario-comercial', 'Detectar productos con baja disponibilidad, alto margen o baja rotacion para recomendar acciones comerciales.', 'inventory', 'commercial', 'active', 'weekly', 'spreadsheet',
    JSON_OBJECT('approval_required', true, 'low_stock_threshold', 8, 'suggest_posts', true),
    JSON_OBJECT('low_stock', 5, 'high_margin_products', 9, 'slow_rotation', 3),
    DATE_ADD(NOW(), INTERVAL 3 DAY)),
  (1, 1, 'Control de clientes frios', 'control-clientes-frios', 'Detectar clientes que pidieron informacion, no compraron y necesitan seguimiento comercial.', 'commercial', 'commercial', 'active', 'daily', 'mixed',
    JSON_OBJECT('approval_required', true, 'inactive_days', 3, 'create_followup_tasks', true),
    JSON_OBJECT('cold_customers', 8, 'recovered_this_week', 2, 'estimated_pipeline', 1650000),
    DATE_ADD(NOW(), INTERVAL 1 DAY))
ON DUPLICATE KEY UPDATE
  objective = VALUES(objective),
  category = VALUES(category),
  brain_key = VALUES(brain_key),
  status = VALUES(status),
  frequency = VALUES(frequency),
  source_type = VALUES(source_type),
  rules_json = VALUES(rules_json),
  metrics_json = VALUES(metrics_json),
  next_review_at = VALUES(next_review_at);

INSERT INTO business_control_alerts (company_id, control_id, severity, title, body, status, due_at)
SELECT company_id, id, 'high', 'Gastos de marketing subieron 22%', 'AsisFly detecto un aumento relevante contra el periodo anterior. Conviene revisar campanas y proveedores.', 'open', DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM business_controls WHERE company_id = 1 AND control_key = 'control-gastos'
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO business_control_alerts (company_id, control_id, severity, title, body, status, due_at)
SELECT company_id, id, 'critical', 'Hay pagos que vencen hoy', 'Existen 2 pagos pendientes que deberian revisarse antes del cierre del dia.', 'open', NOW()
FROM business_controls WHERE company_id = 1 AND control_key = 'control-pagos-pendientes'
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO business_control_alerts (company_id, control_id, severity, title, body, status, due_at)
SELECT company_id, id, 'medium', 'Producto de alto margen con stock bajo', 'Conviene revisar reposicion antes de impulsar nuevas publicaciones o campanas.', 'open', DATE_ADD(NOW(), INTERVAL 2 DAY)
FROM business_controls WHERE company_id = 1 AND control_key = 'control-inventario-comercial'
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO business_control_entries (company_id, control_id, user_id, title, entry_type, amount, currency, period_label, status, data_json)
SELECT company_id, id, 1, 'Gasto importado desde planilla', 'spreadsheet_row', 380000, 'CLP', 'Julio 2026', 'pending', JSON_OBJECT('category', 'Marketing', 'provider', 'Meta Ads')
FROM business_controls WHERE company_id = 1 AND control_key = 'control-gastos'
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO business_control_entries (company_id, control_id, user_id, title, entry_type, period_label, status, data_json)
SELECT company_id, id, 1, 'Clientes sin respuesta detectados', 'crm_signal', 'Ultimos 7 dias', 'pending', JSON_OBJECT('count', 8, 'source', 'CRM y Omnicanal')
FROM business_controls WHERE company_id = 1 AND control_key = 'control-clientes-frios'
ON DUPLICATE KEY UPDATE title = title;
