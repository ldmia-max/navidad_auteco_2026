<?php
/**
 * Verificación manual de E1. Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e1.php
 *
 * No escribe nada en la base de datos.
 */

$fallos = 0;

function comprobar( $etiqueta, $esperado, $obtenido ) {
	global $fallos;
	$ok = ( $esperado === $obtenido );
	if ( ! $ok ) {
		$fallos++;
	}
	printf(
		"%s %-46s esperado=%-14s obtenido=%s\n",
		$ok ? 'OK  ' : 'FALLA',
		$etiqueta,
		var_export( $esperado, true ),
		var_export( $obtenido, true )
	);
}

echo "--- normalizar_telefono ---\n";
comprobar( 'padron con indicativo', '3504567217', NavidadTVS_Plugin::normalizar_telefono( '573504567217' ) );
comprobar( 'digitado por el usuario', '3504567217', NavidadTVS_Plugin::normalizar_telefono( '3504567217' ) );
comprobar( 'con +57', '3504567217', NavidadTVS_Plugin::normalizar_telefono( '+57 350 456 7217' ) );
comprobar( 'con guiones', '3504567217', NavidadTVS_Plugin::normalizar_telefono( '350-456-7217' ) );
comprobar( 'fijo de Bogota (rechazado)', '', NavidadTVS_Plugin::normalizar_telefono( '6012345678' ) );
comprobar( 'muy corto', '', NavidadTVS_Plugin::normalizar_telefono( '35045672' ) );
comprobar( 'vacio', '', NavidadTVS_Plugin::normalizar_telefono( '' ) );
comprobar( 'solo letras', '', NavidadTVS_Plugin::normalizar_telefono( 'abc' ) );

echo "\n--- ventana_abierta (hora Colombia) ---\n";
$tz = new DateTimeZone( NAVIDAD_TVS_TZ );
$casos = array(
	array( '2026-09-22 12:00:00', true,  'martes 12:00 (apertura)' ),
	array( '2026-09-22 13:29:59', true,  'martes 13:29 (ultimo minuto)' ),
	array( '2026-09-22 13:30:00', false, 'martes 13:30 (cierre exacto)' ),
	array( '2026-09-22 11:59:59', false, 'martes 11:59 (antes)' ),
	array( '2026-09-26 12:30:00', true,  'sabado 12:30' ),
	array( '2026-09-27 12:30:00', false, 'domingo 12:30' ),
	array( '2026-09-22 23:00:00', false, 'martes de noche' ),
);
foreach ( $casos as $c ) {
	$momento = new DateTimeImmutable( $c[0], $tz );
	comprobar( $c[2], $c[1], NavidadTVS_Plugin::ventana_abierta( $momento ) );
}

echo "\n--- congelamiento ---\n";
$settings = NavidadTVS_Plugin::instancia()->settings;
$settings->set( array( 'concurso_congelado' => true ) );
$dentro = new DateTimeImmutable( '2026-09-22 12:30:00', $tz );
comprobar( 'congelado ignora la hora', false, NavidadTVS_Plugin::ventana_abierta( $dentro ) );
$settings->set( array( 'concurso_congelado' => false ) );
comprobar( 'descongelado vuelve a abrir', true, NavidadTVS_Plugin::ventana_abierta( $dentro ) );

echo "\n--- tablas ---\n";
$db = NavidadTVS_Plugin::instancia()->database;
foreach ( $db->estado_tablas() as $tabla => $existe ) {
	comprobar( "existe {$tabla}", true, $existe );
}

echo "\n--- constantes de simulacion ---\n";
comprobar( 'total de ticks', 5400, NAVIDAD_TVS_TOTAL_TICKS );

echo "\n";
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
