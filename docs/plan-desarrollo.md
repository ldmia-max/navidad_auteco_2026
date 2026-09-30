# Plan de desarrollo — Concurso Navideño Auteco TVS 2026

Juego arcade-retro estilo Excitebike para la campaña navideña de Auteco-TVS.
WordPress como contenedor y backoffice; el juego en Phaser 3 + TypeScript.

Este archivo es el tablero de avance. Al cerrar cada etapa se marca aquí y se
hace commit; el avance a la siguiente etapa se confirma antes de arrancar.

---

## Estado

| # | Etapa | Estado | Cierre |
|---|-------|--------|--------|
| E0 | Definición y arranque | ✅ Completada | 2026-09-22 |
| E1 | Entorno y cimientos del plugin | ✅ Completada | 2026-09-22 |
| E2 | Importador de padrón | ✅ Completada | 2026-09-22 |
| E3 | Acceso, ventana horaria y sesión | ✅ Completada | 2026-09-22 |
| E4 | Motor del juego | ✅ Completada | 2026-09-22 |
| E5 | Arte y estética 16 bits | ✅ Completada | 2026-09-22 |
| E6 | Validación server-side y score | ✅ Completada | 2026-09-24 |
| E7 | Sitio público | ✅ Completada | 2026-09-24 |
| E8 | Backoffice y ranking | ✅ Hecho | 30-09-2026 |
| E9 | Endurecimiento y QA | ⬜ Pendiente | — |
| E10 | Despliegue y operación | ⬜ Pendiente | — |

Ruta crítica: E0 → E1 → E2 → E3 → E4 → E6 → E8 → E9 → E10.
E5 y E7 corren en paralelo.

---

## E0 — Definición y arranque

Cerrar lo pendiente y dejar el repositorio listo.

- Documento de mecánica y balanceo: velocidad base, turbo, curva de
  temperatura, penalización por sobrecalentamiento, frecuencia y valor de
  ítems, tipos de obstáculo.
- Textos de T&C y FAQ de ejemplo (el área encargada entregará los definitivos).
- `CLAUDE.md` con las convenciones del proyecto.
- Estructura de carpetas y scripts `build-zip.ps1` / `build-zip.sh`.

**Revisión:** documento de mecánica y repositorio inicializado.

## E1 — Entorno y cimientos del plugin

Un plugin instalable, vacío pero con base de datos.

- Docker Compose: WordPress + PHP 8.2 + MySQL + phpMyAdmin.
- Bootstrap del plugin, constantes, singleton, guard `ABSPATH`.
- Tablas: `participantes`, `sesiones`, `scores`, con migraciones idempotentes.
- Menú de admin y esqueleto de módulos.
- Primer `.zip` en `dist/`.

**Revisión:** instalar el zip en un WP limpio y verificar las tablas.

## E2 — Importador de padrón

Cargar el CSV diario de compradores con validación estricta.

- Lector de CSV portado desde el proyecto Trivia (BOM, `sep=;`, delimitadores,
  cabeceras con y sin acentos).
- Validaciones: solo marca TVS, sin duplicados de teléfono/cédula/placa dentro
  del archivo ni contra lo ya importado, campos obligatorios, formato de fechas.
- Normalización de teléfono: el padrón trae 12 dígitos con indicativo (`57…`)
  y el participante digita 10.
- Reporte descargable de filas rechazadas con número de fila y causa.
- Importación acumulativa: cada archivo es el lote de un día y no debe borrar
  lo anterior ni afectar scores ya registrados.

**Revisión:** importar un CSV con errores intencionales y leer el reporte.

## E3 — Acceso, ventana horaria y sesión

Que solo entre quien debe, cuando debe.

- Página de acceso con estilo arcade: banner con portada del juego, inputs de
  celular y nombre, checkbox de T&C no premarcado.
- Validación: el celular existe en el padrón, la marca es TVS, hoy coincide con
  su `fecha_concurso`, y no ha jugado antes.
- Ventana 12:00–1:30 pm, lunes a sábado, con hora de servidor en
  `America/Bogota`. Fuera de horario no se renderiza el formulario y se muestra
  el aviso con la próxima apertura.
- Registro de consentimiento: fecha, hora, IP y versión del texto aceptado.
- Rate limiting y Turnstile contra enumeración de teléfonos.
- Sesión de un solo uso; el intento se marca consumido al iniciar la carrera.

**Revisión:** probar con celular no elegible, fuera de horario, en domingo, y
dos veces con el mismo número.

## E4 — Motor del juego

El juego funciona y es determinista.

- Phaser 3 + TypeScript, resolución interna 320×180, escalado por enteros.
- Física a paso fijo (60 ticks/s) desacoplada del render.
- Pista, obstáculos e ítems generados desde el `seed` del servidor con PRNG
  propio.
- Temperatura del motor: el turbo la sube, soltar o frenar la baja, y al tope
  la moto se detiene unos segundos.
- Contador de metros de 1 en 1; las llaves suman +50 m y cada impulsor, +1 m.
- Cronómetro de 90 s con countdown 3-2-1 tras el botón de iniciar carrera.
- Controles táctiles y teclado; sin zoom, scroll ni pull-to-refresh.
- Grabación del log de inputs por tick.

**Decisión técnica:** la física usa aritmética de punto fijo sobre enteros, no
flotantes. Es lo que permite que TypeScript y PHP produzcan resultados
idénticos y que la validación de E6 no rechace carreras legítimas.

**Revisión:** jugar en Docker con arte placeholder.

## E5 — Arte y estética 16 bits

*(en paralelo con E4 y E6)*

- Paleta oficial TVS: azul `#1F3A72`, rojo `#E01D2D`, blanco `#FFFFFF`
  (`imagenes_apoyo/paleta_colores.jpeg`). El azul `#0059E9` se reserva como
  acento interactivo del sitio.
- Una sola moto doble propósito, como en el Excitebike original.
- Sprites: acelerar, wheelie, salto, caída, sobrecalentamiento.
- **Cambio de carril con transición**, no instantáneo: en el original la moto
  se inclina y se desplaza durante unos cuadros. Hoy salta de golpe y se nota
  brusco (observado al probar E4).
- Vista del piloto girando al cambiar de carril, como en la referencia.
- Tribuna con público y cartel del concurso; banderines como luces navideñas.
- Parallax de 3–4 capas.
- Ítem coleccionable con el diseño de llave.
- Panel inferior: `DIST` izquierda, `TEMP` centro con barra roja/verde, `TIME`
  derecha, en marco azul con números rojo y blanco.
- Pantalla final: piloto en podio sobre panel con marco de cuadros, nombre y
  distancia debajo, mensaje de agradecimiento
  (`imagenes_apoyo/Ejemplo_final_carrera.png`).
- Modal de instrucciones: controles, jugabilidad
  y advertencia de conexión estable.
- Audio chiptune **sintetizado con Web Audio**, sin archivos: cero bytes que
  descargar y sin el problema de que Safari no reproduzca `.ogg`.
- `theme.json` para cambiar textos y colores sin recompilar.
- Tipografía de píxeles propia de 5×7, en vez de una fuente del sistema.

**Revisión:** capturas de cada pantalla antes de integrarlas.

## E6 — Validación server-side y score ✅

Que ningún score llegue sin verificar.

- ✅ `POST /concurso/v1/carrera/terminar`, que recibe el registro de entradas y
  devuelve la distancia que calculó el servidor.
- ✅ El `seed` y el nonce de un solo uso ya salían de E3, al pulsar *Iniciar
  carrera*. Terminar exige que la sesión esté **consumida**: un resultado de una
  carrera que nunca arrancó se rechaza.
- ✅ Puerto completo de la física a PHP en `includes/sim/`, entero a entero.
- ✅ Suite de paridad TypeScript ↔ PHP: 36 carreras (6 pistas × 6 estrategias)
  comparadas campo por campo y también en 18 posiciones intermedias de cada una.
- ✅ Segunda malla de plausibilidad: tope de distancia y reloj de la sesión.
- ✅ Auditoría: seed, registro de entradas, IP, user agent, duración y lo que
  dijo el cliente, todo en la tabla de scores.
- ✅ Pantalla de resultado con la distancia oficial y reintento de envío.

**Revisión hecha:** `dev/probar-endpoint-e6.php` hace lo que haría alguien con
las DevTools abiertas —mandar el registro real con una distancia inventada de
99 999 m— y el servidor devuelve la suya. También comprueba el doble envío, el
reinicio de la carrera y el registro corrupto.

### Cómo se corre

```bash
cd game && npm run sim:vectores      # regenera los vectores desde TypeScript
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e6.php
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/probar-endpoint-e6.php
```

**Los vectores hay que regenerarlos cada vez que cambie la física**, y correr
después la suite de PHP. Es lo único que impide que los dos lados se separen en
silencio.

### Lo que este diseño sí y no resuelve

Lo que resuelve: nadie puede inventarse metros. El navegador solo puede mandar
qué botones se pulsaron, y el servidor saca la distancia de ahí con el seed que
él mismo emitió y no entregó antes de tiempo.

Lo que no: un bot que juegue de verdad, muy bien y muy rápido, produce un
registro legítimo. Contra eso no hay reejecución que valga; lo que hay es
Turnstile, el intento único, el padrón cerrado y la ventana de 90 minutos. Si
en pruebas aparece algo raro, el registro queda guardado y la carrera se puede
volver a ver entera meses después.

## E7 — Sitio público ✅

Las páginas que no son el juego. Cuatro shortcodes y un botón en la pantalla de
configuración que crea las páginas, las publica y las deja asignadas.

| Shortcode | Página |
|---|---|
| `[concurso_tvs]` | El juego |
| `[concurso_tvs_home]` | Portada |
| `[concurso_tvs_faq]` | Preguntas frecuentes |
| `[concurso_tvs_terminos]` | Términos y condiciones |

- ✅ Portada con hero, el estado de la jornada, los tres pasos y las cuatro
  reglas que hay que tener claras antes de jugar.
- ✅ FAQ con 36 preguntas en 7 grupos, en acordeones `<details>` nativos.
- ✅ T&C con 18 cláusulas numeradas por CSS.
- ✅ Responsive y accesible: un solo `h1` por página, secciones etiquetadas,
  áreas de toque de 44 px, y `prefers-reduced-motion`.

**Revisión:** navegar el sitio en móvil y escritorio.

### Dónde está el texto y por qué

En `includes/class-contenido.php`, no en el editor de WordPress. Se versiona
con el código, así que se puede saber qué texto exacto aceptó cada participante
mirando `terminos_version`; y las respuestas del FAQ describen el
comportamiento del juego, de modo que teniéndolas al lado del código es mucho
más probable que se actualicen cuando la mecánica cambia. El banco de E7
comprueba justamente eso: que el FAQ hable de llaves, conos, impulsores y
aceite, y que **no** hable de rampas, saltos ni logos de TVS.

### Los 15 pendientes del cliente

El texto es un borrador funcional. Lo que falta por decidir va marcado como
`[pendiente: ...]`, y no es decorativo: el panel de configuración avisa
mientras quede alguno y `dev/verificar-e7.php` los lista. Son 15: fechas de
inicio y cierre, correo y WhatsApp de soporte, razón social, NIT y domicilio
del organizador, descripción y valor del premio, plazos y lugar de entrega,
canal de anuncio de ganadores, correo del responsable de datos y horario de
atención.

**Antes de abrir al público hay que reemplazarlos y convertir ese aviso en un
fallo.** Está en el runbook de E10.

## E8 — Backoffice y ranking

Operar y auditar el concurso.

- Ranking ordenado por distancia descendente, incluso con filtros aplicados.
- Columnas: ID, nombre, cédula, teléfono, ciudad, departamento, distancia,
  fecha.
- Total de participantes y filtro por rango de fechas.
- Exportación de todo el padrón marcando quién participó y quién no.
- Vista de replay de un intento.
- Descalificación con motivo registrado.
- Congelamiento del concurso.

**Revisión:** exportar el CSV y verificar orden y totales.

**Cerrada el 30-09-2026.** El congelamiento ya venía de E1 y solo se comprobó
que `ventana_abierta()` lo respeta. La vista de replay no es un vídeo de la
carrera: es la reejecución de la simulación con el mismo seed y el mismo log,
comparada campo a campo con lo guardado. Sirve para lo que hace falta ante un
reclamo —demostrar que la distancia no se inventó— y avisa si una fila se
tocó. Un playback visual en el navegador sería otra cosa y no se descarta,
pero pide un modo de reproducción en el bundle del juego.

Los 21 resultados que había en la base de desarrollo reejecutan idénticos.

## E9 — Endurecimiento y QA

Que aguante el día de apertura.

- Hardening de WordPress y cabeceras de seguridad.
- Prueba de carga simulando 50 accesos simultáneos a las 12:00.
- QA en dispositivos reales: Android de gama baja (piso de 30 fps), iOS Safari,
  tablet, escritorio.
- Verificación de precarga de assets y barra de progreso.
- Ciclo completo con padrón real anonimizado.

**Revisión:** reporte de QA por dispositivo.

## E10 — Despliegue y operación

- Subdominio bajo `auteco.com`, SSL, CDN, backups horarios, monitoreo.
- Simulacro completo en producción antes de abrir.
- Runbook de operación.
- Acompañamiento el primer día.

---

## Decisiones cerradas

- **Mecánica:** 90 segundos fijos, gana quien recorra más metros. Ítems TVS
  suman +50 m. Contador de 1 en 1.
- **Temperatura del motor** es mecánica real, no decorativa.
- **Un solo intento** por participante, sin reintentos por ningún motivo. Se
  advierte al usuario que use conexión estable.
- **Acceso** con celular y nombre. Sin llave alfanumérica y sin OTP.
- **Elegibilidad:** marca TVS únicamente, y solo el día indicado en
  `fecha_concurso`.
- **Ganadores:** los 4 de mayor distancia del día. Si hay empate, todos los
  empatados reciben premio; no hay desempate.
- **Horario:** 12:00 a 1:30 pm, hora Colombia, de lunes a sábado.
- **Nombre:** lo digita el participante; no viene en el padrón. La
  identificación legal del ganador es por cédula y teléfono.
- **Arquitectura:** un solo plugin, modular por dentro. El bundle del juego es
  un artefacto de build servido desde el plugin.
- **Infraestructura:** 2–4 vCPU / 4–8 GB. Con ~150 participantes diarios y pico
  de 40 en 10 minutos no hace falta cola asíncrona ni Redis para el ranking.

## Pendientes del cliente

- T&C y FAQ definitivos.
- Tipografía corporativa y logo TVS en SVG.
- Confirmación del subdominio bajo `auteco.com`.
- Accesos al servidor de producción.
