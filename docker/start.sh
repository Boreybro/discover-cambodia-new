#!/bin/sh
set -e
APP=/var/www/html
PORT="${PORT:-8080}"

# exactly ONE Apache MPM (prefork, needed by mod_php) - fixes "More than one MPM loaded"
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Railway Volume mounted at /data keeps uploads + private files across deploys
if [ -d /data ]; then
  mkdir -p /data/uploads /data/storage
  if [ ! -f /data/.seeded ]; then            # first start: copy the old uploads shipped in the repo
    cp -a "$APP/public/uploads/." /data/uploads/ 2>/dev/null || true
    touch /data/.seeded
  fi
  rm -rf "$APP/public/uploads" "$APP/storage"
  ln -s /data/uploads "$APP/public/uploads"
  ln -s /data/storage "$APP/storage"
  chown -R www-data:www-data /data
fi
exec apache2-foreground
