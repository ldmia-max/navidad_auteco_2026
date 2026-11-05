<?php
/**
 * Verificación manual de E1. Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e1.php
 *
 * No escribe nada en la base de datos.
 */

$GLOBALS['fallos'] = 0;

function comprobar( $etiqueta, $esperado, $obtenido ) {
	$ok = ( $esperado === $obtenido );
	if ( ! $ok ) {
		$GLOBALS['fallos']++;
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

/*
 * Se fija el horario estándar antes de comprobarlo. Los scripts de otras
 * etapas mueven la configuración para poder probar con la ventana abierta, y
 * estas comprobaciones tienen que valer sin importar cómo la dejaron.
 */
$settings_e1   = NavidadTVS_Plugin::instancia()->settings;
$config_previa = array(
	'hora_inicio'        => $settings_e1->get( 'hora_inicio' ),
	'hora_fin'           => $settings_e1->get( 'hora_fin' ),
	'dias_habiles'       => $settings_e1->get( 'dias_habiles' ),
	'concurso_congelado' => $settings_e1->get( 'concurso_congelado' ),
	'fecha_inicio'       => $settings_e1->get( 'fecha_inicio' ),
	'fecha_fin'          => $settings_e1->get( 'fecha_fin' ),
);
$settings_e1->set(
	array(
		'hora_inicio'        => '12:00',
		'hora_fin'           => '13:30',
		'dias_habiles'       => array( 1, 2, 3, 4, 5, 6 ),
		'concurso_congelado' => false,
		// Sin rango: estas comprobaciones son sobre la hora y el día. Si
		// quedara puesto el rango real del concurso, las fechas de septiembre
		// de abajo caerían fuera y fallarían todas por el motivo equivocado.
		'fecha_inicio'       => '',
		'fecha_fin'          => '',
	)
);

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

echo "\n--- rango de fechas del concurso ---\n";

/*
 * Todos los momentos de aquí abajo son martes a las 12:30, o sea dentro del
 * horario y en día hábil. Lo único que cambia la respuesta es la fecha, que
 * es justo lo que se está probando.
 */
$martes_1  = new DateTimeImmutable( '2026-12-01 12:30:00', $tz );
$martes_8  = new DateTimeImmutable( '2026-12-08 12:30:00', $tz );
$martes_15 = new DateTimeImmutable( '2026-12-15 12:30:00', $tz );
$martes_22 = new DateTimeImmutable( '2026-12-22 12:30:00', $tz );

$settings->set(
	array(
		'fecha_inicio' => '2026-12-08',
		'fecha_fin'    => '2026-12-15',
	)
);

comprobar( 'antes del inicio (1 dic)', false, NavidadTVS_Plugin::ventana_abierta( $martes_1 ) );
comprobar( 'primer dia (8 dic, entra)', true, NavidadTVS_Plugin::ventana_abierta( $martes_8 ) );
comprobar( 'ultimo dia (15 dic, entra)', true, NavidadTVS_Plugin::ventana_abierta( $martes_15 ) );
comprobar( 'despues del final (22 dic)', false, NavidadTVS_Plugin::ventana_abierta( $martes_22 ) );

// El último día se juega la jornada entera, hasta la hora de cierre.
$ultimo_tarde = new DateTimeImmutable( '2026-12-15 13:29:00', $tz );
$ultimo_cierre = new DateTimeImmutable( '2026-12-15 13:30:00', $tz );
comprobar( 'ultimo dia 13:29 (entra)', true, NavidadTVS_Plugin::ventana_abierta( $ultimo_tarde ) );
comprobar( 'ultimo dia 13:30 (cierra igual)', false, NavidadTVS_Plugin::ventana_abierta( $ultimo_cierre ) );

// Un solo extremo: el otro queda sin tope.
$settings->set( array( 'fecha_inicio' => '2026-12-08', 'fecha_fin' => '' ) );
comprobar( 'solo inicio: antes no', false, NavidadTVS_Plugin::ventana_abierta( $martes_1 ) );
comprobar( 'solo inicio: mucho despues si', true, NavidadTVS_Plugin::ventana_abierta( $martes_22 ) );

$settings->set( array( 'fecha_inicio' => '', 'fecha_fin' => '2026-12-15' ) );
comprobar( 'solo final: mucho antes si', true, NavidadTVS_Plugin::ventana_abierta( $martes_1 ) );
comprobar( 'solo final: despues no', false, NavidadTVS_Plugin::ventana_abierta( $martes_22 ) );

// Concurso de un solo día: las dos fechas iguales.
$settings->set( array( 'fecha_inicio' => '2026-12-08', 'fecha_fin' => '2026-12-08' ) );
comprobar( 'un solo dia: ese dia', true, NavidadTVS_Plugin::ventana_abierta( $martes_8 ) );
comprobar( 'un solo dia: el siguiente no', false, NavidadTVS_Plugin::ventana_abierta( $martes_15 ) );

// Sin rango no hay restriccion por fecha.
$settings->set( array( 'fecha_inicio' => '', 'fecha_fin' => '' ) );
comprobar( 'sin rango: cualquier dia habil', true, NavidadTVS_Plugin::ventana_abierta( $martes_1 ) );

// El congelamiento manda sobre el rango: dentro de fechas y aun asi cerrado.
$settings->set(
	array(
		'fecha_inicio'       => '2026-12-08',
		'fecha_fin'          => '2026-12-15',
		'concurso_congelado' => true,
	)
);
comprobar( 'congelado dentro del rango', false, NavidadTVS_Plugin::ventana_abierta( $martes_8 ) );
$settings->set( array( 'concurso_congelado' => false ) );

$settings_e1->set( $config_previa );

echo "\n--- tablas ---\n";
$db = NavidadTVS_Plugin::instancia()->database;
foreach ( $db->estado_tablas() as $tabla => $existe ) {
	comprobar( "existe {$tabla}", true, $existe );
}

echo "\n--- constantes de simulacion ---\n";
comprobar( 'total de ticks', 5400, NAVIDAD_TVS_TOTAL_TICKS );

echo "\n";
$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
