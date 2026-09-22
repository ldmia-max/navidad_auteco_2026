# Entorno local con Docker

WordPress 6.7 + PHP 8.2 + MySQL 8 + phpMyAdmin. El repositorio se monta en vivo
dentro del contenedor, así que editar un archivo PHP se refleja al recargar la
página, sin rebuild.

## Levantar

```bash
docker compose up -d
```

| Servicio | URL | Credenciales |
|---|---|---|
| WordPress | http://localhost:8080 | se configura en la instalación |
| phpMyAdmin | http://localhost:8081 | `root` / `root` |
| MySQL | `localhost:3307` | `wordpress` / `wordpress` |

La primera vez WordPress pide completar la instalación. Después hay que activar
el plugin en **Plugins → Concurso Navideño TVS**.

## Comandos

```bash
docker compose logs -f wordpress     # seguir los logs
docker compose down                  # parar, conservando datos
docker compose down -v               # parar y borrar volúmenes (WP y BD desde cero)
```

## Log de depuración

`WP_DEBUG_LOG` está activo y `WP_DEBUG_DISPLAY` apagado, así que los avisos de
PHP van al archivo en vez de romper el HTML:

```bash
docker exec navidad_tvs_wp tail -f /var/www/html/wp-content/debug.log
```

## WP-CLI

Corre bajo el perfil `cli`, así que no se levanta con `up -d`:

```bash
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli plugin activate navidad-tvs

# Recrear tablas y aplicar migraciones sin reactivar el plugin
docker compose run --rm wpcli eval '(new NavidadTVS_Database())->create_tables();'

# Ver las tablas del concurso
docker compose run --rm wpcli db query "SHOW TABLES LIKE '%navidad_tvs%';"
```

## Verificar el esquema

```bash
docker compose run --rm wpcli db query "DESCRIBE wp_navidad_tvs_participantes;"
docker compose run --rm wpcli db query "DESCRIBE wp_navidad_tvs_sesiones;"
docker compose run --rm wpcli db query "DESCRIBE wp_navidad_tvs_scores;"
```

## Notas

- El montaje del repo en `wp-content/plugins/navidad-tvs` incluye carpetas que
  no son del plugin (`docs/`, `game/`, `docker/`, `ayudas/`). WordPress las
  ignora porque solo lee el header del archivo principal, y el zip de
  producción las excluye por lista blanca. Ver `build-zip.ps1`.
- El puerto de MySQL es **3307** para no chocar con una instalación local de
  MySQL en el 3306.
- `ayudas/` se monta dentro del contenedor pero está fuera del control de
  versiones: ahí van los CSV con datos reales.
