INSERT INTO inbox_conversations (company_id, channel, external_id, customer_name, customer_handle, subject, status, priority) VALUES
(1, 'WhatsApp', 'wa_1001', 'Valentina Rojas', '+56911111111', 'Consulta por cotizacion', 'new', 'high'),
(1, 'Instagram', 'ig_2002', 'Tienda Norte', '@tiendanorte', 'Pregunta por promociones', 'open', 'medium'),
(1, 'Messenger', 'fb_3003', 'Carlos Medina', 'carlos.medina', 'Despacho disponible', 'new', 'medium'),
(1, 'Email', 'mail_4004', 'Constructora X', 'compras@constructorax.test', 'Solicitud comercial pendiente', 'pending_approval', 'critical');

INSERT INTO inbox_messages (company_id, conversation_id, direction, sender_name, body, ai_generated, status) VALUES
(1, 1, 'inbound', 'Valentina Rojas', 'Hola, quiero saber precios y despacho para esta semana.', false, 'received'),
(1, 2, 'inbound', 'Tienda Norte', 'Tienen promociones para comprar por volumen?', false, 'received'),
(1, 3, 'inbound', 'Carlos Medina', 'Hola, hacen despacho a regiones?', false, 'received'),
(1, 4, 'inbound', 'Constructora X', 'Necesitamos una cotizacion actualizada antes de las 16:00.', false, 'received'),
(1, 4, 'outbound', 'AsisFly', 'Hola, puedo preparar una cotizacion actualizada y enviarla para aprobacion.', true, 'draft');
