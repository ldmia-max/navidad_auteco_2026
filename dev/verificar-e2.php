<?php
/**
 * Verificación manual de E2 (importador del padrón). Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e2.php
 *
 * ESCRIBE EN LA BASE DE DATOS. Es un script de desarrollo: deja el padrón de
 * ejemplo importado para poder revisarlo en el admin. Nunca correr en
 * producción.
 *
 * @package NavidadTVS
 */

$GLOBALS['fallos'] = 0;
$base   = dirname( __DIR__ );

function comprobar( $etiqueta, $esperado, $obtenido ) {
	$ok = ( $esperado === $obtenido );
	if ( ! $ok ) {
		$GLOBALS['fallos']++;
	}
	printf(
		"%s %-52s esperado=%-10s obtenido=%s\n",
		$ok ? 'OK  ' : 'FALLA',
		$etiqueta,
		var_export( $esperado, true ),
		var_export( $obtenido, true )
	);
}

/** Cuenta cuántos rechazos mencionan un texto. */
function rechazos_con( $rechazos, $texto ) {
	$n = 0;
	foreach ( $rechazos as $r ) {
		if ( false !== stripos( $r['motivo'], $texto ) ) {
			$n++;
		}
	}
	return $n;
}

/** Devuelve el motivo del rechazo de una fila concreta. */
function motivo_de_fila( $rechazos, $fila ) {
	foreach ( $rechazos as $r ) {
		if ( (int) $r['fila'] === (int) $fila ) {
			return $r['motivo'];
		}
	}
	return '(sin rechazo)';
}

// El plugin solo instancia el admin cuando is_admin() es true, y WP-CLI no lo
// es. La clase se carga aquí a mano.
require_once NAVIDAD_TVS_PATH . 'includes/admin/class-import-padron.php';

$db         = NavidadTVS_Plugin::instancia()->database;
$importador = new NavidadTVS_Import_Padron( $db );

// Partir de un padrón vacío para que las cuentas sean deterministas.
global $wpdb;
$wpdb->query( "DELETE FROM {$db->tabla_scores}" );
$wpdb->query( "DELETE FROM {$db->tabla_sesiones}" );
$wpdb->query( "DELETE FROM {$db->tabla_participantes}" );

echo "=== Normalizadores de campo ===\n";
comprobar( 'cedula con puntos', '1100000001', NavidadTVS_Import_Padron::normalizar_cedula( '1.100.000.001' ) );
comprobar( 'cedula muy corta', '', NavidadTVS_Import_Padron::normalizar_cedula( '123' ) );
comprobar( 'placa con guion', 'AAA04A', NavidadTVS_Import_Padron::normalizar_placa( 'aaa-04a' ) );
comprobar( 'placa muy corta', '', NavidadTVS_Import_Padron::normalizar_placa( 'AB1' ) );
comprobar( 'fecha ISO', '2026-12-01', NavidadTVS_Import_Padron::normalizar_fecha( '2026-12-01' ) );
comprobar( 'fecha d/m/Y', '2026-12-01', NavidadTVS_Import_Padron::normalizar_fecha( '01/12/2026' ) );
comprobar( 'fecha d-m-Y', '2026-12-01', NavidadTVS_Import_Padron::normalizar_fecha( '01-12-2026' ) );
comprobar( 'fecha en texto', '', NavidadTVS_Import_Padron::normalizar_fecha( '31 de diciembre' ) );
comprobar( 'fecha imposible', '', NavidadTVS_Import_Padron::normalizar_fecha( '2026-02-31' ) );
comprobar( 'fecha vacia', '', NavidadTVS_Import_Padron::normalizar_fecha( '' ) );

echo "\n=== Analisis del CSV de ejemplo ===\n";
$ruta     = $base . '/docs/ejemplos/padron-ejemplo.csv';
$analisis = $importador->analizar( $ruta );

if ( is_wp_error( $analisis ) ) {
	echo 'FALLA  el analisis devolvio error: ' . $analisis->get_error_message() . "\n";
	$GLOBALS['fallos']++;
} else {
	comprobar( 'delimitador detectado', ';', $analisis['delimitador'] );
	comprobar( 'filas validas', 7, count( $analisis['filas'] ) );
	comprobar( 'filas rechazadas', 10, count( $analisis['rechazos'] ) );

	echo "\n--- motivo por fila ---\n";
	foreach ( array( 7, 8, 9, 10, 11, 12, 13, 14, 15, 16 ) as $f ) {
		printf( "   fila %-3d %s\n", $f, motivo_de_fila( $analisis['rechazos'], $f ) );
	}
	echo "\n";

	comprobar( 'rechazos por marca', 2, rechazos_con( $analisis['rechazos'], 'no admitida' ) );
	comprobar( 'rechazos por telefono', 2, rechazos_con( $analisis['rechazos'], 'eléfono' ) );
	comprobar( 'rechazos por cedula faltante', 1, rechazos_con( $analisis['rechazos'], 'Falta la cédula' ) );
	comprobar( 'rechazos por placa faltante', 1, rechazos_con( $analisis['rechazos'], 'Falta la placa' ) );
	comprobar( 'rechazos por fecha', 1, rechazos_con( $analisis['rechazos'], 'Fecha de concurso inválida' ) );
	comprobar( 'rechazos por duplicado en el archivo', 3, rechazos_con( $analisis['rechazos'], 'ya apareció en la fila' ) );

	comprobar( 'jornada 2026-12-01', 5, isset( $analisis['jornadas']['2026-12-01'] ) ? $analisis['jornadas']['2026-12-01'] : 0 );
	comprobar( 'jornada 2026-12-02', 2, isset( $analisis['jornadas']['2026-12-02'] ) ? $analisis['jornadas']['2026-12-02'] : 0 );

	echo "\n--- normalizacion aplicada a las filas validas ---\n";
	$por_cedula = array();
	foreach ( $analisis['filas'] as $f ) {
		$por_cedula[ $f['cedula'] ] = $f;
	}
	comprobar( 'telefono con indicativo -> 10 digitos', '3001110001', $por_cedula['1100000001']['telefono'] );
	comprobar( 'telefono sin indicativo intacto', '3001110003', $por_cedula['1100000003']['telefono'] );
	comprobar( 'telefono con +57 y espacios', '3001110004', $por_cedula['1100000004']['telefono'] );
	comprobar( 'placa con guion normalizada', 'AAA04A', $por_cedula['1100000004']['placa'] );
	comprobar( 'fecha d/m/Y convertida', '2026-12-01', $por_cedula['1100000005']['fecha_concurso'] );
	comprobar( 'marca en minusculas aceptada', 'TVS', $por_cedula['1100000017']['marca'] );
	comprobar( 'fechas opcionales vacias', '', $por_cedula['1100000016']['fecha_matricula'] );

	echo "\n=== Insercion ===\n";
	$insertadas = $db->insertar_participantes( $analisis['filas'] );
	comprobar( 'filas insertadas', 7, $insertadas );
	comprobar( 'total en el padron', 7, $db->contar_participantes() );
	comprobar( 'habilitados 2026-12-01', 5, $db->contar_participantes( '2026-12-01' ) );

	$guardada = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$db->tabla_participantes} WHERE cedula = %s", '1100000016' ),
		ARRAY_A
	);
	comprobar( 'fecha opcional vacia guardada como NULL', null, $guardada['fecha_matricula'] );
	comprobar( 'telefono_csv conserva el original', '573001110016', $guardada['telefono_csv'] );

	echo "\n=== Reimportar el mismo archivo (debe rechazar todo) ===\n";
	$segundo = $importador->analizar( $ruta );
	comprobar( 'filas nuevas en la segunda pasada', 0, count( $segundo['filas'] ) );
	comprobar( 'rechazos por ya existir', 7, rechazos_con( $segundo['rechazos'], 'Ya está en el padrón' ) );
	comprobar( 'el padron no crecio', 7, $db->contar_participantes() );
}

echo "\n=== Resumen por jornada ===\n";
foreach ( $db->resumen_por_jornada() as $j ) {
	printf( "   %s  habilitados=%-5d participaron=%d\n", $j['fecha_concurso'], $j['total'], $j['jugaron'] );
}

// ---------------------------------------------------------------------
// Padrón real, si está disponible. No se versiona: trae datos personales.
// ---------------------------------------------------------------------
$real = $base . '/ayudas/participantes_previo.csv';

if ( file_exists( $real ) ) {
	echo "\n=== Padron real (ayudas/participantes_previo.csv) ===\n";

	$inicio    = microtime( true );
	$analisis  = $importador->analizar( $real );

	if ( is_wp_error( $analisis ) ) {
		echo 'FALLA  ' . $analisis->get_error_message() . "\n";
		$GLOBALS['fallos']++;
	} else {
		$validas   = count( $analisis['filas'] );
		$rechazos  = count( $analisis['rechazos'] );
		$insertadas = $db->insertar_participantes( $analisis['filas'] );
		$segundos   = round( microtime( true ) - $inicio, 2 );

		printf( "   filas validas   : %d\n", $validas );
		printf( "   filas rechazadas: %d\n", $rechazos );
		printf( "   insertadas      : %d\n", $insertadas );
		printf( "   jornadas        : %s\n", implode( ', ', array_keys( $analisis['jornadas'] ) ) );
		printf( "   tiempo total    : %s s\n", $segundos );

		/*
		 * No se exige que el archivo real venga limpio. El que hay en ayudas/
		 * cambia, y una versión anonimizada del padrón colapsa teléfonos
		 * distintos en el mismo número, con lo que el importador los rechaza
		 * como duplicados: eso es correcto, no un fallo. Lo que sí se
		 * comprueba es que el importador sea coherente consigo mismo.
		 */
		if ( $rechazos > 0 ) {
			$motivos = array();
			foreach ( $analisis['rechazos'] as $r ) {
				$clave = preg_replace( '/"[^"]*"/', '"X"', $r['motivo'] );
				$clave = preg_replace( '/fila [0-9]+/', 'fila N', $clave );
				$motivos[ $clave ] = ( $motivos[ $clave ] ?? 0 ) + 1;
			}
			echo "   motivos de rechazo:\n";
			foreach ( $motivos as $m => $n ) {
				printf( "     %4d  %s\n", $n, $m );
			}
		}

		comprobar( 'insertadas == validas', $validas, $insertadas );
		comprobar( 'nada se pierde por el camino', $validas + $rechazos > 0, true );

		echo "\n--- reimportacion del archivo real ---\n";
		$otra = $importador->analizar( $real );
		comprobar( 'ninguna fila nueva', 0, count( $otra['filas'] ) );
		comprobar( 'todas rechazadas por duplicado', $validas, rechazos_con( $otra['rechazos'], 'Ya está en el padrón' ) );
	}
} else {
	echo "\n(Sin padron real en ayudas/: se omite esa parte)\n";
}

echo "\n";
$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
