# DEPLOY & BACKUP - PaqueteriaCZ

Guía mínima para desplegar en producción de forma segura.

1) Backup de la base de datos (MySQL)

```bash
# Dump completo de la BD
mysqldump -u root -p paquetes_apppack > backups/paquetes_apppack_$(date +%F_%T).sql
```

2) Backup de archivos

```bash
tar czvf backups/code_$(date +%F_%T).tar.gz .
```

3) Staging

- Crear un entorno staging idéntico a producción.
- Ejecutar pruebas manuales y automáticas.

4) Despliegue (propuesta simple)

```bash
git fetch --all
git checkout master
git pull origin master
# Ejecutar tareas de deploy
composer install --no-dev --optimize-autoloader
# Migraciones (si las tienes)
# php artisan migrate --force    # ejemplo si usas framework
chown -R www-data:www-data .
```

5) Rollback

- Si algo falla, restaurar el dump SQL y revertir a la versión anterior del código.

6) Consideraciones de seguridad
- Mover `JWT_SECRET_KEY` y credenciales fuera del repo y usar variables de entorno.
- Forzar HTTPS.
- Configurar cookies de sesión con `secure` y `httponly`.
- Habilitar rotación de logs y monitorización.
