<?php
/**
 * Prueba el flujo completo contra la API REST de verdad, por HTTP.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/probar-endpoint-e6.php
 *
 * verificar-e6.php llama al validador en PHP; esto pasa por la ruta REST, que
 * es por donde entra un atacante. Hace lo que haría alguien con las DevTools
 * abiertas: pedir el seed, inventarse el resultado, mandarlo dos veces.
 *
 * ESCRIBE EN LA BASE DE DATOS y limpia lo suyo al terminar. Necesita la
 * ventana de participación abierta (dev/abrir-ventana.php abrir).
 *
 * @package NavidadTVS
 */

$GLOBALS['fallos'] = 0;

function afirmar( $etiqueta, $condicion, $detalle = '' ) {
	if ( ! $condicion ) {
		$GLOBALS['fallos']++;
	}
	printf( "%s %-52s %s\n", $condicion ? 'OK  ' : 'FALLA', $etiqueta, $detalle );
}

/**
 * Llama a una ruta del plugin como lo haría el navegador.
 *
 * Se usa WP_REST_Request en vez de una petición HTTP real porque desde el
 * contenedor de WP-CLI la URL pública no siempre resuelve. Pasa por el mismo
 * despachador, los mismos args y los mismos permission_callback, que es lo
 * que interesa comprobar.
 *
 * @param string $ruta   Ruta sin el namespace.
 * @param array  $cuerpo Parámetros.
 * @param string $metodo Verbo.
 * @return array status y datos.
 */
function llamar( $ruta, $cuerpo = array(), $metodo = 'POST' ) {
	$peticion = new WP_REST_Request( $metodo, '/' . NAVIDAD_TVS_REST_NS . $ruta );
	$peticion->set_header( 'Content-Type', 'application/json' );
	$peticion->set_body( wp_json_encode( $cuerpo ) );

	$respuesta = rest_do_request( $peticion );

	return array(
		'status' => $respuesta->get_status(),
		'datos'  => $respuesta->get_data(),
	);
}

require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-simulacion.php';

global $wpdb;

$plugin = NavidadTVS_Plugin::instancia();
$db     = $plugin->database;

$hoy      = NavidadTVS_Plugin::ahora()->format( 'Y-m-d' );
$cedula   = '9900000002';
$telefono = '3009990002';

// --- Participante de pruebas -----------------------------------------------
$wpdb->query( $wpdb->prepare( "DELETE s FROM {$db->tabla_scores} s INNER JOIN {$db->tabla_participantes} p ON p.id = s.participante_id WHERE p.cedula = %s", $cedula ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE ss FROM {$db->tabla_sesiones} ss INNER JOIN {$db->tabla_participantes} p ON p.id = ss.participante_id WHERE p.cedula = %s", $cedula ) ); // phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$db->tabla_participantes} WHERE cedula = %s", $cedula ) ); // phpcs:ignore

$wpdb->insert(
	$db->tabla_participantes,
	array(
		'telefono'                 => $telefono,
		'telefono_csv'             => '57' . $telefono,
		'cedula'                   => $cedula,
		'placa'                    => 'ZZZ98Z',
		'marca'                    => NAVIDAD_TVS_MARCA,
		'fecha_concurso'           => $hoy,
		'ciudad_propietario'       => 'BOGOTA',
		'departamento_propietario' => 'CUNDINAMARCA',
	)
);
$participante_id = (int) $wpdb->insert_id;

echo "=== Ventana ===\n";
$estado = llamar( '/estado', array(), 'GET' );
afirmar( 'la ventana está abierta', ! empty( $estado['datos']['abierta'] ), $estado['datos']['mensaje'] );

if ( empty( $estado['datos']['abierta'] ) ) {
	echo "\nAbrir la ventana antes: dev/abrir-ventana.php abrir\n";
	$wpdb->delete( $db->tabla_participantes, array( 'id' => $participante_id ) );
	echo "1 FALLO(S)\n";
	return;
}

echo "\n=== Acceso e inicio ===\n";
$acceso = llamar( '/acceso', array( 'telefono' => $telefono, 'nombre' => 'PRUEBA REST', 'acepta' => true ) );
afirmar( 'el acceso responde 200', 200 === $acceso['status'], 'status ' . $acceso['status'] );

$token = isset( $acceso['datos']['token'] ) ? $acceso['datos']['token'] : '';
afirmar( 'devuelve un token', '' !== $token );

// Terminar antes de iniciar no puede funcionar: la sesión sigue emitida.
$antes = llamar( '/carrera/terminar', array( 'token' => $token, 'entradas' => 'AAA=', 'distancia' => 1 ) );
afirmar( 'terminar sin haber iniciado se rechaza', 403 === $antes['status'], 'status ' . $antes['status'] );

$iniciar = llamar( '/carrera/iniciar', array( 'token' => $token ) );
afirmar( 'iniciar responde 200', 200 === $iniciar['status'], 'status ' . $iniciar['status'] );

$seed = isset( $iniciar['datos']['seed'] ) ? (int) $iniciar['datos']['seed'] : -1;
afirmar( 'entrega el seed', $seed >= 0, 'seed ' . $seed );

echo "\n=== El intento de hacer trampa ===\n";

/*
 * La sesión se acaba de consumir, así que la carrera "duró" cero segundos y el
 * validador la va a marcar inválida por el reloj. Para probar la parte de
 * seguridad se retrasa consumida_en a mano, que es lo mismo que habría pasado
 * si el participante se hubiera sentado a jugar los noventa segundos.
 */
$wpdb->query(
	$wpdb->prepare(
		"UPDATE {$db->tabla_sesiones} SET consumida_en = %s WHERE nonce = %s", // phpcs:ignore
		gmdate( 'Y-m-d H:i:s', time() - 95 ),
		$token
	)
);

$registro = NavidadTVS_Sim_Entradas::codificar(
	array_fill( 0, NAVIDAD_TVS_TOTAL_TICKS, NavidadTVS_Sim_Entradas::BIT_ACELERA )
);

$honesta = NavidadTVS_Sim_Simulacion::distancia_metros(
	NavidadTVS_Sim_Simulacion::simular( $seed, NavidadTVS_Sim_Entradas::decodificar( $registro, NAVIDAD_TVS_TOTAL_TICKS ), NAVIDAD_TVS_TOTAL_TICKS )
);

// Esto es exactamente lo que haría alguien editando el fetch desde la consola:
// mandar el registro real pero con una distancia inventada.
$trampa = llamar(
	'/carrera/terminar',
	array( 'token' => $token, 'entradas' => $registro, 'distancia' => 99999 )
);

afirmar( 'el envío responde 200', 200 === $trampa['status'], 'status ' . $trampa['status'] );
afirmar(
	'el servidor ignora la distancia inventada',
	isset( $trampa['datos']['distancia'] ) && (int) $trampa['datos']['distancia'] === $honesta,
	sprintf( 'pidió 99999, le dieron %d', isset( $trampa['datos']['distancia'] ) ? $trampa['datos']['distancia'] : -1 )
);

$fila = $db->score_de( $participante_id );
afirmar( 'lo guardado es lo que calculó el servidor', null !== $fila && (int) $fila['distancia_m'] === $honesta, sprintf( '%d m', null !== $fila ? $fila['distancia_m'] : -1 ) );
afirmar( 'queda anotado lo que dijo el cliente', null !== $fila && 99999 === (int) $fila['distancia_cliente_m'], null !== $fila ? $fila['distancia_cliente_m'] : '' );

$repetido = llamar( '/carrera/terminar', array( 'token' => $token, 'entradas' => $registro, 'distancia' => 1 ) );
afirmar( 'el segundo envío se rechaza', 409 === $repetido['status'], 'status ' . $repetido['status'] );

$reiniciar = llamar( '/carrera/iniciar', array( 'token' => $token ) );
afirmar( 'no se puede volver a iniciar', 409 === $reiniciar['status'], 'status ' . $reiniciar['status'] );

$otro_acceso = llamar( '/acceso', array( 'telefono' => $telefono, 'nombre' => 'OTRA VEZ', 'acepta' => true ) );
afirmar( 'el mismo teléfono ya no entra', 200 !== $otro_acceso['status'], 'status ' . $otro_acceso['status'] );

// --- Limpieza ------------------------------------------------------------------
$wpdb->delete( $db->tabla_scores, array( 'participante_id' => $participante_id ) );
$wpdb->delete( $db->tabla_sesiones, array( 'participante_id' => $participante_id ) );
$wpdb->delete( $db->tabla_participantes, array( 'id' => $participante_id ) );

echo "\n(participante de prueba borrado)\n\n";

$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
