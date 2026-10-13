-- =====================================================================
--  Borrar el padrón y TODO lo que generó
--  Concurso Navideño Auteco TVS 2026
-- =====================================================================
--
--  Deja la base como recién instalada: sin participantes, sin carreras,
--  sin ganadores y sin revanchas individuales. La configuración del
--  concurso (horario, premios, claves de Turnstile, páginas) NO se toca.
--
--  ESTO NO SE PUEDE DESHACER.
--
--  Los resultados no se pueden reconstruir: el log de entradas de cada
--  carrera vive en la tabla de resultados y no existe en ningún otro
--  sitio. Si se borra, se acabó.
--
--  ANTES DE EJECUTAR, haz copia de la base:
--      phpMyAdmin → la base → pestaña Exportar → Continuar
--
--  ---------------------------------------------------------------
--  EL PREFIJO DE LAS TABLAS
--  ---------------------------------------------------------------
--  Este archivo usa `wp_`. Si tu instalación usa otro, hay que
--  cambiarlo en todas partes antes de ejecutar.
--
--  Para saber cuál es: en phpMyAdmin, mira el nombre de las tablas en
--  la columna de la izquierda. Si ves `wp_options`, el prefijo es `wp_`;
--  si ves `wpxy_options`, es `wpxy_`.
--
--  Para cambiarlo: en la caja de SQL de phpMyAdmin, selecciona todo y
--  usa Buscar y reemplazar del editor, o pega el archivo en un editor
--  de texto y reemplaza `wp_navidad_tvs_` y `wp_options`.
--
--  ---------------------------------------------------------------
--  CÓMO EJECUTARLO
--  ---------------------------------------------------------------
--  Los pasos van numerados y se ejecutan de uno en uno, no todo de
--  golpe. El paso 1 no borra nada: sirve para ver qué hay antes y
--  decidir con el número delante.
--
-- =====================================================================


-- =====================================================================
--  PASO 1 — Qué hay ahora mismo  (NO BORRA NADA, se puede repetir)
-- =====================================================================
--  Ejecuta solo este bloque primero. Apunta los números: el paso 3
--  comprueba que quedaron en cero.

SELECT 'participantes del padrón'            AS concepto, COUNT(*) AS filas FROM wp_navidad_tvs_participantes
UNION ALL
SELECT 'sesiones de juego',                  COUNT(*) FROM wp_navidad_tvs_sesiones
UNION ALL
SELECT 'resultados de carreras',             COUNT(*) FROM wp_navidad_tvs_scores
UNION ALL
SELECT 'de esos, marcados como GANADORES',   COUNT(*) FROM wp_navidad_tvs_scores WHERE ganador = 1
UNION ALL
SELECT 'revanchas de una persona',           COUNT(*) FROM wp_navidad_tvs_revanchas WHERE participante_id > 0
UNION ALL
SELECT 'revanchas generales (SE CONSERVAN)', COUNT(*) FROM wp_navidad_tvs_revanchas WHERE participante_id = 0
UNION ALL
SELECT 'contadores del limitador',           COUNT(*) FROM wp_options
 WHERE option_name LIKE '\_transient\_navidad\_tvs\_rl\_%'
    OR option_name LIKE '\_transient\_timeout\_navidad\_tvs\_rl\_%'
UNION ALL
SELECT 'informes de importación guardados',  COUNT(*) FROM wp_options
 WHERE option_name LIKE '\_transient\_navidad\_tvs\_import\_resultado\_%'
    OR option_name LIKE '\_transient\_timeout\_navidad\_tvs\_import\_resultado\_%';


-- =====================================================================
--  PASO 2 — El borrado
-- =====================================================================
--  Ejecuta este bloque ENTERO de una vez, desde START TRANSACTION hasta
--  COMMIT. Va en una transacción: o se borra todo o no se borra nada,
--  así que un error a mitad de camino no deja la base con resultados
--  huérfanos apuntando a participantes que ya no existen.
--
--  Si algo sale mal y aún no has ejecutado el COMMIT, escribe ROLLBACK;
--  y ejecútalo: la base vuelve a como estaba.

START TRANSACTION;

-- Primero lo que cuelga de los participantes y después ellos. No hay
-- claves foráneas que lo obliguen, pero en este orden la base nunca pasa
-- por un estado donde un resultado apunte a alguien que ya no está.

-- Resultados: distancias, logs de entradas y marcas de ganador.
DELETE FROM wp_navidad_tvs_scores;

-- Sesiones: cada intento emitido, se haya jugado o no.
DELETE FROM wp_navidad_tvs_sesiones;

-- Revanchas concedidas a personas concretas.
-- Las generales (participante_id = 0) NO se tocan: son una decisión del
-- organizador sobre el calendario, no algo que generara un participante.
-- Para borrarlas también, está el paso 5.
DELETE FROM wp_navidad_tvs_revanchas WHERE participante_id > 0;

-- Y el padrón.
DELETE FROM wp_navidad_tvs_participantes;

-- Contadores del limitador de intentos. Van por teléfono y por IP, así
-- que son datos de los participantes igual que los demás; además, si se
-- quedan, alguien que acumuló rechazos sigue bloqueado aunque su fila ya
-- no exista.
DELETE FROM wp_options
 WHERE option_name LIKE '\_transient\_navidad\_tvs\_rl\_%'
    OR option_name LIKE '\_transient\_timeout\_navidad\_tvs\_rl\_%';

-- El informe de la última importación, que queda guardado quince minutos
-- para poder descargar el CSV de rechazos. Si se queda, la pantalla de
-- importar enseña el resumen de un archivo cuyas filas ya no están.
DELETE FROM wp_options
 WHERE option_name LIKE '\_transient\_navidad\_tvs\_import\_resultado\_%'
    OR option_name LIKE '\_transient\_timeout\_navidad\_tvs\_import\_resultado\_%';

COMMIT;


-- =====================================================================
--  PASO 3 — Comprobar que quedó limpio
-- =====================================================================
--  Todas las filas de 'filas' tienen que salir en 0, menos las revanchas
--  generales, que se conservan a propósito.

SELECT 'participantes del padrón'            AS concepto, COUNT(*) AS filas FROM wp_navidad_tvs_participantes
UNION ALL
SELECT 'sesiones de juego',                  COUNT(*) FROM wp_navidad_tvs_sesiones
UNION ALL
SELECT 'resultados de carreras',             COUNT(*) FROM wp_navidad_tvs_scores
UNION ALL
SELECT 'revanchas de una persona',           COUNT(*) FROM wp_navidad_tvs_revanchas WHERE participante_id > 0
UNION ALL
SELECT 'revanchas generales (SE CONSERVAN)', COUNT(*) FROM wp_navidad_tvs_revanchas WHERE participante_id = 0
UNION ALL
SELECT 'contadores del limitador',           COUNT(*) FROM wp_options
 WHERE option_name LIKE '\_transient\_navidad\_tvs\_rl\_%'
    OR option_name LIKE '\_transient\_timeout\_navidad\_tvs\_rl\_%';

-- Y esto comprueba que la configuración del concurso sigue en su sitio.
-- Tienen que aparecer navidad_tvs_settings y navidad_tvs_db_version.
SELECT option_name FROM wp_options WHERE option_name LIKE 'navidad\_tvs\_%';


-- =====================================================================
--  PASO 4 — OPCIONAL: que los identificadores vuelvan a empezar en 1
-- =====================================================================
--  Sin esto, el siguiente participante que se importe tendrá el id 1544 o
--  el que tocara. No molesta para nada: solo queda más limpio si el
--  borrado es para empezar la campaña de cero.
--
--  Va FUERA de la transacción porque ALTER TABLE confirma lo anterior por
--  su cuenta. Ejecútalo después del paso 3, nunca antes.

ALTER TABLE wp_navidad_tvs_participantes AUTO_INCREMENT = 1;
ALTER TABLE wp_navidad_tvs_sesiones      AUTO_INCREMENT = 1;
ALTER TABLE wp_navidad_tvs_scores        AUTO_INCREMENT = 1;


-- =====================================================================
--  PASO 5 — OPCIONAL: borrar también las jornadas de revancha
-- =====================================================================
--  Solo si además de vaciar el padrón quieres quitar las fechas de
--  revancha que hubiera abiertas. Son configuración del calendario, no
--  datos de participantes, y por eso el paso 2 no las toca.

DELETE FROM wp_navidad_tvs_revanchas WHERE participante_id = 0;
ALTER TABLE wp_navidad_tvs_revanchas AUTO_INCREMENT = 1;


-- =====================================================================
--  LO QUE ESTE SCRIPT NO BORRA, A PROPÓSITO
-- =====================================================================
--
--  wp_options → navidad_tvs_settings
--      Horario, días hábiles, premios por jornada, versión de términos,
--      claves de Turnstile y las páginas creadas. Borrarlo dejaría el
--      concurso sin configurar.
--
--  wp_options → navidad_tvs_db_version
--      La versión del esquema. Si se borra, el plugin cree que la
--      instalación es nueva y vuelve a correr las migraciones.
--
--  Las páginas de WordPress (juego, términos, FAQ) y sus contenidos.
--
--  Las tablas en sí: quedan vacías pero con su estructura, listas para
--  importar el padrón del día siguiente.
-- =====================================================================
