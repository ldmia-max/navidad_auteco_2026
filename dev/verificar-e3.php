<?php
/**
 * Verificación manual de E3 (acceso, ventana horaria y sesión). Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e3.php
 *
 * ESCRIBE EN LA BASE DE DATOS. Crea participantes de prueba con teléfonos
 * 30099988xx, los usa y los borra al terminar. Deja la configuración como
 * estaba. Nunca correr en producción.
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

/** Código de error de un WP_Error, o 'ok' si no lo es. */
function codigo( $resultado ) {
	return is_wp_error( $resultado ) ? $resultado->get_error_code() : 'ok';
}

global $wpdb;

$plugin   = NavidadTVS_Plugin::instancia();
$db       = $plugin->database;
$settings = $plugin->settings;
$acceso   = $plugin->acceso;

$hoy = NavidadTVS_Plugin::hoy();

// Guardar la configuración para restaurarla al final.
$config_original = array(
	'hora_inicio'        => $settings->get( 'hora_inicio' ),
	'hora_fin'           => $settings->get( 'hora_fin' ),
	'dias_habiles'       => $settings->get( 'dias_habiles' ),
	'concurso_congelado' => $settings->get( 'concurso_congelado' ),
);

/** Deja la ventana abierta ahora mismo. */
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

/** Cierra la ventana. */
function cerrar_ventana( $settings ) {
	$settings->set(
		array(
			'hora_inicio'        => '03:00',
			'hora_fin'           => '03:30',
			'dias_habiles'       => array( 1, 2, 3, 4, 5, 6 ),
			'concurso_congelado' => false,
		)
	);
}

/** Inserta un participante de prueba. */
function sembrar( $db, $sufijo, $fecha, $marca = 'TVS', $estado = 'habilitado' ) {
	global $wpdb;
	$telefono = '30099988' . $sufijo;
	$wpdb->insert(
		$db->tabla_participantes,
		array(
			'telefono'       => $telefono,
			'telefono_csv'   => '57' . $telefono,
			'cedula'         => '99999999' . $sufijo,
			'placa'          => 'TST' . $sufijo . 'T',
			'fecha_concurso' => $fecha,
			'marca'          => $marca,
			'estado'         => $estado,
		)
	);
	return $telefono;
}

// Limpieza previa por si quedó algo de una corrida anterior. Los contadores
// del limitador viven en transients y sobreviven entre corridas: sin borrarlos,
// la segunda ejecución empieza con los teléfonos de prueba ya bloqueados.
for ( $i = 1; $i <= 11; $i++ ) {
	NavidadTVS_Rate_Limit::limpiar( sprintf( 'tel:30099988%02d', $i ) );
}
NavidadTVS_Rate_Limit::limpiar( 'tel:3019998877' );
NavidadTVS_Rate_Limit::limpiar( 'tel:3019990000' );
// Bajo WP-CLI, ip() devuelve 127.0.0.1: sin limpiarla, cada corrida hereda los
// fallos de la anterior y a la tercera el script se bloquea a sí mismo.
NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );

$wpdb->query( "DELETE s FROM {$db->tabla_scores} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30099988%'" );
$wpdb->query( "DELETE s FROM {$db->tabla_sesiones} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30099988%'" );
$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE telefono LIKE '30099988%'" );

$tel_ok        = sembrar( $db, '01', $hoy );
$tel_otro_dia  = sembrar( $db, '02', '2026-12-25' );
$tel_otra_marca = sembrar( $db, '03', $hoy, 'Victory' );
$tel_descal    = sembrar( $db, '04', $hoy, 'TVS', 'descalificado' );
$tel_ya_jugo   = sembrar( $db, '05', $hoy );
$tel_reuso     = sembrar( $db, '06', $hoy );

$datos_base = array(
	'nombre'    => 'Participante De Prueba',
	'acepta'    => true,
	'turnstile' => '',
);

// =====================================================================
echo "=== Ventana cerrada ===\n";
cerrar_ventana( $settings );

comprobar(
	'acceso fuera de horario',
	'fuera_de_horario',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ok ) ) ) )
);

$estado = $acceso->estado_ventana();
comprobar( 'estado_ventana dice cerrada', false, $estado['abierta'] );
comprobar( 'hay mensaje de proxima apertura', true, '' !== $estado['mensaje'] );
printf( "     horario: %s\n", $estado['horario'] );
printf( "     mensaje: %s\n", $estado['mensaje'] );

// =====================================================================
echo "\n=== Concurso congelado ===\n";
abrir_ventana( $settings );
$settings->set( array( 'concurso_congelado' => true ) );

comprobar(
	'congelado bloquea aunque la hora sirva',
	'fuera_de_horario',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ok ) ) ) )
);

$settings->set( array( 'concurso_congelado' => false ) );

// =====================================================================
echo "\n=== Validacion de los datos del formulario ===\n";

comprobar(
	'sin aceptar terminos',
	'sin_terminos',
	codigo( $acceso->validar( array( 'telefono' => $tel_ok, 'nombre' => 'Pepe Perez', 'acepta' => false ) ) )
);

comprobar(
	'nombre vacio',
	'nombre_invalido',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ok, 'nombre' => '   ' ) ) ) )
);

comprobar(
	'nombre solo numeros',
	'nombre_invalido',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ok, 'nombre' => '12345' ) ) ) )
);

comprobar(
	'telefono fijo',
	'telefono_invalido',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => '6012345678' ) ) ) )
);

comprobar(
	'telefono vacio',
	'telefono_invalido',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => '' ) ) ) )
);

// =====================================================================
echo "\n=== Elegibilidad ===\n";

comprobar(
	'telefono que no esta en el padron',
	'no_elegible',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => '3019998877' ) ) ) )
);

comprobar(
	'hoy no es su jornada',
	'no_elegible',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_otro_dia ) ) ) )
);

comprobar(
	'marca distinta de TVS',
	'no_elegible',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_otra_marca ) ) ) )
);

comprobar(
	'participante descalificado',
	'descalificado',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_descal ) ) ) )
);

// Los tres primeros casos comparten mensaje a propósito: distinguirlos
// convertiría el formulario en un buscador de quién compró una moto TVS.
$m1 = $acceso->validar( array_merge( $datos_base, array( 'telefono' => '3019998877' ) ) )->get_error_message();
$m2 = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_otro_dia ) ) )->get_error_message();
comprobar( 'mismo mensaje para inexistente y otro dia', true, $m1 === $m2 );

// =====================================================================
echo "\n=== Acceso valido ===\n";

$ok = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ok, 'nombre' => '  Juan   Camilo  Pérez  ' ) ) );

comprobar( 'acceso concedido', 'ok', codigo( $ok ) );

if ( ! is_wp_error( $ok ) ) {
	comprobar( 'nombre con espacios colapsados', 'Juan Camilo Pérez', $ok['nombre'] );
	comprobar( 'token de 64 caracteres', 64, strlen( $ok['token'] ) );
	comprobar( 'duracion de la carrera', 90, $ok['duracion'] );

	$sesion = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$db->tabla_sesiones} WHERE nonce = %s", $ok['token'] ),
		ARRAY_A
	);

	comprobar( 'sesion en estado emitida', 'emitida', $sesion['estado'] );
	comprobar( 'consentimiento registrado', '1', $sesion['acepto_terminos'] );
	comprobar( 'version de terminos guardada', (string) $settings->get( 'terminos_version' ), $sesion['terminos_version'] );
	comprobar( 'seed dentro del rango uint32', true, (int) $sesion['seed'] >= 0 && (int) $sesion['seed'] <= 4294967295 );
	comprobar( 'el seed NO viaja al cliente', false, isset( $ok['seed'] ) );
}

// =====================================================================
echo "\n=== Reutilizacion de la sesion ===\n";

$primero = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_reuso ) ) );
$segundo = $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_reuso ) ) );

comprobar( 'primer acceso concedido', 'ok', codigo( $primero ) );
comprobar( 'segundo acceso concedido', 'ok', codigo( $segundo ) );

if ( is_wp_error( $primero ) ) {
	printf( "     motivo del primero: %s
", $primero->get_error_message() );
} elseif ( is_wp_error( $segundo ) ) {
	printf( "     motivo del segundo: %s
", $segundo->get_error_message() );
} else {
	comprobar( 'devuelve el mismo token', $primero['token'], $segundo['token'] );
}

$cuantas = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$db->tabla_sesiones} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono = %s",
		$tel_reuso
	)
);
comprobar( 'no se duplicaron filas de sesion', 1, $cuantas );

// =====================================================================
echo "\n=== Un solo intento ===\n";

// Caso A: ya tiene score registrado.
$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$db->tabla_participantes} WHERE telefono = %s", $tel_ya_jugo ) );
$wpdb->insert(
	$db->tabla_scores,
	array(
		'participante_id' => $pid,
		'sesion_id'       => 0,
		'nombre'          => 'Ya Jugo',
		'fecha_concurso'  => $hoy,
		'distancia_m'     => 1234,
		'seed'            => 1,
		'inputs'          => '',
	)
);

comprobar(
	'con score registrado no vuelve a entrar',
	'ya_participo',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ya_jugo ) ) ) )
);

// Caso B: sesión ya consumida (arrancó la carrera y no llegó resultado).
$pid_ok = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$db->tabla_participantes} WHERE telefono = %s", $tel_ok ) );
$wpdb->update( $db->tabla_sesiones, array( 'estado' => 'consumida' ), array( 'participante_id' => $pid_ok ) );

comprobar(
	'con la carrera arrancada no hay reintento',
	'ya_participo',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_ok ) ) ) )
);

// =====================================================================
echo "\n=== Limite de intentos ===\n";

// Se usa un número que no está en el padrón, que es justo lo que haría quien
// intenta averiguar quién compró una moto. Con nombre válido, para que el
// intento llegue hasta la consulta del padrón y cuente como fallo.
$tel_enumera = '3019990000';

for ( $i = 0; $i < NavidadTVS_Acceso::TOPE_TELEFONO + 2; $i++ ) {
	$codigos[] = codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_enumera ) ) ) );
}
printf( "     codigos: %s\n", implode( ', ', $codigos ) );

comprobar(
	'los primeros ' . NavidadTVS_Acceso::TOPE_TELEFONO . ' intentos consultan el padron',
	array_fill( 0, NavidadTVS_Acceso::TOPE_TELEFONO, 'no_elegible' ),
	array_slice( $codigos, 0, NavidadTVS_Acceso::TOPE_TELEFONO )
);
comprobar( 'a partir del tope se bloquea', 'demasiados_intentos', $codigos[ NavidadTVS_Acceso::TOPE_TELEFONO ] );
comprobar( 'y sigue bloqueado', 'demasiados_intentos', $codigos[ NavidadTVS_Acceso::TOPE_TELEFONO + 1 ] );

// Un acceso correcto no debe gastar cupo del teléfono.
$tel_limpia = sembrar( $db, '07', $hoy );
NavidadTVS_Rate_Limit::registrar_fallo( 'tel:' . $tel_limpia, 600 );
NavidadTVS_Rate_Limit::registrar_fallo( 'tel:' . $tel_limpia, 600 );
comprobar( 'acceso valido tras dos fallos', 'ok', codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_limpia ) ) ) ) );
comprobar( 'el acierto limpia el contador', false, NavidadTVS_Rate_Limit::bloqueado( 'tel:' . $tel_limpia, 1 ) );

NavidadTVS_Rate_Limit::limpiar( 'tel:' . $tel_enumera );
NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );

// Los errores que no revelan nada del padrón no deben gastar cupo. Si lo
// gastaran, quien insiste a las 11:59 llegaría bloqueado a las 12:00.
$tel_formato = sembrar( $db, '08', $hoy );

for ( $i = 0; $i < NavidadTVS_Acceso::TOPE_TELEFONO + 3; $i++ ) {
	$acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_formato, 'nombre' => '' ) ) );
}
comprobar(
	'los errores de nombre no gastan cupo',
	'ok',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_formato ) ) ) )
);

// Lo mismo para los intentos fuera de horario.
$tel_horario = sembrar( $db, '09', $hoy );
cerrar_ventana( $settings );
for ( $i = 0; $i < NavidadTVS_Acceso::TOPE_TELEFONO + 3; $i++ ) {
	$acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_horario ) ) );
}
abrir_ventana( $settings );
comprobar(
	'insistir antes de la apertura no bloquea',
	'ok',
	codigo( $acceso->validar( array_merge( $datos_base, array( 'telefono' => $tel_horario ) ) ) )
);

echo "\n=== Normalizacion del telefono en el acceso ===\n";
$tel_norm = sembrar( $db, '10', $hoy );

$por_indicativo = $acceso->validar( array_merge( $datos_base, array( 'telefono' => '57' . $tel_norm ) ) );
comprobar( 'entra digitando el numero con indicativo', 'ok', codigo( $por_indicativo ) );

$tel_norm2 = sembrar( $db, '11', $hoy );
$con_guiones = $acceso->validar(
	array_merge( $datos_base, array( 'telefono' => substr( $tel_norm2, 0, 3 ) . '-' . substr( $tel_norm2, 3, 3 ) . '-' . substr( $tel_norm2, 6 ) ) )
);
comprobar( 'entra digitando el numero con guiones', 'ok', codigo( $con_guiones ) );

// =====================================================================
echo "\n=== Render del shortcode ===\n";

/*
 * Regresión: los assets se registraban en 'wp_enqueue_scripts', pero con un
 * tema de bloques el contenido se renderiza antes de ese hook. El handle no
 * existía todavía, wp_localize_script() devolvía false y la página salía sin
 * el objeto NAVIDAD_TVS: el fetch iba contra undefined, recibía el HTML del
 * 404 y el formulario mostraba "Unexpected token '<'".
 */
abrir_ventana( $settings );

wp_scripts()->registered['navidad-tvs-acceso']->extra['data'] = '';
$html_abierto = do_shortcode( '[' . NavidadTVS_Shortcode::TAG . ']' );
$datos_js     = (string) wp_scripts()->get_data( 'navidad-tvs-acceso', 'data' );

comprobar( 'el script quedo registrado antes del render', true, wp_script_is( 'navidad-tvs-acceso', 'registered' ) );
comprobar( 'la pagina lleva el objeto NAVIDAD_TVS', true, false !== strpos( $datos_js, 'var NAVIDAD_TVS' ) );
// wp_json_encode escapa las barras, así que se comparan sin ellas.
comprobar( 'lleva la URL del endpoint de acceso', true, false !== strpos( stripslashes( $datos_js ), rest_url( NAVIDAD_TVS_REST_NS . '/acceso' ) ) );
comprobar( 'con la ventana abierta se pinta el formulario', true, false !== strpos( $html_abierto, 'id="ntvs-form"' ) );

cerrar_ventana( $settings );
$html_cerrado = do_shortcode( '[' . NavidadTVS_Shortcode::TAG . ']' );

comprobar( 'fuera de horario NO existe el formulario', false, strpos( $html_cerrado, 'id="ntvs-form"' ) !== false );
comprobar( 'fuera de horario NO existe el campo de telefono', false, strpos( $html_cerrado, 'id="ntvs-telefono"' ) !== false );
comprobar( 'fuera de horario se muestra el aviso', true, false !== strpos( $html_cerrado, 'ntvs--cerrado' ) );

// =====================================================================
// Limpieza
// =====================================================================
$wpdb->query( "DELETE s FROM {$db->tabla_scores} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30099988%'" );
$wpdb->query( "DELETE s FROM {$db->tabla_sesiones} s JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.telefono LIKE '30099988%'" );
$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE telefono LIKE '30099988%'" );

NavidadTVS_Rate_Limit::limpiar( 'ip:' . NavidadTVS_Rate_Limit::ip() );
$settings->set( $config_original );

echo "\n(configuracion restaurada y participantes de prueba borrados)\n";
echo "\n";
$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
