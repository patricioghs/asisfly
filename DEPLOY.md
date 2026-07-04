# Deploy Staging AsisFly

Ruta objetivo en VPS:

```bash
/var/www/html/staging-asisfly
```

Repositorio:

```bash
https://github.com/patricioghs/asisfly.git
```

Rama:

```bash
Staging
```

## Requisitos VPS

- Ubuntu 22.04/24.04.
- Nginx.
- PHP 8.3 FPM con extensiones `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `curl`, `xml`, `zip`.
- MySQL 8+.
- Certbot para SSL.

## Instalacion

```bash
cd /var/www/html
git clone -b Staging https://github.com/patricioghs/asisfly.git staging-asisfly
cd /var/www/html/staging-asisfly
cp .env.production.example .env
nano .env
```

Configurar `APP_URL`, credenciales MySQL y proveedor IA si aplica.

Para staging actual:

```env
APP_URL=https://staging-asisfly.tilo.cl
DB_DATABASE=asisfly_latam
DB_USERNAME=catalogo_user
DB_AUTO_CREATE=false
```

## Base de datos

La base de datos de staging ya existe:

```sql
asisfly_latam
```

El usuario configurado es:

```bash
catalogo_user
```

Verificar que el `.env` tenga `DB_PASSWORD` real y ejecutar importacion:

```bash
cd /var/www/html/staging-asisfly
php database/install.php
```

## Permisos

```bash
sudo chown -R www-data:www-data /var/www/html/staging-asisfly/storage /var/www/html/staging-asisfly/logs
sudo find /var/www/html/staging-asisfly/storage /var/www/html/staging-asisfly/logs -type d -exec chmod 775 {} \;
sudo find /var/www/html/staging-asisfly/storage /var/www/html/staging-asisfly/logs -type f -exec chmod 664 {} \;
```

## Nginx

Copiar configuracion:

```bash
sudo cp deploy/nginx/staging-asisfly.conf /etc/nginx/sites-available/staging-asisfly
sudo ln -s /etc/nginx/sites-available/staging-asisfly /etc/nginx/sites-enabled/staging-asisfly
sudo nginx -t
sudo systemctl reload nginx
```

Luego activar SSL:

```bash
sudo certbot --nginx -d staging-asisfly.tilo.cl
```

## Actualizacion

```bash
cd /var/www/html/staging-asisfly
git pull origin Staging
php database/install.php
sudo chown -R www-data:www-data storage logs
```

## Checklist beta

- `.env` no esta versionado.
- `APP_ENV=production`.
- `APP_URL` usa HTTPS.
- Nginx apunta a `/public`.
- `/storage` y `/logs` escribibles por `www-data`.
- Backups MySQL diarios configurados.
- Cuenta demo revisada.
- Warnings PHP ocultos en servidor.
