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

## Base de datos

Crear base y usuario:

```sql
CREATE DATABASE asisfly_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'asisfly_user'@'localhost' IDENTIFIED BY 'CAMBIAR_PASSWORD';
GRANT ALL PRIVILEGES ON asisfly_staging.* TO 'asisfly_user'@'localhost';
FLUSH PRIVILEGES;
```

Ejecutar instalador:

```bash
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
sudo certbot --nginx -d staging-asisfly.tudominio.com
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
