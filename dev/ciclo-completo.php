<?php
/**
 * Ciclo completo de punta a punta con el padrón real anonimizado.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/ciclo-completo.php
 *   ... dev/ciclo-completo.php ayudas/otro-archivo.csv
 *
 * Recorre lo que recorre un participante de verdad, y por HTTP:
 *
 *   importar el padrón → /acceso → /carrera/iniciar → simular → /carrera/terminar
 *   → comprobar que el resultado aparece en el ranking
 *
 * Usa el archivo real porque es el único que tiene su forma: tildes, nombres
 * de establecimiento largos, ciudades con espacios, la mezcla de formatos que
 * salga del sistema de origen. Un CSV inventado por mí pasaría siempre, y no
 * probaría nada de lo que de verdad rompe un importador.
 *
 * ANONIMIZA ANTES DE TOCAR NADA. Teléfono, cédula y placa se sustituyen por
 * valores sintéticos con prefijos reservados; ciudad, departamento y
 * establecimiento se conservan, que es donde está la forma rara y no son
 * datos de una persona. El archivo anonimizado se escribe en el directorio
 * temporal del sistema, nunca dentro del repositorio.
 *
 * ESCRIBE EN LA BASE y borra todo lo suyo al terminar, pase lo que pase. Las
 * filas reales que ya estén importadas no se tocan: las de prueba llevan
 * placa ZZZ…
 *
 * Script de desarrollo: nunca en producción.
 *
 * @package NavidadTVS
 */

$db  = NavidadTVS_Plugin::instancia()->database;
$cfg = NavidadTVS_Plugin::instancia()->settings;

require_once NAVIDAD_TVS_PATH . 'includes/admin/class-import-padron.php';
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
 * Una petición HTTP al API del concurso.
 *
 * @param string     $url    Destino completo.
 * @param array|null $cuerpo Cuerpo JSON, o null para GET.
 * @return array{codigo:int, datos:array}
 */
function pedir( $url, $cuerpo = null ) {
	$ch = curl_init( $url );

	$opciones = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
	);

	if ( null !== $cuerpo ) {
		$opciones[ CURLOPT_POST ]       = true;
		$opciones[ CURLOPT_POSTFIELDS ] = wp_json_encode( $cuerpo );
		$opciones[ CURLOPT_HTTPHEADER ] = array( 'Content-Type: application/json' );
	}

	curl_setopt_array( $ch, $opciones );

	$texto  = curl_exec( $ch );
	$codigo = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );

	curl_close( $ch );

	$datos = json_decode( (string) $texto, true );

	return array(
		'codigo' => $codigo,
		'datos'  => is_array( $datos ) ? $datos : array(),
	);
}

/** Borra todo lo que creó esta corrida. */
function limpiar( $db ) {
	global $wpdb;

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
// 1. Anonimizar
// ---------------------------------------------------------------------------

$origen_rel = 'ayudas/participantes_previo.csv';

foreach ( (array) $args as $arg ) {
	if ( '' !== trim( (string) $arg ) ) {
		$origen_rel = (string) $arg;
	}
}

$origen = NAVIDAD_TVS_PATH . ltrim( $origen_rel, '/' );

if ( ! file_exists( $origen ) ) {
	printf( "No existe el archivo: %s\n", $origen );
	return;
}

$hoy = NavidadTVS_Plugin::hoy();

$entrada = fopen( $origen, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$destino = tempnam( sys_get_temp_dir(), 'ntvs-anon-' ) . '.csv';
$salida  = fopen( $destino, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

$cabecera = fgetcsv( $entrada, 0, ';' );
fputcsv( $salida, $cabecera, ';' );

$col = array_flip( array_map( 'trim', $cabecera ) );
$n   = 0;

while ( false !== ( $fila = fgetcsv( $entrada, 0, ';' ) ) ) {
	if ( count( $fila ) < count( $cabecera ) ) {
		continue;
	}

	/*
	 * Los tres identificadores se reemplazan; el resto se conserva tal cual,
	 * que es donde está la forma rara que se quiere probar. La jornada se pasa
	 * a hoy para que el ciclo pueda jugarse ahora mismo.
	 */
	$fila[ $col['telefono'] ] = '5739' . str_pad( (string) ( 10000000 + $n ), 8, '0', STR_PAD_LEFT );
	$fila[ $col['cedula'] ]   = '99' . str_pad( (string) $n, 7, '0', STR_PAD_LEFT );
	$fila[ $col['placa'] ]    = 'ZZZ' . str_pad( (string) $n, 3, '0', STR_PAD_LEFT );

	if ( isset( $col['fecha_concurso'] ) ) {
		$fila[ $col['fecha_concurso'] ] = $hoy;
	}

	fputcsv( $salida, $fila, ';' );
	$n++;
}

fclose( $entrada ); // phpcs:ignore WordPress.WP.AlternativeFunctions
fclose( $salida ); // phpcs:ignore WordPress.WP.AlternativeFunctions

printf( "Padrón anonimizado: %d filas, jornada %s\n", $n, $hoy );
printf( "Archivo temporal  : %s\n\n", $destino );

// ---------------------------------------------------------------------------
// 2. El ciclo
// ---------------------------------------------------------------------------

$ventana_previa = array(
	'hora_inicio'        => $cfg->get( 'hora_inicio' ),
	'hora_fin'           => $cfg->get( 'hora_fin' ),
	'dias_habiles'       => $cfg->get( 'dias_habiles' ),
	'concurso_congelado' => $cfg->get( 'concurso_congelado' ),
);

limpiar( $db );

$cfg->set(
	array(
		'hora_inicio'        => '00:00',
		'hora_fin'           => '23:59',
		'dias_habiles'       => array( 1, 2, 3, 4, 5, 6, 7 ),
		'concurso_congelado' => false,
	)
);

$base = 'http://wordpress/wp-json/' . NAVIDAD_TVS_REST_NS;

try {
	// --- Importación --------------------------------------------------------
	echo "=== Importación ===\n";

	$importador = new NavidadTVS_Import_Padron( $db );
	$analisis   = $importador->analizar( $destino );

	comprobar( 'el archivo se lee sin errores', ! is_wp_error( $analisis ), is_wp_error( $analisis ) ? $analisis->get_error_message() : '' );

	if ( is_wp_error( $analisis ) ) {
		throw new RuntimeException( 'sin análisis no hay ciclo' );
	}

	$validas    = count( $analisis['filas'] );
	$rechazadas = count( $analisis['rechazos'] );

	printf( "  %d válidas, %d rechazadas\n", $validas, $rechazadas );

	comprobar( 'acepta la mayoría del padrón real', $validas > 0 && $validas >= $n * 0.9, "{$validas} de {$n}" );

	if ( $rechazadas > 0 ) {
		$motivos = array();

		foreach ( $analisis['rechazos'] as $r ) {
			$clave             = preg_replace( '/"[^"]*"/', '"X"', $r['motivo'] );
			$clave             = preg_replace( '/fila [0-9]+/', 'fila N', $clave );
			$motivos[ $clave ] = ( $motivos[ $clave ] ?? 0 ) + 1;
		}

		echo "  motivos de rechazo:\n";
		arsort( $motivos );

		foreach ( $motivos as $m => $c ) {
			printf( "    %4d  %s\n", $c, $m );
		}
	}

	$insertadas = $db->insertar_participantes( $analisis['filas'] );
	comprobar( 'se insertan todas las válidas', $insertadas === $validas, "{$insertadas} de {$validas}" );

	// La segunda pasada no debe duplicar: el importador es acumulativo.
	$analisis2 = $importador->analizar( $destino );
	comprobar(
		'reimportar el mismo archivo no duplica a nadie',
		0 === count( $analisis2['filas'] ),
		count( $analisis2['filas'] ) . ' filas se colarían'
	);

	// --- El recorrido del participante --------------------------------------
	echo "\n=== Recorrido de un participante ===\n";

	$telefono = '3910000000';

	$r = pedir( $base . '/acceso', array( 'nombre' => 'Ciclo Completo', 'telefono' => $telefono, 'acepta' => true ) );
	comprobar( '/acceso responde 200', 200 === $r['codigo'], 'HTTP ' . $r['codigo'] . ' ' . ( $r['datos']['message'] ?? '' ) );
	comprobar( 'y entrega un token', ! empty( $r['datos']['token'] ) );

	$token = $r['datos']['token'] ?? '';

	$r = pedir( $base . '/carrera/iniciar', array( 'token' => $token ) );
	comprobar( '/carrera/iniciar responde 200', 200 === $r['codigo'], 'HTTP ' . $r['codigo'] . ' ' . ( $r['datos']['message'] ?? '' ) );
	comprobar( 'y entrega un seed', isset( $r['datos']['seed'] ) );

	$seed = (int) ( $r['datos']['seed'] ?? 0 );

	// El navegador juega: se arma un log de entradas y se simula igual que él.
	$ticks    = NAVIDAD_TVS_TOTAL_TICKS;
	$registro = array();

	for ( $t = 0; $t < $ticks; $t++ ) {
		$registro[] = 1 | ( ( ( $t % 300 ) < 90 ) ? 2 : 0 );
	}

	$b64       = NavidadTVS_Sim_Entradas::codificar( $registro );
	$estado    = NavidadTVS_Sim_Simulacion::simular( $seed, NavidadTVS_Sim_Entradas::decodificar( $b64, $ticks ), $ticks );
	$distancia = NavidadTVS_Sim_Simulacion::distancia_metros( $estado );

	printf( "  el cliente calcula %d m\n", $distancia );

	/*
	 * La carrera se enveje... se retrasa noventa segundos a mano.
	 *
	 * La primera versión de esta prueba enviaba el resultado al instante y el
	 * servidor lo marcaba inválido, con razón: una carrera de noventa segundos
	 * no puede llegar en cero. Esperar noventa segundos de verdad haría la
	 * prueba inservible para el día a día, así que se retrasa consumida_en y
	 * el reloj del servidor ve lo que vería en una carrera real.
	 *
	 * Que esto haga falta es una buena noticia: significa que la malla de
	 * plausibilidad está puesta. Más abajo se comprueba de frente.
	 */
	$wpdb->query( // phpcs:ignore
		$wpdb->prepare(
			"UPDATE {$db->tabla_sesiones}
			    SET consumida_en = ( UTC_TIMESTAMP() - INTERVAL %d SECOND )
			  WHERE nonce = %s", // phpcs:ignore WordPress.DB.PreparedSQL
			NAVIDAD_TVS_DURACION_SEGUNDOS,
			$token
		)
	);

	$r = pedir( $base . '/carrera/terminar', array( 'token' => $token, 'entradas' => $b64, 'distancia' => $distancia ) );
	comprobar( '/carrera/terminar responde 200', 200 === $r['codigo'], 'HTTP ' . $r['codigo'] . ' ' . ( $r['datos']['message'] ?? '' ) );
	comprobar(
		'el servidor calcula la misma distancia que el cliente',
		(int) ( $r['datos']['distancia'] ?? -1 ) === $distancia,
		( $r['datos']['distancia'] ?? '?' ) . ' frente a ' . $distancia
	);
	comprobar( 'y lo da por válido', ! empty( $r['datos']['valido'] ), 'valido=' . var_export( $r['datos']['valido'] ?? null, true ) );

	// --- La malla de plausibilidad ------------------------------------------
	echo "\n=== Malla de plausibilidad ===\n";

	// Otro participante, que envía su carrera de noventa segundos al instante.
	$telefono2 = '3910000001';

	$r = pedir( $base . '/acceso', array( 'nombre' => 'Demasiado Rapido', 'telefono' => $telefono2, 'acepta' => true ) );
	$token2 = $r['datos']['token'] ?? '';

	$r     = pedir( $base . '/carrera/iniciar', array( 'token' => $token2 ) );
	$seed2 = (int) ( $r['datos']['seed'] ?? 0 );

	$estado2 = NavidadTVS_Sim_Simulacion::simular( $seed2, NavidadTVS_Sim_Entradas::decodificar( $b64, $ticks ), $ticks );
	$dist2   = NavidadTVS_Sim_Simulacion::distancia_metros( $estado2 );

	$r = pedir( $base . '/carrera/terminar', array( 'token' => $token2, 'entradas' => $b64, 'distancia' => $dist2 ) );

	comprobar( 'una carrera enviada al instante se acepta pero no cuenta', 200 === $r['codigo'] && empty( $r['datos']['valido'] ), 'HTTP ' . $r['codigo'] . ' valido=' . var_export( $r['datos']['valido'] ?? null, true ) );

	$score_rapido = $db->score_de( (int) $db->buscar_participante_por_telefono( $telefono2 )['id'] );
	comprobar( 'y queda registrada con el motivo escrito', ! empty( $score_rapido['motivo_descalificacion'] ), $score_rapido['motivo_descalificacion'] ?? '' );

	// --- Un solo intento ----------------------------------------------------
	echo "\n=== Un solo intento ===\n";

	$r = pedir( $base . '/acceso', array( 'nombre' => 'Ciclo Completo', 'telefono' => $telefono, 'acepta' => true ) );
	comprobar( 'el mismo teléfono ya no puede volver a entrar', 200 !== $r['codigo'], 'HTTP ' . $r['codigo'] );
	comprobar( 'y se le explica por qué', ! empty( $r['datos']['message'] ), $r['datos']['message'] ?? '' );

	$r = pedir( $base . '/carrera/terminar', array( 'token' => $token, 'entradas' => $b64, 'distancia' => $distancia ) );
	comprobar( 'reenviar el mismo resultado no cuela', 200 !== $r['codigo'], 'HTTP ' . $r['codigo'] );

	// --- El resultado en el backoffice --------------------------------------
	echo "\n=== En el ranking ===\n";

	$ranking = $db->ranking_rango( $hoy, $hoy, 1, 500, false );
	$mio     = null;

	foreach ( $ranking as $fila ) {
		if ( 'Ciclo Completo' === $fila['nombre'] ) {
			$mio = $fila;
			break;
		}
	}

	comprobar( 'el resultado válido aparece en el ranking', null !== $mio );

	if ( $mio ) {
		comprobar( 'con la distancia que calculó el servidor', (int) $mio['distancia_m'] === $distancia, $mio['distancia_m'] . ' m' );
		comprobar( 'y con la ciudad que traía el padrón', '' !== $mio['ciudad_propietario'], $mio['ciudad_propietario'] );
	}

	$nombres_ranking = wp_list_pluck( $ranking, 'nombre' );
	comprobar( 'el implausible NO aparece en el ranking', ! in_array( 'Demasiado Rapido', $nombres_ranking, true ) );

	$con_invalidos = wp_list_pluck( $db->ranking_rango( $hoy, $hoy, 1, 500, true ), 'nombre' );
	comprobar( 'pero sí al incluir descalificados', in_array( 'Demasiado Rapido', $con_invalidos, true ) );

	$padron  = $db->padron_con_participacion( $hoy, $hoy );
	$mios    = array_filter(
		$padron,
		static function ( $f ) {
			return 0 === strpos( (string) $f['placa'], 'ZZZ' );
		}
	);
	$jugaron = array_filter( $mios, static function ( $f ) { return ! empty( $f['score_id'] ); } );

	comprobar( 'el informe cuenta los dos que jugaron', 2 === count( $jugaron ), count( $jugaron ) . ' de ' . count( $mios ) );

} catch ( RuntimeException $e ) {
	printf( "\nCiclo interrumpido: %s\n", $e->getMessage() );
} finally {
	$cfg->set( $ventana_previa );
	limpiar( $db );

	if ( file_exists( $destino ) ) {
		unlink( $destino ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	echo "\nVentana restaurada, filas de prueba borradas y archivo anonimizado eliminado.\n";
}

$marcador = cuenta( 'resumen' );

printf(
	"\n%s  %d comprobaciones, %d fallos\n",
	0 === $marcador['fallos'] ? 'TODO OK' : 'HAY FALLOS',
	$marcador['hechas'],
	$marcador['fallos']
);
