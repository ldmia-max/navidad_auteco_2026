<?php
/**
 * Comprueba la búsqueda y la edición del padrón.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-participantes.php
 *
 * ESCRIBE EN LA BASE DE DATOS, pero solo sobre filas suyas: inserta tres
 * participantes de prueba con teléfonos del rango 39xxxxxxxx, cédulas 99xxxxx
 * y placas ZZZ..., y los borra al terminar pase lo que pase. No toca ninguna
 * fila importada.
 *
 * Script de desarrollo: nunca en producción.
 *
 * @package NavidadTVS
 */

$db = NavidadTVS_Plugin::instancia()->database;

require_once NAVIDAD_TVS_PATH . 'includes/admin/class-participantes.php';

global $wpdb;

/**
 * Comprueba una condición y lleva la cuenta.
 *
 * Los contadores van en estáticas y no en variables globales: `wp eval-file`
 * ejecuta el archivo DENTRO de una función, así que lo que aquí parece ámbito
 * global no lo es, y el `global $fallos` de comprobar() apuntaba a otra
 * variable. El resumen salía "0 comprobaciones, 0 fallos" teniendo fallos en
 * pantalla, que es la peor forma de fallar: en verde.
 *
 * @param string $que     Qué se comprueba.
 * @param bool   $ok      Resultado.
 * @param string $detalle Contexto.
 */
function comprobar( $que, $ok, $detalle = '' ) {
	cuenta( $ok ? 'ok' : 'falla' );

	printf( "%-5s %s%s\n", $ok ? 'OK' : 'FALLA', $que, '' !== $detalle ? "  {$detalle}" : '' );
}

/**
 * Acumula el marcador.
 *
 * @param string $que 'ok', 'falla' o 'resumen'.
 * @return array{hechas:int, fallos:int}
 */
function cuenta( $que ) {
	static $hechas = 0;
	static $fallos = 0;

	if ( 'resumen' !== $que ) {
		$hechas++;

		if ( 'falla' === $que ) {
			$fallos++;
		}
	}

	return array(
		'hechas' => $hechas,
		'fallos' => $fallos,
	);
}

// --- Filas de prueba, marcadas para poder borrarlas sin dudas --------------
$hoy    = NavidadTVS_Plugin::hoy();
$manana = gmdate( 'Y-m-d', strtotime( $hoy . ' +1 day' ) );

$semillas = array(
	array(
		'telefono'     => '3900000001',
		'telefono_csv' => '573900000001',
		'cedula'       => '9900001',
		'placa'        => 'ZZZ001',
		'fecha_concurso' => $manana,
		'marca'        => 'TVS',
	),
	array(
		'telefono'     => '3900000002',
		'telefono_csv' => '573900000002',
		'cedula'       => '9900002',
		'placa'        => 'ZZZ002',
		'fecha_concurso' => $hoy,
		'marca'        => 'TVS',
	),
);

$ids = array();

/** Borra las filas de prueba. */
function limpiar( $db, $ids ) {
	global $wpdb;

	foreach ( $ids as $id ) {
		$wpdb->delete( $db->tabla_participantes, array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore
	}
}

// Por si una corrida anterior murió a medias.
$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE placa LIKE 'ZZZ%'" ); // phpcs:ignore

foreach ( $semillas as $fila ) {
	$wpdb->insert( $db->tabla_participantes, $fila ); // phpcs:ignore
	$ids[] = (int) $wpdb->insert_id;
}

printf( "Insertadas %d filas de prueba. Hoy: %s\n\n", count( $ids ), $hoy );

try {
	// --- Búsqueda -----------------------------------------------------------
	echo "=== Búsqueda ===\n";

	$r = $db->buscar_participantes( '3900000001' );
	comprobar( 'encuentra por teléfono de diez dígitos', 1 === count( $r ) && 'ZZZ001' === $r[0]['placa'] );

	$r = $db->buscar_participantes( '573900000001' );
	comprobar( 'encuentra por el teléfono de doce del archivo', 1 === count( $r ) && 'ZZZ001' === $r[0]['placa'] );

	$r = $db->buscar_participantes( '390 000 0001' );
	comprobar( 'ignora los espacios del teléfono', 1 === count( $r ) );

	$r = $db->buscar_participantes( '9900002' );
	comprobar( 'encuentra por cédula', 1 === count( $r ) && '3900000002' === $r[0]['telefono'] );

	$r = $db->buscar_participantes( 'zzz001' );
	comprobar( 'encuentra por placa en minúsculas', 1 === count( $r ) );

	$r = $db->buscar_participantes( 'ZZZ-001' );
	comprobar( 'ignora el guion de la placa', 1 === count( $r ) );

	$r = $db->buscar_participantes( '' );
	comprobar( 'una búsqueda vacía no devuelve el padrón entero', array() === $r, count( $r ) . ' filas' );

	$r = $db->buscar_participantes( '   ' );
	comprobar( 'solo espacios tampoco', array() === $r, count( $r ) . ' filas' );

	$r = $db->buscar_participantes( "' OR 1=1 -- " );
	comprobar( 'una inyección no devuelve nada', array() === $r, count( $r ) . ' filas' );

	// --- Edición ------------------------------------------------------------
	echo "\n=== Edición ===\n";

	$res = $db->actualizar_participante( $ids[0], array( 'fecha_concurso' => $hoy ) );
	comprobar( 'cambia la jornada', true === $res, is_wp_error( $res ) ? $res->get_error_message() : '' );

	$fila = $db->buscar_participante( $ids[0] );
	comprobar( 'el cambio queda guardado', $hoy === $fila['fecha_concurso'], $fila['fecha_concurso'] );

	$res = $db->actualizar_participante( $ids[0], array( 'ciudad_propietario' => 'Medellín' ) );
	$fila = $db->buscar_participante( $ids[0] );
	comprobar( 'cambia un campo suelto sin tocar los demás', 'Medellín' === $fila['ciudad_propietario'] && 'ZZZ001' === $fila['placa'] );

	// --- Choques con las claves únicas --------------------------------------
	echo "\n=== Claves únicas ===\n";

	$res = $db->actualizar_participante( $ids[0], array( 'telefono' => '3900000002' ) );
	comprobar( 'rechaza un teléfono que ya tiene otro', is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_message() : 'lo aceptó' );

	$fila = $db->buscar_participante( $ids[0] );
	comprobar( 'y no deja el registro a medias', '3900000001' === $fila['telefono'], $fila['telefono'] );

	$res = $db->actualizar_participante( $ids[0], array( 'cedula' => '9900002' ) );
	comprobar( 'rechaza una cédula repetida', is_wp_error( $res ) );

	$res = $db->actualizar_participante( $ids[0], array( 'placa' => 'ZZZ002' ) );
	comprobar( 'rechaza una placa repetida', is_wp_error( $res ) );

	$res = $db->actualizar_participante( $ids[0], array( 'telefono' => '3900000001' ) );
	comprobar( 'guardar el mismo teléfono no se toma como choque', true === $res );

	$res = $db->actualizar_participante( 999999999, array( 'placa' => 'ZZZ099' ) );
	comprobar( 'rechaza un id que no existe', is_wp_error( $res ) );

	$res = $db->actualizar_participante( $ids[0], array() );
	comprobar( 'rechaza un guardado sin cambios', is_wp_error( $res ) );

	// --- Saneado de los campos ----------------------------------------------
	echo "\n=== Saneado ===\n";

	$pantalla = new NavidadTVS_Participantes( $db );
	$sanear   = new ReflectionMethod( $pantalla, 'sanear' );
	$sanear->setAccessible( true );

	$casos = array(
		array( 'telefono', '573504567217', '3504567217', 'quita el indicativo 57' ),
		array( 'telefono', '350 456 7217', '3504567217', 'quita los espacios' ),
		array( 'telefono', '6012345678', null, 'rechaza un fijo' ),
		array( 'telefono', '35045672', null, 'rechaza uno corto' ),
		array( 'placa', 'abc-12d', 'ABC12D', 'pasa la placa a mayúsculas y quita el guion' ),
		array( 'placa', 'AB1', null, 'rechaza una placa corta' ),
		array( 'cedula', '1.032.456.789', '1032456789', 'quita los puntos de la cédula' ),
		array( 'cedula', '12', null, 'rechaza una cédula corta' ),
		array( 'fecha_concurso', '2026-12-24', '2026-12-24', 'acepta una fecha válida' ),
		array( 'fecha_concurso', '24/12/2026', null, 'rechaza el formato con barras' ),
		array( 'fecha_concurso', '2026-02-31', null, 'rechaza un 31 de febrero' ),
		array( 'estado', 'descalificado', 'descalificado', 'acepta descalificado' ),
		array( 'estado', 'cualquier-cosa', 'habilitado', 'un estado desconocido cae en habilitado' ),
	);

	foreach ( $casos as $caso ) {
		list( $campo, $entra, $espera, $que ) = $caso;
		$salida = $sanear->invoke( $pantalla, $campo, $entra );

		if ( null === $espera ) {
			comprobar( $que, is_wp_error( $salida ), is_wp_error( $salida ) ? '' : "devolvió '{$salida}'" );
		} else {
			comprobar( $que, $espera === $salida, is_wp_error( $salida ) ? $salida->get_error_message() : "'{$salida}'" );
		}
	}

	// --- Diagnóstico del intento --------------------------------------------
	echo "\n=== Diagnóstico ===\n";

	$estado_intento = new ReflectionMethod( $pantalla, 'estado_intento' );
	$estado_intento->setAccessible( true );

	$d = $estado_intento->invoke( $pantalla, $ids[1] );
	comprobar( 'con jornada de hoy y sin jugar, puede', ! empty( $d['puede'] ), implode( ' / ', $d['motivos'] ) );

	$db->actualizar_participante( $ids[1], array( 'fecha_concurso' => $manana ) );
	$d = $estado_intento->invoke( $pantalla, $ids[1] );
	comprobar( 'con la jornada de otro día, no puede', empty( $d['puede'] ) );
	comprobar( 'y el motivo habla de la jornada', false !== strpos( implode( ' ', $d['motivos'] ), 'jornada' ) );

	$db->actualizar_participante( $ids[1], array( 'fecha_concurso' => $hoy, 'estado' => 'descalificado' ) );
	$d = $estado_intento->invoke( $pantalla, $ids[1] );
	comprobar( 'descalificado tampoco puede', empty( $d['puede'] ) );

	$db->actualizar_participante( $ids[1], array( 'estado' => 'habilitado' ) );

	// Una sesión consumida tiene que bloquear aunque todo lo demás esté bien.
	$wpdb->insert(
		$db->tabla_sesiones,
		array(
			'participante_id' => $ids[1],
			'nonce'           => 'prueba-' . wp_generate_password( 12, false ),
			'seed'            => 123,
			'estado'          => 'consumida',
			'nombre_digitado'          => 'Prueba',
		)
	); // phpcs:ignore

	$d = $estado_intento->invoke( $pantalla, $ids[1] );
	comprobar( 'con el intento gastado, no puede', empty( $d['puede'] ) );
	comprobar( 'y se avisa de que esto no se arregla cambiando la fecha', ! empty( $d['ya_jugo'] ) );

	$wpdb->delete( $db->tabla_sesiones, array( 'participante_id' => $ids[1] ), array( '%d' ) ); // phpcs:ignore

} finally {
	limpiar( $db, $ids );
	$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE placa LIKE 'ZZZ%'" ); // phpcs:ignore
	echo "\nFilas de prueba borradas.\n";
}

$marcador = cuenta( 'resumen' );

printf(
	"\n%s  %d comprobaciones, %d fallos\n",
	0 === $marcador['fallos'] ? 'TODO OK' : 'HAY FALLOS',
	$marcador['hechas'],
	$marcador['fallos']
);
