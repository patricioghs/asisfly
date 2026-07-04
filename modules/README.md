# Modulos Asisty

Cada modulo debe vivir aislado y exponer rutas, controladores, servicios, politicas y migraciones propias cuando crezca.

## Modulos planificados

- Core
- Usuarios
- Empresas
- Roles y permisos
- Dashboard
- IA
- Entrenamiento
- Memoria
- Documentos
- Planillas
- Correos
- Calendario
- Redes sociales
- WhatsApp
- CRM
- Cotizaciones
- Automatizaciones
- Reportes
- Integraciones
- Suscripciones
- Administracion global
- Logs y auditoria

## Regla multiempresa

Toda tabla de negocio debe incluir `company_id`, indices por empresa y validaciones de acceso en controlador, servicio o policy. Ninguna consulta de negocio debe ejecutarse sin filtrar por empresa activa.
