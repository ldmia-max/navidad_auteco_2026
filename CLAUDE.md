# CLAUDE.md

Guía para trabajar en este repositorio.

## Qué es esto

Plugin de WordPress para el **Concurso Navideño de Auteco TVS 2026**: un juego
arcade-retro estilo Excitebike donde los compradores de moto TVS corren 90
segundos y gana quien recorra más metros.

El repo **es** la carpeta del plugin: se monta directo en
`wp-content/plugins/navidad-tvs`. La excepción es `game/`, que es el código
fuente TypeScript del juego y no se empaqueta — su build sale a
`assets/game/`.

La documentación y los comentarios del código están en español; mantener ese
idioma al escribir código nuevo.

**Antes de tocar el motor del juego, leer
[docs/mecanica-y-balanceo.md](docs/mecanica-y-balanceo.md).** Ese documento es
la fuente de verdad de la física y el balanceo.

El avance por etapas se lleva en
[docs/plan-desarrollo.md](docs/plan-desarrollo.md).

## Nomenclatura

| Elemento | Valor |
|---|---|
| Slug del plugin | `navidad-tvs` |
| Archivo principal | `navidad-tvs.php` |
| Prefijo de clases | `NavidadTVS_` |
| Prefijo de constantes | `NAVIDAD_TVS_` |
| Text domain | `navidad-tvs` |
| Namespace REST | `concurso/v1` |
| Tablas | `{prefijo}navidad_tvs_participantes`, `_sesiones`, `_scores` |

## Comandos

### Entorno local

```bash
docker compose up -d      # WP en :8080, phpMyAdmin en :8081, MySQL en :3307
docker compose logs -f wordpress
docker compose down       # conserva datos
docker compose down -v    # borra volúmenes
```

El repo se monta en vivo dentro del contenedor: editar un archivo PHP se
refleja al recargar.

**El montaje va en solo lectura (`:ro`)**, y no es opcional. El repo *es* la
carpeta del plugin, así que pulsar "Eliminar" en Plugins borraba los archivos a
través del montaje y se llevaba el repositorio entero, `.git` incluido. Pasó el
29 de septiembre de 2026.

Con `:ro` el borrado falla y no pasa nada. WordPress no necesita escribir
dentro de la carpeta del plugin; lo único que se pierde es poder instalar el
zip encima en el WordPress local, que no se hacía. Editar desde el host sigue
funcionando igual, incluidos los scripts sueltos en `dev/`.

### Juego

```bash
cd game
npm install
npm run dev      # servidor de desarrollo con recarga
npm run build    # compila a ../assets/game/
```

El bundle compilado **sí se versiona** en `assets/game/`, para que el zip del
plugin funcione sin necesidad de Node en el servidor.

### Empaquetar

```powershell
.\build-zip.ps1              # dist\navidad-tvs-<version>.zip
.\build-zip.ps1 -Suffix rc1
```

```bash
./build-zip.sh
```

La versión sale del header `Version:` de `navidad-tvs.php`. Al subirla hay que
tocar **dos** lugares: ese header y la constante `NAVIDAD_TVS_VERSION`. Cambiar
la constante es lo que dispara `maybe_upgrade()` → `create_tables()` en
instalaciones ya activas.

Los scripts de build usan lista blanca: **un archivo nuevo fuera de
`INCLUDE_FILES`/`INCLUDE_DIRS` no se empaqueta.** Hay que agregarlo en los dos
scripts.

## Reglas que no se negocian

### 1. Nada que venga del cliente se usa para puntuar

El navegador envía el **log de inputs**, nunca el score. El servidor reejecuta
la simulación con el mismo seed y calcula la distancia. Lo que el jugador ve en
pantalla es cosmético hasta que el servidor lo confirma.

### 2. La física va en enteros

Ni un flotante en el bucle de simulación, ni en TypeScript ni en PHP. División
siempre truncada: `Math.trunc(a / b)` en TS, `intdiv($a, $b)` en PHP. Con
flotantes las dos implementaciones divergen y el validador empieza a rechazar
carreras legítimas.

Cualquier cambio en la física se aplica **en los dos lenguajes a la vez** y se
corre la suite de paridad antes de commitear.

### 3. Paso fijo

60 ticks por segundo, 5400 ticks por carrera, desacoplado del render. Un equipo
a 144 Hz y un celular a 30 fps deben producir exactamente el mismo resultado
con los mismos inputs.

### 4. Sin aleatoriedad libre

Nada de `Math.random()` ni `rand()`. Todo sale del PRNG `mulberry32` sembrado
con el seed del servidor, con aritmética sin signo de 32 bits (`>>> 0` en TS,
`& 0xFFFFFFFF` en PHP).

### 5. La hora la pone el servidor

La ventana de participación (12:00–1:30 p. m., lunes a sábado) se valida
siempre con hora del servidor en `America/Bogota`. La hora del dispositivo del
participante no se consulta para nada.

### 6. El padrón trae datos personales reales

`ayudas/` está en `.gitignore` y así se queda. Teléfonos, cédulas y placas
reales no se versionan, no se pegan en commits ni en issues.

## Convenciones de código

- Estilo WP clásico: sin namespaces, clases `NavidadTVS_*` con prefijo, cada
  archivo abre con `if (! defined('ABSPATH')) exit;`.
- Todo string visible pasa por `__()` / `esc_html_e()` con text domain
  `navidad-tvs`.
- SQL siempre con `$wpdb->prepare()`; los nombres de tabla se interpolan desde
  las propiedades de `NavidadTVS_Database`.
- Endpoints REST en `/wp-json/concurso/v1/`, nunca `admin-ajax.php`.
- Los assets se encolan con `NAVIDAD_TVS_VERSION` como cache buster.
- **`dbDelta()` no soporta `IF NOT EXISTS`**: su regex toma `IF` como nombre de
  tabla y las sentencias comparten clave interna. Los `CREATE TABLE` que pasan
  por `dbDelta` van sin esa cláusula. (Trampa heredada del proyecto Trivia,
  donde sí mordió.)
- Los cambios de esquema se hacen con un método `migrate_*()` idempotente que
  chequea con `SHOW COLUMNS` / `SHOW TABLES` antes de tocar nada, llamado al
  final de `create_tables()`.

## Teléfonos

El padrón llega con **12 dígitos e indicativo** (`573504567217`). El
participante digita **10** (`3504567217`). Toda comparación pasa por la función
de normalización, que quita el prefijo `57` y cualquier caracter no numérico.
Sin eso, nadie entra.

## Datos del padrón

CSV con separador `;`, cabeceras en snake_case:

```
telefono;cedula;placa;fecha_concurso;marca;fecha_matricula;fecha_acta;
ciudad_propietario;departamento_propietario;razon_social_establecimiento
```

- **No trae columna `nombre`.** El nombre lo digita el participante y es solo
  informativo; la identificación legal del ganador es por cédula y teléfono.
- Cada archivo es el lote de **un día** (`fecha_concurso` uniforme). La
  importación es acumulativa: no borra lo anterior ni toca scores existentes.
- Solo se aceptan filas con `marca = TVS`.
- El lector de CSV se porta desde el proyecto Trivia y ya resuelve BOM UTF-8,
  línea `sep=;`, delimitadores variables y cabeceras con o sin acentos.

## Tests

No hay suite de PHPUnit. La verificación es manual sobre Docker:

```bash
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e1.php
# ... e2, e3, e4, e6
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/probar-endpoint-e6.php
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-participantes.php
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e8.php
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/prueba-carga.php
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/ciclo-completo.php
cd game && npm run sim && npm run arte
```

La **suite de paridad TypeScript ↔ PHP** vive en `dev/verificar-e6.php` y es
obligatoria antes de cualquier commit que toque física, en cualquiera de los
dos lados. Corre 36 carreras conocidas por las dos implementaciones y las
compara campo por campo.

Los vectores de referencia los genera TypeScript:

```bash
cd game && npm run sim:vectores      # escribe dev/vectores-paridad.json
```

**Cambiar la física y no regenerarlos deja la suite comparando contra números
viejos**, que es la forma exacta en que esto se rompe sin que nadie se entere.

```bash
docker exec navidad_tvs_wp tail -f /var/www/html/wp-content/debug.log
```

Lo que hay que dejar configurado en el servidor y la lista de pruebas en
dispositivos reales están en
[docs/operacion-y-qa.md](docs/operacion-y-qa.md).
