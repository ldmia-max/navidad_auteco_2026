<?php
/**
 * Comprueba el teléfono de pruebas.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-modo-pruebas.php
 *
 * Son dos cosas las que hay que demostrar, y la segunda es la que importa:
 *
 *   1. Que el número de pruebas entra fuera de horario, fuera de su jornada y
 *      cuantas veces quiera.
 *   2. Que NADA de lo que juega se guarda, y que un número normal sigue con
 *      sus reglas de siempre.
 *
 * Sin la segunda, esto no sería un modo de pruebas sino un agujero.
 *
 * Todo va por HTTP contra los endpoints de verdad. Comprobar los métodos por
 * dentro no serviría: lo que se quiere saber es qué le pasa a quien entra.
 *
 * ESCRIBE EN LA BASE y borra lo suyo al terminar pase lo que pase. Solo toca
 * filas con placa ZZZ… y deja el ajuste del teléfono de pruebas como estaba.
 *
 * Script de desarrollo: nunca en producción.
 *
 * @package NavidadTVS
 */

$db  = NavidadTVS_Plugin::instancia()->database;
$cfg = NavidadTVS_Plugin::instancia()->settings;

require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-simulacion.php';

global $wpdb;

/**
 * Comprueba una condición y lleva la cuenta.
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

/**
 * Una petición al API del concurso.
 *
 * @param string     $url    Destino.
 * @param array|null $cuerpo Cuerpo JSON, o null para GET.
 * @return array{codigo:int, datos:array}
 */
function pedir( $url, $cuerpo = null ) {
	$ch = curl_init( $url );

	$o = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
	);

	if ( null !== $cuerpo ) {
		$o[ CURLOPT_POST ]       = true;
		$o[ CURLOPT_POSTFIELDS ] = wp_json_encode( $cuerpo );
		$o[ CURLOPT_HTTPHEADER ] = array( 'Content-Type: application/json' );
	}

	curl_setopt_array( $ch, $o );

	$texto  = curl_exec( $ch );
	$codigo = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );

	curl_close( $ch );

	$datos = json_decode( (string) $texto, true );

	return array(
		'codigo' => $codigo,
		'datos'  => is_array( $datos ) ? $datos : array(),
	);
}

/**
 * Juega una carrera entera por HTTP.
 *
 * @param string $base     Raíz del API.
 * @param object $db       Acceso a datos.
 * @param string $telefono Teléfono.
 * @param string $nombre   Nombre a digitar.
 * @return array{acceso:array, iniciar:array, terminar:array}
 */
function jugar( $base, $db, $telefono, $nombre ) {
	global $wpdb;

	$vacio = array( 'codigo' => 0, 'datos' => array() );

	$acceso = pedir( $base . '/acceso', array( 'nombre' => $nombre, 'telefono' => $telefono, 'acepta' => true ) );

	if ( empty( $acceso['datos']['token'] ) ) {
		return array( 'acceso' => $acceso, 'iniciar' => $vacio, 'terminar' => $vacio );
	}

	$token   = $acceso['datos']['token'];
	$iniciar = pedir( $base . '/carrera/iniciar', array( 'token' => $token ) );

	if ( ! isset( $iniciar['datos']['seed'] ) ) {
		return array( 'acceso' => $acceso, 'iniciar' => $iniciar, 'terminar' => $vacio );
	}

	$ticks    = NAVIDAD_TVS_TOTAL_TICKS;
	$registro = array();

	for ( $t = 0; $t < $ticks; $t++ ) {
		$registro[] = 1 | ( ( ( $t % 300 ) < 90 ) ? 2 : 0 );
	}

	$b64    = NavidadTVS_Sim_Entradas::codificar( $registro );
	$estado = NavidadTVS_Sim_Simulacion::simular( (int) $iniciar['datos']['seed'], NavidadTVS_Sim_Entradas::decodificar( $b64, $ticks ), $ticks );
	$dist   = NavidadTVS_Sim_Simulacion::distancia_metros( $estado );

	// La carrera se retrasa noventa segundos para que la malla de
	// plausibilidad no la tumbe por llegar en cero.
	$wpdb->query( // phpcs:ignore
		$wpdb->prepare(
			"UPDATE {$db->tabla_sesiones}
			    SET consumida_en = ( UTC_TIMESTAMP() - INTERVAL %d SECOND )
			  WHERE nonce = %s", // phpcs:ignore WordPress.DB.PreparedSQL
			NAVIDAD_TVS_DURACION_SEGUNDOS,
			$token
		)
	);

	$terminar = pedir( $base . '/carrera/terminar', array( 'token' => $token, 'entradas' => $b64, 'distancia' => $dist ) );

	return array( 'acceso' => $acceso, 'iniciar' => $iniciar, 'terminar' => $terminar );
}

/** Borra lo que creó esta corrida. */
function limpiar( $db ) {
	global $wpdb;

	foreach ( array( $db->tabla_scores, $db->tabla_sesiones ) as $tabla ) {
		$wpdb->query( // phpcs:ignore
			"DELETE t FROM {$tabla} t
			  JOIN {$db->tabla_participantes} p ON p.id = t.participante_id
			 WHERE p.placa LIKE 'ZZZ%'"
		);
	}

	$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE placa LIKE 'ZZZ%'" ); // phpcs:ignore
}

// ---------------------------------------------------------------------------

$hoy   = NavidadTVS_Plugin::hoy();
$ayer  = gmdate( 'Y-m-d', strtotime( $hoy . ' -1 day' ) );
$base  = 'http://wordpress/wp-json/' . NAVIDAD_TVS_REST_NS;

$antes = array(
	'telefono_pruebas'   => $cfg->get( 'telefono_pruebas' ),
	'hora_inicio'        => $cfg->get( 'hora_inicio' ),
	'hora_fin'           => $cfg->get( 'hora_fin' ),
	'dias_habiles'       => $cfg->get( 'dias_habiles' ),
	'concurso_congelado' => $cfg->get( 'concurso_congelado' ),
	'fecha_inicio'       => $cfg->get( 'fecha_inicio' ),
	'fecha_fin'          => $cfg->get( 'fecha_fin' ),
);

limpiar( $db );

/*
 * Dos personajes, los dos con jornada de AYER para que ninguno pueda jugar
 * por la vía normal:
 *   P — será el teléfono de pruebas.
 *   N — un participante cualquiera, el grupo de control.
 */
$gente = array( 'P' => '3930000001', 'N' => '3930000002' );
$ids   = array();
$i     = 0;

foreach ( $gente as $clave => $telefono ) {
	$i++;

	$wpdb->insert( // phpcs:ignore
		$db->tabla_participantes,
		array(
			'telefono'                 => $telefono,
			'telefono_csv'             => '57' . $telefono,
			'cedula'                   => '96' . str_pad( (string) $i, 7, '0', STR_PAD_LEFT ),
			'placa'                    => 'ZZZ' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ),
			'fecha_concurso'           => $ayer,
			'marca'                    => 'TVS',
			'ciudad_propietario'       => 'Medellín',
			'departamento_propietario' => 'Antioquia',
		)
	);

	$ids[ $clave ] = (int) $wpdb->insert_id;

	NavidadTVS_Rate_Limit::limpiar( 'tel:' . $telefono );
}

NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );

printf( "hoy=%s   jornada de los dos=%s (o sea, ya pasó)\n\n", $hoy, $ayer );

/*
 * La ventana se deja CERRADA a propósito: es media de las condiciones que el
 * teléfono de pruebas tiene que poder saltarse.
 */
$cfg->set(
	array(
		'hora_inicio'        => '03:00',
		'hora_fin'           => '03:01',
		// Sin rango de fechas en todo el guion. Más abajo se abre la ventana
		// para el grupo de control, y un rango puesto la dejaría cerrada por
		// un motivo que no es el que se está probando.
		'fecha_inicio'       => '',
		'fecha_fin'          => '',
		'dias_habiles'       => array( 1, 2, 3, 4, 5, 6, 7 ),
		'concurso_congelado' => false,
		'telefono_pruebas'   => '',
	)
);

try {
	// --- Sin teléfono de pruebas configurado --------------------------------
	echo "=== Con el modo apagado ===\n";

	comprobar( 'la ventana está cerrada', ! NavidadTVS_Plugin::ventana_abierta() );

	$r = pedir( $base . '/acceso', array( 'nombre' => 'P', 'telefono' => $gente['P'], 'acepta' => true ) );
	comprobar( 'nadie entra, tampoco el futuro número de pruebas', 403 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	comprobar( 'y el ayudante dice que no hay número de pruebas', ! NavidadTVS_Plugin::es_telefono_de_pruebas( $gente['P'] ) );

	// --- Con el teléfono de pruebas puesto -----------------------------------
	echo "\n=== Con el modo encendido ===\n";

	$cfg->set( array( 'telefono_pruebas' => $gente['P'] ) );

	comprobar( 'el ayudante reconoce el número', NavidadTVS_Plugin::es_telefono_de_pruebas( $gente['P'] ) );
	comprobar( 'y lo reconoce escrito con indicativo', NavidadTVS_Plugin::es_telefono_de_pruebas( '57' . $gente['P'] ) );
	comprobar( 'pero no confunde a otro', ! NavidadTVS_Plugin::es_telefono_de_pruebas( $gente['N'] ) );

	$r = jugar( $base, $db, $gente['P'], 'Disenador' );
	comprobar( 'P entra con la ventana cerrada y fuera de su jornada', 200 === $r['acceso']['codigo'], 'HTTP ' . $r['acceso']['codigo'] . ' ' . ( $r['acceso']['datos']['message'] ?? '' ) );
	comprobar( 'y el servidor avisa de que es una sesión de pruebas', ! empty( $r['acceso']['datos']['pruebas'] ) );
	comprobar( 'arranca la carrera', 200 === $r['iniciar']['codigo'], 'HTTP ' . $r['iniciar']['codigo'] );
	comprobar( 'y la termina', 200 === $r['terminar']['codigo'], 'HTTP ' . $r['terminar']['codigo'] );
	comprobar( 'el resultado viene marcado como de pruebas', ! empty( $r['terminar']['datos']['pruebas'] ) );
	comprobar( 'y trae su distancia, para que se vea en pantalla', ! empty( $r['terminar']['datos']['distancia'] ), (string) ( $r['terminar']['datos']['distancia'] ?? '' ) );

	// --- LO QUE IMPORTA: no queda rastro -------------------------------------
	echo "\n=== Que no se guarde nada ===\n";

	$scores_p = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$db->tabla_scores} WHERE participante_id = %d", $ids['P'] ) ); // phpcs:ignore
	comprobar( 'NO se guardó ningún resultado de P', 0 === $scores_p, $scores_p . ' filas' );

	$ranking = $db->ranking_rango( '', '', 1, 500, true );
	$nombres = wp_list_pluck( $ranking, 'nombre' );
	comprobar( 'P no aparece en el ranking', ! in_array( 'Disenador', $nombres, true ) );

	comprobar( 'ni puede ser ganador', ! $db->es_ganador( $ids['P'] ) );

	$informe = $db->informe_por_participante();
	$fila_p  = null;

	foreach ( $informe as $f ) {
		if ( (int) $f['id'] === $ids['P'] ) {
			$fila_p = $f;
			break;
		}
	}

	comprobar( 'en el informe general figura con cero intentos', $fila_p && 0 === (int) $fila_p['intentos'], $fila_p ? $fila_p['intentos'] . ' intentos' : 'no está' );

	// --- Repetir, que es para lo que existe ----------------------------------
	echo "\n=== Repetir ===\n";

	$veces = 0;

	for ( $v = 0; $v < 3; $v++ ) {
		$r = jugar( $base, $db, $gente['P'], 'Disenador ' . $v );

		if ( 200 === $r['terminar']['codigo'] ) {
			$veces++;
		}
	}

	comprobar( 'P juega tres carreras seguidas sin que nadie lo pare', 3 === $veces, $veces . ' de 3' );

	$scores_p = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$db->tabla_scores} WHERE participante_id = %d", $ids['P'] ) ); // phpcs:ignore
	comprobar( 'y sigue sin guardarse ni una', 0 === $scores_p, $scores_p . ' filas' );

	// --- El grupo de control --------------------------------------------------
	echo "\n=== Un número normal no cambia ===\n";

	$r = pedir( $base . '/acceso', array( 'nombre' => 'N', 'telefono' => $gente['N'], 'acepta' => true ) );
	comprobar( 'N sigue sin poder entrar con la ventana cerrada', 403 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	// Se abre la ventana y se le pone jornada de hoy: ahora debe entrar, jugar
	// UNA vez y que su resultado sí quede.
	$cfg->set( array( 'hora_inicio' => '00:00', 'hora_fin' => '23:59' ) );
	$wpdb->update( $db->tabla_participantes, array( 'fecha_concurso' => $hoy ), array( 'id' => $ids['N'] ) ); // phpcs:ignore
	NavidadTVS_Rate_Limit::limpiar( 'tel:' . $gente['N'] );

	$r = jugar( $base, $db, $gente['N'], 'Comprador' );
	comprobar( 'N juega en su jornada', 200 === $r['terminar']['codigo'], 'HTTP ' . $r['terminar']['codigo'] );
	comprobar( 'y su resultado NO viene marcado como de pruebas', empty( $r['terminar']['datos']['pruebas'] ) );

	$scores_n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$db->tabla_scores} WHERE participante_id = %d", $ids['N'] ) ); // phpcs:ignore
	comprobar( 'su carrera SÍ se guarda', 1 === $scores_n, $scores_n . ' filas' );

	$r = pedir( $base . '/acceso', array( 'nombre' => 'N otra vez', 'telefono' => $gente['N'], 'acepta' => true ) );
	comprobar( 'y sigue teniendo un solo intento', 409 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	// --- Apagar el modo -------------------------------------------------------
	echo "\n=== Al apagar el modo ===\n";

	$cfg->set( array( 'telefono_pruebas' => '', 'hora_inicio' => '03:00', 'hora_fin' => '03:01' ) );
	NavidadTVS_Rate_Limit::limpiar( 'tel:' . $gente['P'] );

	comprobar( 'el ayudante deja de reconocer el número', ! NavidadTVS_Plugin::es_telefono_de_pruebas( $gente['P'] ) );

	$r = pedir( $base . '/acceso', array( 'nombre' => 'P', 'telefono' => $gente['P'], 'acepta' => true ) );
	comprobar( 'y P vuelve a quedarse fuera', 403 === $r['codigo'], 'HTTP ' . $r['codigo'] );

} finally {
	$cfg->set( $antes );
	limpiar( $db );
	echo "\nAjustes restaurados y filas de prueba borradas.\n";
}

$marcador = cuenta( 'resumen' );

printf(
	"\n%s  %d comprobaciones, %d fallos\n",
	0 === $marcador['fallos'] ? 'TODO OK' : 'HAY FALLOS',
	$marcador['hechas'],
	$marcador['fallos']
);
