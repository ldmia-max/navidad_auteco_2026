<?php
/**
 * Verificación manual de E6 (validación server-side). Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e6.php
 *
 * Dos partes:
 *
 * 1. PARIDAD TypeScript ↔ PHP. Corre los vectores de dev/vectores-paridad.json
 *    —carreras completas ya resueltas por el navegador— y comprueba que este
 *    puerto saca exactamente lo mismo, campo por campo. Es obligatoria antes
 *    de cualquier commit que toque la física, en cualquiera de los dos lados.
 *
 *    Los vectores se regeneran con:  cd game && npm run sim:vectores
 *
 * 2. El validador de extremo a extremo: sesiones falsas, registros corruptos,
 *    dobles envíos y lo que pasaría si alguien abre DevTools y se inventa el
 *    resultado.
 *
 * ESCRIBE EN LA BASE DE DATOS y limpia lo suyo al terminar. Script de
 * desarrollo: nunca correr en producción.
 *
 * @package NavidadTVS
 */

$GLOBALS['fallos'] = 0;
$base              = dirname( __DIR__ );

function comprobar( $etiqueta, $esperado, $obtenido ) {
	$ok = ( $esperado === $obtenido );
	if ( ! $ok ) {
		$GLOBALS['fallos']++;
	}
	printf(
		"%s %-56s esperado=%-14s obtenido=%s\n",
		$ok ? 'OK  ' : 'FALLA',
		$etiqueta,
		var_export( $esperado, true ),
		var_export( $obtenido, true )
	);
}

function afirmar( $etiqueta, $condicion, $detalle = '' ) {
	if ( ! $condicion ) {
		$GLOBALS['fallos']++;
	}
	printf( "%s %s%s\n", $condicion ? 'OK  ' : 'FALLA', $etiqueta, '' === $detalle ? '' : '  ' . $detalle );
}

require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-simulacion.php';

$plugin    = NavidadTVS_Plugin::instancia();
$db        = $plugin->database;
$validador = $plugin->validador;

// ===========================================================================
echo "=== Vectores de paridad ===\n";

$ruta_vectores = $base . '/dev/vectores-paridad.json';

if ( ! file_exists( $ruta_vectores ) ) {
	echo "FALLA no existe dev/vectores-paridad.json\n";
	echo "      generarlo con: cd game && npm run sim:vectores\n";
	$GLOBALS['fallos']++;
	echo "\n1 FALLO(S)\n";
	return;
}

$v = json_decode( file_get_contents( $ruta_vectores ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

if ( ! is_array( $v ) || empty( $v['carreras'] ) ) {
	echo "FALLA el archivo de vectores no se pudo leer\n";
	$GLOBALS['fallos']++;
	echo "\n1 FALLO(S)\n";
	return;
}

printf( "     generado el %s, %d carreras\n\n", $v['generadoEn'], count( $v['carreras'] ) );

// --- Las constantes, antes que nada ---------------------------------------
/*
 * Si un número se cambió en TypeScript y no aquí, todo lo demás falla en
 * cascada y el mensaje no diría por qué. Comprobarlas primero convierte
 * treinta fallos incomprensibles en uno que dice exactamente qué pasó.
 */
echo "--- Constantes espejo ---\n";

$desajustadas = array();

foreach ( $v['constantes'] as $nombre => $valor_ts ) {
	if ( ! defined( 'NavidadTVS_Sim_Constantes::' . $nombre ) ) {
		$desajustadas[] = sprintf( '%s no existe en PHP', $nombre );
		continue;
	}

	$valor_php = constant( 'NavidadTVS_Sim_Constantes::' . $nombre );

	if ( (int) $valor_php !== (int) $valor_ts ) {
		$desajustadas[] = sprintf( '%s: TS=%d PHP=%d', $nombre, $valor_ts, $valor_php );
	}
}

afirmar(
	sprintf( 'las %d constantes valen lo mismo en los dos lados', count( $v['constantes'] ) ),
	empty( $desajustadas ),
	implode( '; ', $desajustadas )
);

comprobar( 'TOTAL_TICKS coincide con la constante del plugin', (int) $v['constantes']['TOTAL_TICKS'], NAVIDAD_TVS_TOTAL_TICKS );
comprobar( 'el tope de plausibilidad coincide', (int) $v['constantes']['DISTANCIA_MAXIMA_M'], NAVIDAD_TVS_DISTANCIA_MAXIMA_M );

// --- Las pistas -------------------------------------------------------------
echo "\n--- Pistas ---\n";

/**
 * Huella de una pista. Espejo de huellaPista() en exportar-vectores.ts.
 *
 * @param NavidadTVS_Sim_Pista $p Pista.
 * @return int uint32.
 */
function huella_pista( $p ) {
	$h = 2166136261;

	$mezclar = function ( $valor ) use ( &$h ) {
		// imul(h, 31) a mano: h cabe en 32 bits y 31 en 5, así que el producto
		// no pasa de 37 bits y no desborda el entero de PHP.
		$h = ( ( $h * 31 ) + $valor ) & 0xFFFFFFFF;
	};

	$mezclar( count( $p->obstaculos ) );
	foreach ( $p->obstaculos as $o ) {
		$mezclar( $o['pos'] );
		$mezclar( $o['carril'] );
		$mezclar( $o['tipo'] );
		$mezclar( $o['largo'] );
	}

	$mezclar( count( $p->items ) );
	foreach ( $p->items as $i ) {
		$mezclar( $i['pos'] );
		$mezclar( $i['carril'] );
	}

	return $h;
}

foreach ( $v['pistas'] as $ref ) {
	$p = NavidadTVS_Sim_Pista::generar( $ref['seed'] );

	$iguales = count( $p->obstaculos ) === (int) $ref['obstaculos']
		&& count( $p->items ) === (int) $ref['items']
		&& huella_pista( $p ) === (int) $ref['huella'];

	afirmar(
		sprintf( 'pista del seed %s', $ref['seed'] ),
		$iguales,
		sprintf(
			'%d obstáculos, %d llaves, huella %u%s',
			count( $p->obstaculos ),
			count( $p->items ),
			huella_pista( $p ),
			$iguales ? '' : sprintf( '  <-- TS decía %d/%d/%u', $ref['obstaculos'], $ref['items'], $ref['huella'] )
		)
	);
}

// --- Las carreras -------------------------------------------------------------
echo "\n--- Carreras, estado final campo por campo ---\n";

$inicio_paridad = microtime( true );
$divergencias   = 0;

foreach ( $v['carreras'] as $carrera ) {
	$entradas = NavidadTVS_Sim_Entradas::decodificar( $carrera['entradas'], NAVIDAD_TVS_TOTAL_TICKS );

	if ( is_wp_error( $entradas ) ) {
		afirmar(
			sprintf( 'seed %s / %s', $carrera['seed'], $carrera['estrategia'] ),
			false,
			'el registro no se pudo decodificar: ' . $entradas->get_error_message()
		);
		$divergencias++;
		continue;
	}

	// Se corre tick a tick para poder comparar también las posiciones
	// intermedias: dos errores que se compensan darían el mismo estado final.
	$pista    = NavidadTVS_Sim_Pista::generar( $carrera['seed'] );
	$estado   = NavidadTVS_Sim_Simulacion::crear_estado();
	$muestras = array();

	for ( $t = 0; $t < NAVIDAD_TVS_TOTAL_TICKS; $t++ ) {
		NavidadTVS_Sim_Simulacion::paso( $estado, $entradas[ $t ], $pista );

		if ( 0 === $estado['tick'] % (int) $v['pasoMuestra'] ) {
			$muestras[] = $estado['pos'];
		}
	}

	$problemas = array();

	foreach ( $carrera['estado'] as $campo => $esperado ) {
		if ( ! array_key_exists( $campo, $estado ) ) {
			$problemas[] = sprintf( 'falta el campo %s', $campo );
			continue;
		}
		if ( (int) $estado[ $campo ] !== (int) $esperado ) {
			$problemas[] = sprintf( '%s: TS=%d PHP=%d', $campo, $esperado, $estado[ $campo ] );
		}
	}

	$distancia = NavidadTVS_Sim_Simulacion::distancia_metros( $estado );

	if ( $distancia !== (int) $carrera['distancia'] ) {
		$problemas[] = sprintf( 'distancia: TS=%d PHP=%d', $carrera['distancia'], $distancia );
	}

	// Dónde se separaron, que es lo primero que hace falta saber para arreglarlo.
	foreach ( $carrera['muestras'] as $i => $esperado ) {
		if ( ! isset( $muestras[ $i ] ) || (int) $muestras[ $i ] !== (int) $esperado ) {
			$problemas[] = sprintf(
				'se separan en el tick %d (TS=%d PHP=%d)',
				( $i + 1 ) * (int) $v['pasoMuestra'],
				$esperado,
				isset( $muestras[ $i ] ) ? $muestras[ $i ] : -1
			);
			break;
		}
	}

	if ( ! empty( $problemas ) ) {
		$divergencias++;
	}

	afirmar(
		sprintf( 'seed %-10s %-30s', $carrera['seed'], $carrera['estrategia'] ),
		empty( $problemas ),
		empty( $problemas ) ? sprintf( '%d m', $distancia ) : implode( '; ', array_slice( $problemas, 0, 3 ) )
	);
}

$ms_por_carrera = ( microtime( true ) - $inicio_paridad ) * 1000 / max( 1, count( $v['carreras'] ) );

echo "\n";
afirmar( 'ninguna carrera diverge', 0 === $divergencias, sprintf( '%d de %d', $divergencias, count( $v['carreras'] ) ) );

printf( "     %.1f ms por carrera reejecutada\n", $ms_por_carrera );
printf( "     validar las 150 de una jornada costaría %.2f s\n", $ms_por_carrera * 150 / 1000 );

/*
 * La jornada trae 150 carreras y llegan casi todas juntas al cerrar la
 * ventana. Si reejecutar una costara décimas de segundo, validar en línea
 * dejaría de ser viable y habría que meter una cola.
 */
afirmar( 'una carrera se reejecuta en menos de 200 ms', $ms_por_carrera < 200, sprintf( '%.1f ms', $ms_por_carrera ) );

// ===========================================================================
echo "\n=== Decodificación del registro ===\n";

$vacio = array_fill( 0, NAVIDAD_TVS_TOTAL_TICKS, 0 );
$ok    = NavidadTVS_Sim_Entradas::decodificar( NavidadTVS_Sim_Entradas::codificar( $vacio ), NAVIDAD_TVS_TOTAL_TICKS );
comprobar( 'ida y vuelta de un registro vacío', false, is_wp_error( $ok ) );
comprobar( 'recupera los ticks exactos', NAVIDAD_TVS_TOTAL_TICKS, is_array( $ok ) ? count( $ok ) : -1 );

$rechazos = array(
	'cadena vacía'                => '',
	'no es base64'                => '!!!no soy base64!!!',
	'número impar de bytes'       => base64_encode( "\x01" ),
	'repeticiones a cero'         => base64_encode( "\x01\x00" ),
	'más ticks de los que dura'   => NavidadTVS_Sim_Entradas::codificar( array_fill( 0, NAVIDAD_TVS_TOTAL_TICKS + 300, 1 ) ),
	'menos ticks de los que dura' => NavidadTVS_Sim_Entradas::codificar( array_fill( 0, 100, 1 ) ),
	'demasiado grande'            => str_repeat( 'A', 70000 ),
);

foreach ( $rechazos as $etiqueta => $entrada ) {
	$r = NavidadTVS_Sim_Entradas::decodificar( $entrada, NAVIDAD_TVS_TOTAL_TICKS );
	afirmar( sprintf( 'rechaza: %-28s', $etiqueta ), is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_code() : 'LO ACEPTÓ' );
}

// ===========================================================================
echo "\n=== Validador de extremo a extremo ===\n";

global $wpdb;

// Participante de pruebas, con su jornada puesta en hoy para que sea elegible.
$hoy      = NavidadTVS_Plugin::ahora()->format( 'Y-m-d' );
$cedula   = '9900000001';
$telefono = '3009990001';

$wpdb->query( $wpdb->prepare( "DELETE s FROM {$db->tabla_scores} s INNER JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.cedula = %s", $cedula ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE ss FROM {$db->tabla_sesiones} ss INNER JOIN {$db->tabla_participantes} p ON p.id = ss.participante_id WHERE p.cedula = %s", $cedula ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$db->tabla_participantes} WHERE cedula = %s", $cedula ) ); // phpcs:ignore

$wpdb->insert(
	$db->tabla_participantes,
	array(
		'telefono'                 => $telefono,
		'telefono_csv'             => '57' . $telefono,
		'cedula'                   => $cedula,
		'placa'                    => 'ZZZ99Z',
		'marca'                    => NAVIDAD_TVS_MARCA,
		'fecha_concurso'           => $hoy,
		'ciudad_propietario'       => 'MEDELLIN',
		'departamento_propietario' => 'ANTIOQUIA',
	)
);
$participante_id = (int) $wpdb->insert_id;

/** Crea una sesión ya consumida, como la deja /carrera/iniciar. */
function sesion_de_prueba( $db, $participante_id, $seed, $hace_segundos ) {
	global $wpdb;

	$nonce    = bin2hex( random_bytes( 32 ) );
	$consumida = gmdate( 'Y-m-d H:i:s', time() - $hace_segundos );

	$wpdb->insert(
		$db->tabla_sesiones,
		array(
			'participante_id' => $participante_id,
			'nombre_digitado' => 'PRUEBA E6',
			'seed'            => $seed,
			'nonce'           => $nonce,
			'estado'          => 'consumida',
			'acepto_terminos' => 1,
			'creada_en'       => $consumida,
			'consumida_en'    => $consumida,
		)
	);

	return $nonce;
}

/** Borra los scores del participante de pruebas para poder repetir. */
function limpiar_scores( $db, $participante_id ) {
	global $wpdb;
	$wpdb->delete( $db->tabla_scores, array( 'participante_id' => $participante_id ) );
}

// Un registro real: acelerador a fondo los 90 segundos.
$registro_acelera = NavidadTVS_Sim_Entradas::codificar(
	array_fill( 0, NAVIDAD_TVS_TOTAL_TICKS, NavidadTVS_Sim_Entradas::BIT_ACELERA )
);

$seed_prueba = 4242;

// --- Caso feliz ---------------------------------------------------------------
$nonce = sesion_de_prueba( $db, $participante_id, $seed_prueba, 95 );
$r     = $validador->registrar(
	array(
		'token'      => $nonce,
		'entradas'   => $registro_acelera,
		'distancia'  => 1,
		'ip'         => '10.0.0.1',
		'user_agent' => 'prueba/e6',
	)
);

afirmar( 'una carrera legítima se registra', ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_code() : sprintf( '%d m', $r['distancia'] ) );

$fila = $db->score_de( $participante_id );
afirmar( 'quedó la fila del score', null !== $fila );

if ( null !== $fila && ! is_wp_error( $r ) ) {
	// La distancia tiene que salir de la reejecución, no del cliente.
	$esperada = NavidadTVS_Sim_Simulacion::distancia_metros(
		NavidadTVS_Sim_Simulacion::simular( $seed_prueba, NavidadTVS_Sim_Entradas::decodificar( $registro_acelera, NAVIDAD_TVS_TOTAL_TICKS ), NAVIDAD_TVS_TOTAL_TICKS )
	);

	comprobar( 'la distancia la calculó el servidor', $esperada, (int) $fila['distancia_m'] );
	comprobar( 'no le hizo caso a la del cliente', 1, (int) $fila['distancia_cliente_m'] );
	comprobar( 'el score quedó válido', 1, (int) $fila['valido'] );
	comprobar( 'guardó el seed', $seed_prueba, (int) $fila['seed'] );
	comprobar( 'guardó el registro para auditar', $registro_acelera, $fila['inputs'] );
	comprobar( 'copió la cédula del padrón', $cedula, $fila['cedula'] );
	comprobar( 'copió la jornada', $hoy, $fila['fecha_concurso'] );
	comprobar( 'guardó la IP', '10.0.0.1', $fila['ip'] );
	afirmar( 'guardó cuánto tardó', (int) $fila['duracion_s'] >= 90, sprintf( '%d s', $fila['duracion_s'] ) );
}

// --- Segundo envío del mismo participante ---------------------------------------
$nonce2 = sesion_de_prueba( $db, $participante_id, $seed_prueba, 95 );
$r2     = $validador->registrar( array( 'token' => $nonce2, 'entradas' => $registro_acelera, 'distancia' => 0 ) );
afirmar( 'el segundo intento se rechaza', is_wp_error( $r2 ), is_wp_error( $r2 ) ? $r2->get_error_code() : 'LO ACEPTÓ' );

// --- Lo que intentaría alguien desde DevTools --------------------------------------
limpiar_scores( $db, $participante_id );

$ataques = array(
	'token inventado'           => array( 'token' => str_repeat( 'a', 64 ), 'entradas' => $registro_acelera ),
	'token vacío'               => array( 'token' => '', 'entradas' => $registro_acelera ),
	'registro corrupto'         => array( 'token' => null, 'entradas' => 'no-soy-base64!!' ),
	'registro corto'            => array( 'token' => null, 'entradas' => NavidadTVS_Sim_Entradas::codificar( array_fill( 0, 60, 1 ) ) ),
	'registro gigante'          => array( 'token' => null, 'entradas' => str_repeat( 'A', 70000 ) ),
);

foreach ( $ataques as $etiqueta => $ataque ) {
	$token = $ataque['token'];

	if ( null === $token ) {
		limpiar_scores( $db, $participante_id );
		$token = sesion_de_prueba( $db, $participante_id, $seed_prueba, 95 );
	}

	$res = $validador->registrar( array( 'token' => $token, 'entradas' => $ataque['entradas'], 'distancia' => 999999 ) );
	afirmar( sprintf( 'rechaza: %-24s', $etiqueta ), is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_code() : 'LO ACEPTÓ' );
}

// Una sesión que nunca pasó por /carrera/iniciar.
limpiar_scores( $db, $participante_id );
$nonce_emitida = bin2hex( random_bytes( 32 ) );
$wpdb->insert(
	$db->tabla_sesiones,
	array(
		'participante_id' => $participante_id,
		'nombre_digitado' => 'PRUEBA E6',
		'seed'            => $seed_prueba,
		'nonce'           => $nonce_emitida,
		'estado'          => 'emitida',
		'acepto_terminos' => 1,
	)
);
$res = $validador->registrar( array( 'token' => $nonce_emitida, 'entradas' => $registro_acelera ) );
afirmar( 'rechaza: carrera que nunca se inició   ', is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_code() : 'LO ACEPTÓ' );

// --- Plausibilidad del reloj ---------------------------------------------------------
limpiar_scores( $db, $participante_id );
$nonce_rapida = sesion_de_prueba( $db, $participante_id, $seed_prueba, 3 );
$res          = $validador->registrar( array( 'token' => $nonce_rapida, 'entradas' => $registro_acelera ) );

afirmar( 'una carrera que llega en 3 s se registra pero inválida', ! is_wp_error( $res ) && empty( $res['valido'] ) );

$fila = $db->score_de( $participante_id );
if ( null !== $fila ) {
	comprobar( 'queda marcada como no válida', 0, (int) $fila['valido'] );
	afirmar( 'con el motivo anotado', '' !== $fila['motivo_descalificacion'], $fila['motivo_descalificacion'] );
}

/*
 * Se guarda en vez de rechazarse a propósito: el intento es único y ya se
 * gastó, así que tiene que quedar rastro de lo que pasó. Marcarla inválida la
 * deja fuera del ranking sin borrar la evidencia.
 */

// --- Ranking ------------------------------------------------------------------------
limpiar_scores( $db, $participante_id );
$nonce_ok = sesion_de_prueba( $db, $participante_id, $seed_prueba, 95 );
$validador->registrar( array( 'token' => $nonce_ok, 'entradas' => $registro_acelera ) );

$ranking = $db->ranking( $hoy, 10 );
afirmar( 'el score aparece en el ranking de la jornada', count( $ranking ) >= 1, sprintf( '%d filas', count( $ranking ) ) );

$ordenado = true;
for ( $i = 1; $i < count( $ranking ); $i++ ) {
	if ( (int) $ranking[ $i - 1 ]['distancia_m'] < (int) $ranking[ $i ]['distancia_m'] ) {
		$ordenado = false;
	}
}
afirmar( 'el ranking va de mayor a menor', $ordenado );

// --- Limpieza -------------------------------------------------------------------------
$wpdb->delete( $db->tabla_scores, array( 'participante_id' => $participante_id ) );
$wpdb->delete( $db->tabla_sesiones, array( 'participante_id' => $participante_id ) );
$wpdb->delete( $db->tabla_participantes, array( 'id' => $participante_id ) );

echo "\n(participante de prueba borrado)\n";

echo "\n";
$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
