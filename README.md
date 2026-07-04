# AsisFly

AsisFly es una base SaaS MVC en PHP 8.3 para construir un empleado digital multiempresa orientado a Latinoamerica.

## Fase 1 incluida

- Login y registro simulados.
- Separacion multiempresa por contexto de sesion.
- Roles iniciales.
- Dashboard ejecutivo.
- Chat con IA simulada.
- Configuracion de personalidad del asistente.
- Carga de documentos.
- CRM basico.
- Cotizaciones basicas.
- Registro visible de consumo IA.
- Panel superadmin.
- Cerebros especializados: Comercial, Administrativo, Analitico, Operacional y Ejecutivo.
- Asisti Social para ideas, captions, calendario editorial, hashtags, CTA y borradores por canal.
- Centro de Acciones para aprobar, rechazar y ejecutar acciones sugeridas por la IA.
- Bandeja Omnicanal para WhatsApp, Instagram, Messenger y correo con respuestas IA aprobables.
- Conectores simulados para Gmail, Outlook, Calendar, WhatsApp, Instagram, Facebook y Telegram.
- Migraciones SQL y seeders iniciales.

## Requisitos

- PHP 8.3+
- MySQL 8+
- Apache o Nginx apuntando a `/public`

## Instalacion local

1. Copiar `.env.example` a `.env`.
2. Crear la base de datos `asisfly`.
3. Ejecutar `C:\xampp\php\php.exe database/install.php`.
   - Alternativa manual: ejecutar `database/migrations/001_initial_schema.sql` y luego `database/seeders/002_demo_data.sql`.
5. Abrir `http://localhost/asisfly/public`.

La interfaz funciona con datos demo aunque la conexion MySQL todavia no este configurada. La siguiente fase debe conectar los modelos a PDO y reemplazar los repositorios demo.
