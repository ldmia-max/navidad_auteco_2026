<?php
/**
 * Importa un padrón desde la línea de comandos. Se corre con:
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/importar-padron.php ayudas/participantes_previo.csv
 *   ... dev/importar-padron.php ayudas/participantes_previo.csv limpiar
 *
 * La ruta va relativa a la raíz del plugin. Sin argumento usa
 * ayudas/participantes_previo.csv, que es donde se deja el archivo de pruebas.
 *
 * Con 'limpiar' vacía antes el padrón, las sesiones y los resultados. Sirve
 * para dejar la base como recién instalada; sin él la importación es
 * acumulativa, igual que en producción.
 *
 * La opción va como palabra suelta y no como --bandera: WP-CLI intercepta
 * cualquier argumento con dos guiones antes de que llegue al script y aborta
 * con "unknown parameter".
 *
 * ESCRIBE EN LA BASE DE DATOS. Script de desarrollo: nunca en producción, que
 * para eso está la pantalla de importación del panel.
 *
 * @package NavidadTVS
 */

$base = dirname( __DIR__ );

$ruta_rel = '';
$limpiar  = false;

foreach ( (array) $args as $arg ) {
	if ( 'limpiar' === $arg ) {
		$limpiar = true;
	} elseif ( '' === $ruta_rel ) {
		$ruta_rel = (string) $arg;
	}
}

if ( '' === $ruta_rel ) {
	$ruta_rel = 'ayudas/participantes_previo.csv';
}

$ruta = $base . '/' . ltrim( $ruta_rel, '/' );

if ( ! file_exists( $ruta ) ) {
	printf( "No existe el archivo: %s\n", $ruta );
	return;
}

// El plugin solo instancia el admin cuando is_admin() es true, y WP-CLI no lo
// es. La clase del importador se carga aquí a mano.
require_once NAVIDAD_TVS_PATH . 'includes/admin/class-import-padron.php';

$db         = NavidadTVS_Plugin::instancia()->database;
$importador = new NavidadTVS_Import_Padron( $db );

global $wpdb;

if ( $limpiar ) {
	$wpdb->query( "DELETE FROM {$db->tabla_scores}" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$db->tabla_sesiones}" ); // phpcs:ignore
	$wpdb->query( "DELETE FROM {$db->tabla_participantes}" ); // phpcs:ignore
	echo "Padrón, sesiones y resultados borrados.\n\n";
}

printf( "Archivo: %s\n", $ruta_rel );

$inicio   = microtime( true );
$analisis = $importador->analizar( $ruta );

if ( is_wp_error( $analisis ) ) {
	printf( "ERROR: %s\n", $analisis->get_error_message() );
	return;
}

$insertadas = $db->insertar_participantes( $analisis['filas'] );

printf( "Delimitador     : %s\n", $analisis['delimitador'] );
printf( "Filas válidas   : %d\n", count( $analisis['filas'] ) );
printf( "Filas rechazadas: %d\n", count( $analisis['rechazos'] ) );
printf( "Insertadas      : %d\n", $insertadas );
printf( "Tiempo          : %s s\n", round( microtime( true ) - $inicio, 2 ) );

/*
 * Los motivos se agrupan quitando los valores concretos. Con 543 filas, ver
 * "la fila 7 duplica a la 3" quinientas veces no dice nada; saber que hay
 * cuatrocientos duplicados y doce fechas malas, sí.
 */
if ( ! empty( $analisis['rechazos'] ) ) {
	$motivos = array();

	foreach ( $analisis['rechazos'] as $r ) {
		$clave = preg_replace( '/"[^"]*"/', '"X"', $r['motivo'] );
		$clave = preg_replace( '/fila [0-9]+/', 'fila N', $clave );

		$motivos[ $clave ] = ( $motivos[ $clave ] ?? 0 ) + 1;
	}

	echo "\nMotivos de rechazo:\n";
	arsort( $motivos );
	foreach ( $motivos as $m => $n ) {
		printf( "  %4d  %s\n", $n, $m );
	}
}

echo "\nJornadas en el padrón:\n";
foreach ( $db->resumen_por_jornada() as $j ) {
	printf(
		"  %s  habilitados=%-5d jugaron=%d%s\n",
		$j['fecha_concurso'],
		$j['total'],
		$j['jugaron'],
		$j['fecha_concurso'] === NavidadTVS_Plugin::hoy() ? '   <-- hoy' : ''
	);
}
