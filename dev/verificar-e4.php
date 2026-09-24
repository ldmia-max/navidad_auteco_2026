<?php
/**
 * Verificación manual de E4 (inicio de carrera y bundle del juego). Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e4.php
 *
 * La física se verifica aparte, en el banco de pruebas de TypeScript:
 *   cd game && npm run sim
 *
 * ESCRIBE EN LA BASE DE DATOS. Crea participantes de prueba con teléfonos
 * 30088877xx y los borra al terminar. Nunca correr en producción.
 *
 * @package NavidadTVS
 */

$GLOBALS['fallos'] = 0;

function comprobar( $etiqueta, $esperado, $obtenido ) {
	$ok = ( $esperado === $obtenido );
	if ( ! $ok ) {
		$GLOBALS['fallos']++;
	}
	printf(
		"%s %-54s esperado=%-20s obtenido=%s\n",
		$ok ? 'OK  ' : 'FALLA',
		$etiqueta,
		var_export( $esperado, true ),
		var_export( $obtenido, true )
	);
}

function codigo( $resultado ) {
	return is_wp_error( $resultado ) ? $resultado->get_error_code() : 'ok';
}

global $wpdb;

$plugin   = NavidadTVS_Plugin::instancia();
$db       = $plugin->database;
$settings = $plugin->settings;
$acceso   = $plugin->acceso;
$hoy      = NavidadTVS_Plugin::hoy();

$config_original = array(
	'hora_inicio'        => $settings->get( 'hora_inicio' ),
	'hora_fin'           => $settings->get( 'hora_fin' ),
	'dias_habiles'       => $settings->get( 'dias_habiles' ),
	'concurso_congelado' => $settings->get( 'concurso_congelado' ),
);

function abrir_ventana( $settings ) {
	$ahora = NavidadTVS_Plugin::ahora();
	$settings->set(
		array(
			'hora_inicio'        => $ahora->modify( '-30 minutes' )->format( 'H:i' ),
			'hora_fin'           => $ahora->modify( '+30 minutes' )->format( 'H:i' ),
			'dias_habiles'       => array( 1, 2, 3, 4, 5, 6, 7 ),
			'concurso_congelado' => false,
		)
	);
}

function sembrar( $db, $sufijo, $fecha ) {
	global $wpdb;
	$telefono = '30088877' . $sufijo;
	$wpdb->insert(
		$db->tabla_participantes,
		array(
			'telefono'       => $telefono,
			'telefono_csv'   => '57' . $telefono,
			'cedula'         => '88888888' . $sufijo,
			'placa'          => 'E4T' . $sufijo . 'T',
			'fecha_concurso' => $fecha,
			'marca'          => 'TVS',
		)
	);
	return $telefono;
}

// Limpieza previa.
for ( $i = 1; $i <= 9; $i++ ) {
	NavidadTVS_Rate_Limit::limpiar( sprintf( 'tel:30088877%02d', $i ) );
	NavidadTVS_Rate_Limit::limpiar( sprintf( 'tel:30088877%02d', $i ) );
}
NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );
$wpdb->query( "DELETE s FROM {$db->tabla_scores} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30088877%'" );
$wpdb->query( "DELETE s FROM {$db->tabla_sesiones} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30088877%'" );
$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE telefono LIKE '30088877%'" );

abrir_ventana( $settings );

$datos_base = array( 'nombre' => 'Piloto De Prueba', 'acepta' => true, 'turnstile' => '' );

// =====================================================================
echo "=== Bundle del juego ===\n";

$ruta_bundle = NAVIDAD_TVS_PATH . 'assets/game/juego.js';
comprobar( 'el bundle está compilado', true, file_exists( $ruta_bundle ) );

if ( file_exists( $ruta_bundle ) ) {
	printf( "     tamaño: %s\n", size_format( filesize( $ruta_bundle ) ) );
	$fuente = file_get_contents( $ruta_bundle );
	comprobar( 'expone navidadTvsIniciarJuego', true, false !== strpos( $fuente, 'navidadTvsIniciarJuego' ) );
}

/*
 * El determinismo se comprueba sobre NUESTRA simulación, no sobre el bundle
 * entero: Phaser usa Math.random por dentro para sus propias utilidades y eso
 * es irrelevante, porque src/sim/ no depende de Phaser ni lo llama.
 */
echo "\n=== Determinismo del código de simulación ===\n";

$dir_sim = NAVIDAD_TVS_PATH . 'game/src/sim';

if ( ! is_dir( $dir_sim ) ) {
	echo "     (no está el fuente: se omite, el zip de producción no lo lleva)\n";
} else {
	$reloj = array( 'Date.now', 'performance.now', 'new Date' );

	/*
	 * Las herramientas de src/sim/ no son la simulación: el banco de pruebas
	 * mide cuánto tarda una carrera y el exportador de vectores anota cuándo se
	 * generó el archivo. Leer el reloj ahí es legítimo.
	 *
	 * Math.random NO se les perdona. Si una herramienta sorteara algo, los
	 * vectores de paridad cambiarían en cada regeneración y el diff dejaría de
	 * decir nada.
	 */
	$herramientas = array( 'banco-pruebas.ts', 'exportar-vectores.ts' );

	foreach ( glob( $dir_sim . '/*.ts' ) as $archivo ) {
		$nombre    = basename( $archivo );
		$contenido = file_get_contents( $archivo );

		// Se quitan los comentarios: varios explican precisamente por qué no se
		// usa Math.random, y nombrarlo no es usarlo.
		$contenido = preg_replace( '#/\*.*?\*/#s', '', $contenido );
		$contenido = preg_replace( '#//.*$#m', '', $contenido );

		$prohibido = in_array( $nombre, $herramientas, true )
			? array( 'Math.random' )
			: array_merge( array( 'Math.random' ), $reloj );

		foreach ( $prohibido as $patron ) {
			comprobar( sprintf( '%s sin %s', $nombre, $patron ), 0, substr_count( $contenido, $patron ) );
		}
	}
}

// =====================================================================
echo "\n=== Inicio de carrera ===\n";

$tel = sembrar( $db, '01', $hoy );
$ok  = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel ) ) );
comprobar( 'acceso previo concedido', 'ok', codigo( $ok ) );

$token   = $ok['token'];
$carrera = $acceso->iniciar_carrera( $token );

comprobar( 'la carrera arranca', 'ok', codigo( $carrera ) );

if ( ! is_wp_error( $carrera ) ) {
	comprobar( 'entrega el seed', true, isset( $carrera['seed'] ) );
	comprobar( 'el seed es un uint32', true, is_int( $carrera['seed'] ) && $carrera['seed'] >= 0 && $carrera['seed'] <= 4294967295 );
	comprobar( 'entrega los ticks de la carrera', 5400, $carrera['ticks'] );
	comprobar( 'entrega la duración', 90, $carrera['duracion'] );
	comprobar( 'entrega el nombre del participante', 'Piloto De Prueba', $carrera['nombre'] );

	$seed_guardado = (int) $wpdb->get_var( $wpdb->prepare( "SELECT seed FROM {$db->tabla_sesiones} WHERE nonce = %s", $token ) );
	comprobar( 'el seed entregado es el que guardó el servidor', $seed_guardado, $carrera['seed'] );
}

$sesion = $db->buscar_sesion_por_nonce( $token );
comprobar( 'la sesión quedó consumida', 'consumida', $sesion['estado'] );
comprobar( 'quedó registrado cuándo se consumió', true, ! empty( $sesion['consumida_en'] ) );

// =====================================================================
echo "\n=== Un solo intento ===\n";

comprobar( 'el mismo token no arranca dos veces', 'ya_participo', codigo( $acceso->iniciar_carrera( $token ) ) );
comprobar( 'tampoco se puede volver a entrar', 'ya_participo', codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel ) ) ) ) );

// Doble pulsación del botón: el UPDATE condicionado tiene que dejar pasar una sola.
$tel_doble = sembrar( $db, '02', $hoy );
$acc       = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_doble ) ) );
$sid       = (int) $db->buscar_sesion_por_nonce( $acc['token'] )['id'];

$primera  = $db->consumir_sesion( $sid );
$segunda  = $db->consumir_sesion( $sid );
comprobar( 'la primera consumición gana', true, $primera );
comprobar( 'la segunda no afecta nada', false, $segunda );

// =====================================================================
echo "\n=== Tokens rechazados ===\n";

comprobar( 'token inventado', 'sesion_invalida', codigo( $acceso->iniciar_carrera( str_repeat( 'a', 64 ) ) ) );
comprobar( 'token vacío', 'sesion_invalida', codigo( $acceso->iniciar_carrera( '' ) ) );
comprobar( 'token de largo incorrecto', 'sesion_invalida', codigo( $acceso->iniciar_carrera( 'abc123' ) ) );

// Sesión caducada.
$tel_viejo = sembrar( $db, '03', $hoy );
$acc_viejo = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_viejo ) ) );
$wpdb->update(
	$db->tabla_sesiones,
	array( 'creada_en' => gmdate( 'Y-m-d H:i:s', time() - 3600 ) ),
	array( 'nonce' => $acc_viejo['token'] )
);
comprobar( 'sesión caducada', 'sesion_expirada', codigo( $acceso->iniciar_carrera( $acc_viejo['token'] ) ) );

// Fuera de horario.
$tel_tarde = sembrar( $db, '04', $hoy );
$acc_tarde = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_tarde ) ) );
$settings->set( array( 'hora_inicio' => '03:00', 'hora_fin' => '03:30' ) );
comprobar( 'la jornada cerró a mitad', 'fuera_de_horario', codigo( $acceso->iniciar_carrera( $acc_tarde['token'] ) ) );
abrir_ventana( $settings );

// =====================================================================
echo "\n=== Rutas REST ===\n";

$rutas = rest_get_server()->get_routes();
comprobar( 'existe /concurso/v1/carrera/iniciar', true, isset( $rutas['/' . NAVIDAD_TVS_REST_NS . '/carrera/iniciar'] ) );

// =====================================================================
// Limpieza
// =====================================================================
$wpdb->query( "DELETE s FROM {$db->tabla_scores} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30088877%'" );
$wpdb->query( "DELETE s FROM {$db->tabla_sesiones} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30088877%'" );
$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE telefono LIKE '30088877%'" );
NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );
$settings->set( $config_original );

echo "\n(configuracion restaurada y participantes de prueba borrados)\n\n";

$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
