<?php
/**
 * Prueba de carga: 50 accesos simultáneos, como a las 12:00 del primer día.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/prueba-carga.php
 *   ... dev/prueba-carga.php 100
 *
 * Dispara N peticiones DE VERDAD contra el WordPress local, en paralelo y por
 * HTTP, no llamando a las clases desde dentro. Es la diferencia entre medir la
 * lógica y medir lo que va a pasar: por HTTP entran PHP-FPM, las conexiones a
 * MySQL, los plugins del sitio y los índices de las tablas.
 *
 * Mide las dos fases que importan:
 *
 *   1. /acceso           — valida padrón y emite sesión. Es la que recibe el
 *                          pico, porque todo el mundo entra a la vez.
 *   2. /carrera/iniciar  — consume el intento y entrega el seed. Escribe, y es
 *                          donde una carrera doble haría más daño.
 *
 * Y comprueba lo que no se ve en un promedio: que nadie recibió dos sesiones,
 * que ningún intento se consumió dos veces y que ningún seed se repitió.
 *
 * ESCRIBE EN LA BASE. Crea participantes de prueba con placa ZZZ… y los borra
 * al terminar pase lo que pase, junto con sus sesiones. También abre la
 * ventana horaria mientras dura y la deja como estaba.
 *
 * Script de desarrollo: nunca en producción.
 *
 * @package NavidadTVS
 */

$db  = NavidadTVS_Plugin::instancia()->database;
$cfg = NavidadTVS_Plugin::instancia()->settings;

global $wpdb;

$cuantos = 50;

foreach ( (array) $args as $arg ) {
	if ( ctype_digit( (string) $arg ) ) {
		$cuantos = max( 1, min( 500, (int) $arg ) );
	}
}

/*
 * La URL se resuelve por el nombre del servicio de Docker. site_url() devuelve
 * localhost:8080, que es la dirección vista desde el equipo, no desde dentro
 * del contenedor de wp-cli.
 */
$base = 'http://wordpress/wp-json/' . NAVIDAD_TVS_REST_NS;

/**
 * Lanza peticiones POST en paralelo y devuelve sus resultados.
 *
 * curl_multi y no un bucle de wp_remote_post: en serie no hay concurrencia, y
 * lo que se quiere medir es justamente qué pasa cuando llegan todas juntas.
 *
 * @param string $url     Destino.
 * @param array  $cuerpos Un cuerpo JSON por petición.
 * @param string $metodo  'POST' o 'GET'.
 * @return array Lista de array{codigo:int, cuerpo:string, ms:float}.
 */
function disparar( $url, array $cuerpos, $metodo = 'POST' ) {
	$multi  = curl_multi_init();
	$mangos = array();

	foreach ( $cuerpos as $i => $cuerpo ) {
		$ch = curl_init( $url );

		$opciones = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
		);

		if ( 'POST' === $metodo ) {
			$opciones[ CURLOPT_POST ]       = true;
			$opciones[ CURLOPT_POSTFIELDS ] = wp_json_encode( $cuerpo );
			$opciones[ CURLOPT_HTTPHEADER ] = array( 'Content-Type: application/json' );
		}

		curl_setopt_array( $ch, $opciones );

		curl_multi_add_handle( $multi, $ch );
		$mangos[ $i ] = $ch;
	}

	$inicio  = microtime( true );
	$activas = null;

	do {
		$estado = curl_multi_exec( $multi, $activas );

		if ( $activas ) {
			curl_multi_select( $multi, 1.0 );
		}
	} while ( $activas && CURLM_OK === $estado );

	$resultados = array();

	foreach ( $mangos as $i => $ch ) {
		$resultados[ $i ] = array(
			'codigo' => (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ),
			'cuerpo' => (string) curl_multi_getcontent( $ch ),
			'ms'     => curl_getinfo( $ch, CURLINFO_TOTAL_TIME ) * 1000,
		);

		curl_multi_remove_handle( $multi, $ch );
		curl_close( $ch );
	}

	curl_multi_close( $multi );

	$resultados['_pared_ms'] = ( microtime( true ) - $inicio ) * 1000;

	return $resultados;
}

/**
 * Resume una tanda de latencias.
 *
 * Se mira la mediana y el percentil 95, no el promedio: con una sola petición
 * lenta el promedio se dispara y deja de describir lo que siente la mayoría,
 * mientras que el p95 dice cuánto espera el que peor lo pasa de cada veinte.
 *
 * @param array  $resultados Salida de disparar().
 * @param string $titulo     Cómo se llama la fase.
 * @return array{ok:int, fallos:array<int,int>}
 */
function resumir( array $resultados, $titulo ) {
	$pared = $resultados['_pared_ms'];
	unset( $resultados['_pared_ms'] );

	$tiempos = array();
	$codigos = array();

	foreach ( $resultados as $r ) {
		$tiempos[]                 = $r['ms'];
		$codigos[ $r['codigo'] ]   = ( $codigos[ $r['codigo'] ] ?? 0 ) + 1;
	}

	sort( $tiempos );

	$n   = count( $tiempos );
	$p   = static function ( $q ) use ( $tiempos, $n ) {
		return $tiempos[ max( 0, min( $n - 1, (int) ceil( $q * $n ) - 1 ) ) ];
	};

	printf( "\n--- %s ---\n", $titulo );
	printf( "  peticiones      : %d en %.0f ms de reloj\n", $n, $pared );
	printf( "  throughput      : %.1f peticiones/s\n", $n / max( 0.001, $pared / 1000 ) );
	printf( "  mediana         : %.0f ms\n", $p( 0.5 ) );
	printf( "  p95             : %.0f ms\n", $p( 0.95 ) );
	printf( "  máximo          : %.0f ms\n", end( $tiempos ) );

	ksort( $codigos );

	foreach ( $codigos as $codigo => $cuantas ) {
		printf( "  HTTP %-3d        : %d\n", $codigo, $cuantas );
	}

	return array(
		'ok'     => $codigos[200] ?? 0,
		'fallos' => $codigos,
	);
}

/** Borra lo que creó esta prueba. */
function limpiar( $db ) {
	global $wpdb;

	$wpdb->query( // phpcs:ignore
		"DELETE s FROM {$db->tabla_sesiones} s
		  JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		 WHERE p.placa LIKE 'ZZZ%'"
	);
	$wpdb->query( // phpcs:ignore
		"DELETE s FROM {$db->tabla_scores} s
		  JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		 WHERE p.placa LIKE 'ZZZ%'"
	);
	$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE placa LIKE 'ZZZ%'" ); // phpcs:ignore
}

// --- Preparación ------------------------------------------------------------
$hoy = NavidadTVS_Plugin::hoy();

$ventana_previa = array(
	'hora_inicio'        => $cfg->get( 'hora_inicio' ),
	'hora_fin'           => $cfg->get( 'hora_fin' ),
	'dias_habiles'       => $cfg->get( 'dias_habiles' ),
	'concurso_congelado' => $cfg->get( 'concurso_congelado' ),
);

limpiar( $db );

$telefonos = array();

for ( $i = 0; $i < $cuantos; $i++ ) {
	$telefono = '39' . str_pad( (string) ( 10000000 + $i ), 8, '0', STR_PAD_LEFT );

	$wpdb->insert( // phpcs:ignore
		$db->tabla_participantes,
		array(
			'telefono'                 => $telefono,
			'telefono_csv'             => '57' . $telefono,
			'cedula'                   => '99' . str_pad( (string) $i, 7, '0', STR_PAD_LEFT ),
			'placa'                    => 'ZZZ' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ),
			'fecha_concurso'           => $hoy,
			'marca'                    => 'TVS',
			'ciudad_propietario'       => 'Medellín',
			'departamento_propietario' => 'Antioquia',
		)
	);

	$telefonos[] = $telefono;
}

printf( "%d participantes de prueba para la jornada %s\n", count( $telefonos ), $hoy );

// La ventana se abre de par en par solo mientras dura la prueba.
$cfg->set(
	array(
		'hora_inicio'        => '00:00',
		'hora_fin'           => '23:59',
		'dias_habiles'       => array( 1, 2, 3, 4, 5, 6, 7 ),
		'concurso_congelado' => false,
	)
);

try {
	/*
	 * --- Fase 0: la primera oleada ------------------------------------------
	 *
	 * Esta medición existe porque la primera versión de esta prueba daba un
	 * resultado alarmante —10 peticiones por segundo— y la culpa no era del
	 * plugin. Una ráfaga de 50 peticiones tras un rato de calma tarda unos
	 * cinco segundos; la MISMA ráfaga repetida a continuación tarda menos de
	 * doscientos milisegundos. Lo que se paga es el arranque de los procesos
	 * del servidor web, que Apache va matando cuando no hay tráfico.
	 *
	 * Importa porque la ventana abre a las 12:00 en punto y la primera oleada
	 * de participantes llega justo entonces, sobre un servidor que lleva horas
	 * quieto. Esa gente sí va a esperar esos segundos.
	 *
	 * Se mide contra /estado, que solo lee, para que el coste sea del servidor
	 * y no de nuestras escrituras.
	 */
	$calentar = array_fill( 0, $cuantos, array() );

	// GET porque /estado solo lee; con POST devolvería 404 y el informe
	// parecería un fallo cuando lo que se mide es el arranque del servidor.
	$r0 = disparar( $base . '/estado', $calentar, 'GET' );
	resumir( $r0, 'Fase 0: primera oleada, servidor en frío  (/estado)' );

	$r0b = disparar( $base . '/estado', $calentar, 'GET' );
	resumir( $r0b, 'Fase 0 bis: la misma oleada, ya en caliente' );

	// --- Fase 1: acceso -----------------------------------------------------
	$cuerpos = array();

	foreach ( $telefonos as $i => $telefono ) {
		$cuerpos[] = array(
			'nombre'   => 'Carga ' . $i,
			'telefono' => $telefono,
			'acepta'   => true,
		);
	}

	$r1 = disparar( $base . '/acceso', $cuerpos );
	$s1 = resumir( $r1, 'Fase 1: /acceso  (validar padrón y emitir sesión)' );

	unset( $r1['_pared_ms'] );

	$tokens = array();

	foreach ( $r1 as $r ) {
		$datos = json_decode( $r['cuerpo'], true );

		if ( isset( $datos['token'] ) ) {
			$tokens[] = $datos['token'];
		}
	}

	printf( "  sesiones emitidas: %d\n", count( $tokens ) );
	printf( "  tokens distintos : %d  %s\n", count( array_unique( $tokens ) ), count( array_unique( $tokens ) ) === count( $tokens ) ? 'OK' : 'HAY REPETIDOS' );

	// --- Fase 2: iniciar carrera --------------------------------------------
	if ( empty( $tokens ) ) {
		echo "\nSin tokens no hay fase 2.\n";
	} else {
		$cuerpos2 = array();

		foreach ( $tokens as $token ) {
			$cuerpos2[] = array( 'token' => $token );
		}

		$r2 = disparar( $base . '/carrera/iniciar', $cuerpos2 );
		$s2 = resumir( $r2, 'Fase 2: /carrera/iniciar  (consumir intento y dar seed)' );

		unset( $r2['_pared_ms'] );

		$seeds = array();

		foreach ( $r2 as $r ) {
			$datos = json_decode( $r['cuerpo'], true );

			if ( isset( $datos['seed'] ) ) {
				$seeds[] = (int) $datos['seed'];
			}
		}

		printf( "  carreras iniciadas: %d\n", count( $seeds ) );
		printf( "  seeds distintos   : %d  %s\n", count( array_unique( $seeds ) ), count( array_unique( $seeds ) ) === count( $seeds ) ? 'OK' : 'HAY REPETIDOS' );
	}

	// --- Integridad ----------------------------------------------------------
	echo "\n--- Integridad ---\n";

	$sesiones = (int) $wpdb->get_var( // phpcs:ignore
		"SELECT COUNT(*) FROM {$db->tabla_sesiones} s
		   JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		  WHERE p.placa LIKE 'ZZZ%'"
	);

	$consumidas = (int) $wpdb->get_var( // phpcs:ignore
		"SELECT COUNT(*) FROM {$db->tabla_sesiones} s
		   JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		  WHERE p.placa LIKE 'ZZZ%' AND s.estado = 'consumida'"
	);

	$con_dos = (int) $wpdb->get_var( // phpcs:ignore
		"SELECT COUNT(*) FROM (
		   SELECT s.participante_id
		     FROM {$db->tabla_sesiones} s
		     JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		    WHERE p.placa LIKE 'ZZZ%' AND s.estado = 'consumida'
		    GROUP BY s.participante_id HAVING COUNT(*) > 1
		 ) t"
	);

	printf( "  sesiones creadas          : %d\n", $sesiones );
	printf( "  intentos consumidos       : %d\n", $consumidas );
	printf( "  %-25s : %d  %s\n", 'con DOS intentos gastados', $con_dos, 0 === $con_dos ? 'OK' : 'FALLA GRAVE' );
	printf( "  %-25s : %s\n", 'una sesión por persona', $sesiones <= $cuantos ? 'OK' : 'HAY DE MÁS (' . $sesiones . ')' );

	echo "\n--- Cómo leer esto ---\n";
	echo "Compara la fase 0 con la 0 bis. Si la primera es mucho más lenta, el coste\n";
	echo "es el arranque de los procesos del servidor web, no el plugin: la fase 1 va\n";
	echo "después y ya corre en caliente.\n\n";
	echo "Eso sí pasa el día del concurso: la ventana abre a las 12:00 sobre un\n";
	echo "servidor que lleva horas quieto, y la primera oleada paga esa espera. Se\n";
	echo "evita manteniéndolo caliente, con un cron que pida /estado cada pocos\n";
	echo "minutos desde las 11:45, o subiendo StartServers y MinSpareServers en\n";
	echo "Apache. Está anotado en docs/operacion-y-qa.md.\n";

} finally {
	$cfg->set( $ventana_previa );
	limpiar( $db );
	echo "\nVentana horaria restaurada y filas de prueba borradas.\n";
}
