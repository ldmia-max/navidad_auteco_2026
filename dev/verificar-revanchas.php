<?php
/**
 * Comprueba las revanchas, el intento por jornada y la marca de ganador.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-revanchas.php
 *
 * Es la suite más importante del backoffice, porque aquí se decide quién
 * puede jugar y quién no. Un fallo en esta lógica no se ve en pantalla: se ve
 * cuando alguien juega dos veces el mismo día, o cuando a quien tenía derecho
 * a revancha no le deja entrar.
 *
 * Las comprobaciones van por HTTP donde se puede, contra los endpoints de
 * verdad, y no llamando a los métodos por dentro: lo que importa es lo que le
 * pasa al participante, no lo que devuelve una función.
 *
 * ESCRIBE EN LA BASE y borra lo suyo al terminar pase lo que pase. Solo toca
 * filas con placa ZZZ… y sus revanchas.
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
 * Juega una carrera entera por HTTP y devuelve lo que respondió el servidor.
 *
 * La sesión se retrasa noventa segundos a mano para que la malla de
 * plausibilidad no la tumbe: una carrera de noventa segundos no puede llegar
 * en cero, y esperarlos de verdad haría la suite inservible.
 *
 * @param string $base     Raíz del API.
 * @param object $db       Acceso a datos.
 * @param string $telefono Teléfono del participante.
 * @param string $nombre   Nombre a digitar.
 * @return array{acceso:array, iniciar:array, terminar:array}
 */
function jugar( $base, $db, $telefono, $nombre ) {
	global $wpdb;

	$acceso = pedir( $base . '/acceso', array( 'nombre' => $nombre, 'telefono' => $telefono, 'acepta' => true ) );

	if ( empty( $acceso['datos']['token'] ) ) {
		return array( 'acceso' => $acceso, 'iniciar' => array(), 'terminar' => array() );
	}

	$token   = $acceso['datos']['token'];
	$iniciar = pedir( $base . '/carrera/iniciar', array( 'token' => $token ) );

	if ( ! isset( $iniciar['datos']['seed'] ) ) {
		return array( 'acceso' => $acceso, 'iniciar' => $iniciar, 'terminar' => array() );
	}

	$ticks    = NAVIDAD_TVS_TOTAL_TICKS;
	$registro = array();

	for ( $t = 0; $t < $ticks; $t++ ) {
		$registro[] = 1 | ( ( ( $t % 300 ) < 90 ) ? 2 : 0 );
	}

	$b64    = NavidadTVS_Sim_Entradas::codificar( $registro );
	$estado = NavidadTVS_Sim_Simulacion::simular( (int) $iniciar['datos']['seed'], NavidadTVS_Sim_Entradas::decodificar( $b64, $ticks ), $ticks );
	$dist   = NavidadTVS_Sim_Simulacion::distancia_metros( $estado );

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

/** Borra todo lo que creó esta corrida. */
function limpiar( $db ) {
	global $wpdb;

	$wpdb->query( // phpcs:ignore
		"DELETE r FROM {$db->tabla_revanchas} r
		  JOIN {$db->tabla_participantes} p ON p.id = r.participante_id
		 WHERE p.placa LIKE 'ZZZ%'"
	);
	$wpdb->query( // phpcs:ignore
		"DELETE s FROM {$db->tabla_scores} s
		  JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		 WHERE p.placa LIKE 'ZZZ%'"
	);
	$wpdb->query( // phpcs:ignore
		"DELETE s FROM {$db->tabla_sesiones} s
		  JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		 WHERE p.placa LIKE 'ZZZ%'"
	);
	$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE placa LIKE 'ZZZ%'" ); // phpcs:ignore
}

// ---------------------------------------------------------------------------

$hoy    = NavidadTVS_Plugin::hoy();
$ayer   = gmdate( 'Y-m-d', strtotime( $hoy . ' -1 day' ) );
$manana = gmdate( 'Y-m-d', strtotime( $hoy . ' +1 day' ) );
$base   = 'http://wordpress/wp-json/' . NAVIDAD_TVS_REST_NS;

$ventana_previa = array(
	'hora_inicio'        => $cfg->get( 'hora_inicio' ),
	'hora_fin'           => $cfg->get( 'hora_fin' ),
	'dias_habiles'       => $cfg->get( 'dias_habiles' ),
	'concurso_congelado' => $cfg->get( 'concurso_congelado' ),
);

limpiar( $db );

/*
 * Cuatro personajes:
 *   A — jornada de hoy. El caso normal.
 *   B — jornada de AYER: ya pasó su día. Candidato natural a revancha.
 *   C — jornada de ayer y ganador. No debería volver.
 *   D — jornada de MAÑANA: todavía no le toca. Una revancha no le adelanta.
 */
$gente = array(
	'A' => array( 'fecha' => $hoy ),
	'B' => array( 'fecha' => $ayer ),
	'C' => array( 'fecha' => $ayer ),
	'D' => array( 'fecha' => $manana ),
);

$i = 0;

foreach ( $gente as $clave => $datos ) {
	$i++;
	$telefono = '39' . str_pad( (string) ( 20000000 + $i ), 8, '0', STR_PAD_LEFT );

	$wpdb->insert( // phpcs:ignore
		$db->tabla_participantes,
		array(
			'telefono'                 => $telefono,
			'telefono_csv'             => '57' . $telefono,
			'cedula'                   => '98' . str_pad( (string) $i, 7, '0', STR_PAD_LEFT ),
			'placa'                    => 'ZZZ' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ),
			'fecha_concurso'           => $datos['fecha'],
			'marca'                    => 'TVS',
			'ciudad_propietario'       => 'Medellín',
			'departamento_propietario' => 'Antioquia',
		)
	);

	$gente[ $clave ]['id']       = (int) $wpdb->insert_id;
	$gente[ $clave ]['telefono'] = $telefono;
}

/*
 * El limitador se reinicia para estos teléfonos.
 *
 * La suite provoca varios rechazos a propósito —"hoy no es tu jornada"— y esos
 * cuentan para el límite por teléfono. Al repetir la suite varias veces
 * seguidas, el quinto rechazo devolvía 429 en vez de 403 y la prueba fallaba
 * por el limitador haciendo bien su trabajo, no por un defecto del código.
 */
foreach ( $gente as $datos ) {
	NavidadTVS_Rate_Limit::limpiar( 'tel:' . $datos['telefono'] );
}

NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );

printf( "hoy=%s  ayer=%s  mañana=%s\n\n", $hoy, $ayer, $manana );

$cfg->set(
	array(
		'hora_inicio'        => '00:00',
		'hora_fin'           => '23:59',
		'dias_habiles'       => array( 1, 2, 3, 4, 5, 6, 7 ),
		'concurso_congelado' => false,
	)
);

try {
	// --- Sin revanchas: el comportamiento de siempre -------------------------
	echo "=== Sin revanchas ===\n";

	$r = jugar( $base, $db, $gente['A']['telefono'], 'Corredor A' );
	comprobar( 'A juega en su jornada', 200 === $r['terminar']['codigo'], 'HTTP ' . $r['terminar']['codigo'] );
	comprobar( 'y su resultado queda en la jornada de hoy', $db->tiene_score_en( $gente['A']['id'], $hoy ) );

	$r = pedir( $base . '/acceso', array( 'nombre' => 'A otra vez', 'telefono' => $gente['A']['telefono'], 'acepta' => true ) );
	comprobar( 'A no puede jugar dos veces el mismo día', 409 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	$r = pedir( $base . '/acceso', array( 'nombre' => 'Corredor B', 'telefono' => $gente['B']['telefono'], 'acepta' => true ) );
	comprobar( 'B no puede jugar: su jornada fue ayer', 403 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	// --- Revancha individual -------------------------------------------------
	echo "\n=== Revancha individual ===\n";

	$res = $db->crear_revancha( $gente['B']['id'], $hoy, '' );
	comprobar( 'no deja conceder una revancha personal sin motivo', is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_message() : 'la aceptó' );

	$res = $db->crear_revancha( $gente['B']['id'], $hoy, 'Derecho de petición 123' );
	comprobar( 'se concede con motivo', is_int( $res ), is_wp_error( $res ) ? $res->get_error_message() : '' );

	$res2 = $db->crear_revancha( $gente['B']['id'], $hoy, 'Otra vez' );
	comprobar( 'no se puede conceder dos veces la misma', is_wp_error( $res2 ) );

	$r = jugar( $base, $db, $gente['B']['telefono'], 'Corredor B' );
	comprobar( 'B ya puede jugar hoy', 200 === $r['terminar']['codigo'], 'HTTP ' . $r['terminar']['codigo'] );

	$score_b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$db->tabla_scores} WHERE participante_id = %d", $gente['B']['id'] ), ARRAY_A ); // phpcs:ignore
	comprobar(
		'y su resultado se guarda en la jornada de HOY, no en la del padrón',
		$hoy === $score_b['fecha_concurso'],
		$score_b['fecha_concurso'] . ' (padrón: ' . $ayer . ')'
	);

	$r = pedir( $base . '/acceso', array( 'nombre' => 'B otra vez', 'telefono' => $gente['B']['telefono'], 'acepta' => true ) );
	comprobar( 'la revancha da UN intento, no barra libre', 409 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	comprobar( 'la revancha de B no habilita a C', ! $db->hay_revancha_individual( $gente['C']['id'], $hoy ) );

	$r = pedir( $base . '/acceso', array( 'nombre' => 'Corredor C', 'telefono' => $gente['C']['telefono'], 'acepta' => true ) );
	comprobar( 'y C sigue sin poder entrar', 403 === $r['codigo'], 'HTTP ' . $r['codigo'] );

	// --- Dos jornadas, dos resultados ---------------------------------------
	echo "\n=== Un intento por jornada ===\n";

	comprobar(
		'B tiene resultado hoy y no ayer',
		$db->tiene_score_en( $gente['B']['id'], $hoy ) && ! $db->tiene_score_en( $gente['B']['id'], $ayer )
	);

	// Se le mete a mano un resultado de ayer: la clave única debe permitirlo.
	$ok = $wpdb->insert( // phpcs:ignore
		$db->tabla_scores,
		array(
			'participante_id' => $gente['B']['id'],
			'sesion_id'       => 0,
			'nombre'          => 'Corredor B ayer',
			'fecha_concurso'  => $ayer,
			'distancia_m'     => 1000,
			'seed'            => 1,
			'inputs'          => '',
		)
	);
	comprobar( 'la misma persona puede tener resultado en DOS jornadas', false !== $ok );

	// Pero no dos el mismo día.
	$wpdb->suppress_errors( true );
	$ok = $wpdb->insert( // phpcs:ignore
		$db->tabla_scores,
		array(
			'participante_id' => $gente['B']['id'],
			'sesion_id'       => 0,
			'nombre'          => 'Corredor B otra vez hoy',
			'fecha_concurso'  => $hoy,
			'distancia_m'     => 9999,
			'seed'            => 1,
			'inputs'          => '',
		)
	);
	$wpdb->suppress_errors( false );
	comprobar( 'pero la base impide dos en la MISMA jornada', false === $ok );

	$wpdb->delete( $db->tabla_scores, array( 'participante_id' => $gente['B']['id'], 'fecha_concurso' => $ayer ) ); // phpcs:ignore

	// --- Revancha general ----------------------------------------------------
	echo "\n=== Revancha general ===\n";

	// C jugó ayer y ganó.
	$wpdb->insert( // phpcs:ignore
		$db->tabla_scores,
		array(
			'participante_id' => $gente['C']['id'],
			'sesion_id'       => 0,
			'nombre'          => 'Corredor C',
			'fecha_concurso'  => $ayer,
			'distancia_m'     => 3000,
			'seed'            => 1,
			'inputs'          => '',
			'valido'          => 1,
		)
	);
	$score_c = (int) $wpdb->insert_id;

	$res = $db->marcar_ganador( $score_c, true, 'Primer puesto' );
	comprobar( 'se marca a C como ganador', true === $res, is_wp_error( $res ) ? $res->get_error_message() : '' );
	comprobar( 'y el sistema lo reconoce', $db->es_ganador( $gente['C']['id'] ) );
	comprobar( 'B no es ganador', ! $db->es_ganador( $gente['B']['id'] ) );

	$res = $db->crear_revancha( 0, $manana, 'Segunda oportunidad de campaña' );
	comprobar( 'se abre una revancha general', is_int( $res ) );
	comprobar( 'y el sistema la ve', $db->hay_revancha_general( $manana ) );
	comprobar( 'sin afectar a otros días', ! $db->hay_revancha_general( $hoy ) );

	/*
	 * La revancha general es para mañana, así que no se puede jugar hoy por
	 * HTTP. Se comprueba la regla llamando al método de elegibilidad, que es
	 * exactamente el que decide.
	 */
	$acceso   = NavidadTVS_Plugin::instancia()->acceso;
	$metodo   = new ReflectionMethod( $acceso, 'puede_jugar_hoy' );
	$metodo->setAccessible( true );

	$p_b = $db->buscar_participante( $gente['B']['id'] );
	$p_c = $db->buscar_participante( $gente['C']['id'] );
	$p_d = $db->buscar_participante( $gente['D']['id'] );

	comprobar( 'en la revancha general entra quien no ganó', $metodo->invoke( $acceso, $p_b, $manana ) );
	comprobar( 'NO entra quien ya ganó', ! $metodo->invoke( $acceso, $p_c, $manana ) );
	comprobar( 'y tampoco quien aún no ha tenido su día', ! $metodo->invoke( $acceso, $p_d, $manana ) || $p_d['fecha_concurso'] === $manana, 'jornada de D: ' . $p_d['fecha_concurso'] );

	// D tiene jornada mañana, así que entra por su propio día, no por la
	// revancha. Se comprueba con alguien cuyo día sea pasado mañana.
	$pasado = gmdate( 'Y-m-d', strtotime( $hoy . ' +2 day' ) );
	$wpdb->update( $db->tabla_participantes, array( 'fecha_concurso' => $pasado ), array( 'id' => $gente['D']['id'] ) ); // phpcs:ignore
	$p_d = $db->buscar_participante( $gente['D']['id'] );
	comprobar( 'quien todavía no ha tenido su día no entra por revancha', ! $metodo->invoke( $acceso, $p_d, $manana ), 'jornada de D: ' . $p_d['fecha_concurso'] );

	/*
	 * Una fecha sin ninguna revancha. No vale usar hoy: B tiene concedida una
	 * revancha individual para hoy unas líneas más arriba, así que hoy SÍ
	 * puede jugar, y comprobar lo contrario era un error de la prueba.
	 */
	$sin_nada = gmdate( 'Y-m-d', strtotime( $hoy . ' +5 day' ) );

	comprobar( 'en una fecha sin revanchas no entra nadie fuera de su jornada', ! $metodo->invoke( $acceso, $p_b, $sin_nada ), $sin_nada );
	comprobar( 'y quien sí la tenga ese día entra por la individual', $metodo->invoke( $acceso, $p_b, $hoy ) );

	// --- Marca de ganador ----------------------------------------------------
	echo "\n=== Marca de ganador ===\n";

	$score_a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$db->tabla_scores} WHERE participante_id = %d", $gente['A']['id'] ), ARRAY_A ); // phpcs:ignore

	$db->marcar_score_valido( (int) $score_a['id'], false, 'Prueba' );
	$res = $db->marcar_ganador( (int) $score_a['id'], true, 'Primer puesto' );
	comprobar( 'un resultado descalificado no puede ganar', is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_message() : 'lo permitió' );

	$db->marcar_score_valido( (int) $score_a['id'], true );
	$res = $db->marcar_ganador( (int) $score_a['id'], true, 'Primer puesto' );
	comprobar( 'rehabilitado, sí puede', true === $res );

	$fila = $db->buscar_score( (int) $score_a['id'] );
	comprobar( 'queda la nota del premio', 'Primer puesto' === $fila['ganador_nota'], $fila['ganador_nota'] );
	comprobar( 'y la fecha en que se marcó', ! empty( $fila['ganador_en'] ), (string) $fila['ganador_en'] );

	$ganadores = $db->ganadores();
	$ids       = array_map( 'intval', wp_list_pluck( $ganadores, 'id' ) );
	comprobar( 'A y C salen en la lista de ganadores', in_array( (int) $score_a['id'], $ids, true ) && in_array( $score_c, $ids, true ) );

	$res = $db->marcar_ganador( (int) $score_a['id'], false );
	comprobar( 'se puede desmarcar', true === $res );

	$fila = $db->buscar_score( (int) $score_a['id'] );
	comprobar( 'y se limpia la nota', '' === $fila['ganador_nota'] && empty( $fila['ganador_en'] ) );

	// --- Borrar revanchas ----------------------------------------------------
	echo "\n=== Retirar una revancha ===\n";

	$lista = $db->listar_revanchas();
	$mia   = null;

	foreach ( $lista as $r ) {
		if ( (int) $r['participante_id'] === $gente['B']['id'] ) {
			$mia = $r;
			break;
		}
	}

	comprobar( 'la revancha individual sale en el listado', null !== $mia );

	if ( $mia ) {
		comprobar( 'con el teléfono de la persona', ! empty( $mia['telefono'] ), $mia['telefono'] ?? '' );
		comprobar( 'y con el motivo escrito', 'Derecho de petición 123' === $mia['motivo'], $mia['motivo'] );
		comprobar( 'se puede retirar', $db->borrar_revancha( (int) $mia['id'] ) );
		comprobar( 'y deja de estar', ! $db->hay_revancha_individual( $gente['B']['id'], $hoy ) );
	}

	// --- Informes ------------------------------------------------------------
	echo "\n=== Informes ===\n";

	// B juega otra vez en una jornada distinta, para que tenga dos intentos.
	$wpdb->insert( // phpcs:ignore
		$db->tabla_scores,
		array(
			'participante_id'          => $gente['B']['id'],
			'sesion_id'                => 0,
			'nombre'                   => 'Corredor B',
			'cedula'                   => '98' . str_pad( '2', 7, '0', STR_PAD_LEFT ),
			'fecha_concurso'           => $ayer,
			'distancia_m'              => 5000,
			'departamento_propietario' => 'Antioquia',
			'seed'                     => 7,
			'inputs'                   => '',
			'valido'                   => 1,
		)
	);

	$informe = $db->informe_por_participante();
	$fila_b  = null;

	foreach ( $informe as $f ) {
		if ( (int) $f['id'] === $gente['B']['id'] ) {
			$fila_b = $f;
			break;
		}
	}

	comprobar( 'el informe general trae una fila por persona', null !== $fila_b );

	if ( $fila_b ) {
		comprobar( 'cuenta los dos intentos de B', 2 === (int) $fila_b['intentos'], $fila_b['intentos'] . ' intentos' );
		comprobar( 'y toma su mejor marca', 5000 === (int) $fila_b['distancia_maxima'], $fila_b['distancia_maxima'] . ' m' );
		comprobar( 'con la fecha de ESA carrera y no de otra', $ayer === $fila_b['fecha_mejor'], (string) $fila_b['fecha_mejor'] );
		comprobar( 'y lista las jornadas en que jugó', false !== strpos( (string) $fila_b['jornadas'], $hoy ) && false !== strpos( (string) $fila_b['jornadas'], $ayer ), (string) $fila_b['jornadas'] );
	}

	$fila_d = null;

	foreach ( $informe as $f ) {
		if ( (int) $f['id'] === $gente['D']['id'] ) {
			$fila_d = $f;
			break;
		}
	}

	comprobar( 'quien nunca jugó también sale', null !== $fila_d );

	if ( $fila_d ) {
		comprobar( 'con cero intentos', 0 === (int) $fila_d['intentos'] );
		comprobar( 'y sin distancia inventada', null === $fila_d['distancia_maxima'] );
	}

	$fila_c = null;

	foreach ( $informe as $f ) {
		if ( (int) $f['id'] === $gente['C']['id'] ) {
			$fila_c = $f;
			break;
		}
	}

	comprobar( 'el ganador sale marcado como tal', $fila_c && 1 === (int) $fila_c['gano'] );
	comprobar( 'y con el premio anotado', $fila_c && 'Primer puesto' === $fila_c['premio'], $fila_c['premio'] ?? '' );

	/*
	 * Una revancha individual nueva: la de antes se retiró unas líneas más
	 * arriba, al probar que se puede retirar, y contar sobre ella daría cero
	 * por un motivo que no tiene nada que ver con los informes.
	 */
	$db->crear_revancha( $gente['B']['id'], gmdate( 'Y-m-d', strtotime( $hoy . ' +9 day' ) ), 'Para contar en el informe' );

	$ej = $db->resumen_ejecutivo();

	comprobar( 'el ejecutivo cuenta inscritos', $ej['inscritos'] > 0, (string) $ej['inscritos'] );
	comprobar(
		'las participaciones suman válidas más descalificadas',
		$ej['participaciones'] >= $ej['participaciones_validas'],
		$ej['participaciones'] . ' / ' . $ej['participaciones_validas']
	);
	comprobar(
		'las personas que jugaron no superan a las participaciones',
		$ej['personas_que_jugaron'] <= $ej['participaciones'],
		$ej['personas_que_jugaron'] . ' personas, ' . $ej['participaciones'] . ' carreras'
	);
	comprobar( 'hay desglose por día', ! empty( $ej['por_dia'] ), count( $ej['por_dia'] ) . ' días' );
	comprobar( 'y desglose de inscritos por día', ! empty( $ej['inscritos_por_dia'] ) );
	comprobar( 'cuenta las revanchas concedidas', $ej['revanchas_generales'] >= 1 && $ej['revanchas_individuales'] >= 1, $ej['revanchas_generales'] . ' generales, ' . $ej['revanchas_individuales'] . ' individuales' );

	$suma_dias = 0;

	foreach ( $ej['por_dia'] as $d ) {
		$suma_dias += $d['participaciones'];
	}

	comprobar( 'el desglose por día suma el total', $suma_dias === $ej['participaciones'], $suma_dias . ' frente a ' . $ej['participaciones'] );

	$ganadores_lista = $db->ganadores();
	comprobar( 'la lista de ganadores cuadra con el total del ejecutivo', count( $ganadores_lista ) === $ej['ganadores'], count( $ganadores_lista ) . ' frente a ' . $ej['ganadores'] );


} finally {
	$cfg->set( $ventana_previa );
	$wpdb->query( "DELETE FROM {$db->tabla_revanchas} WHERE participante_id = 0" ); // phpcs:ignore
	limpiar( $db );
	echo "\nVentana restaurada y filas de prueba borradas.\n";
}

$marcador = cuenta( 'resumen' );

printf(
	"\n%s  %d comprobaciones, %d fallos\n",
	0 === $marcador['fallos'] ? 'TODO OK' : 'HAY FALLOS',
	$marcador['hechas'],
	$marcador['fallos']
);
