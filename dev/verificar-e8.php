<?php
/**
 * Comprueba el backoffice: ranking, filtros, exportación, descalificación y
 * la reejecución que sostiene la auditoría.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e8.php
 *
 * ESCRIBE EN LA BASE DE DATOS, pero solo sobre filas suyas: crea participantes
 * con placa ZZZ… y sus scores, y los borra al terminar pase lo que pase. No
 * toca ninguna fila real.
 *
 * Los scores no se inventan: se generan corriendo el simulador de verdad con
 * un log de entradas sintético. Así la prueba de "el resultado se reproduce"
 * comprueba algo, en vez de comparar un número contra sí mismo.
 *
 * Script de desarrollo: nunca en producción.
 *
 * @package NavidadTVS
 */

$db  = NavidadTVS_Plugin::instancia()->database;
$cfg = NavidadTVS_Plugin::instancia()->settings;

require_once NAVIDAD_TVS_PATH . 'includes/admin/class-ranking.php';
require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-simulacion.php';

global $wpdb;

/**
 * Comprueba una condición y lleva la cuenta.
 *
 * Contadores en estáticas: `wp eval-file` ejecuta el archivo dentro de una
 * función, así que un `global` aquí apunta a otra variable y el marcador
 * saldría en cero teniendo fallos en pantalla.
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
 * Arma un log de entradas y lo corre por el simulador.
 *
 * @param int $seed   Seed de la pista.
 * @param int $patron Cada cuántos ticks se suelta el acelerador.
 * @return array{b64:string, estado:array}
 */
function correr( $seed, $patron ) {
	$ticks    = NAVIDAD_TVS_TOTAL_TICKS;
	$registro = array();

	for ( $t = 0; $t < $ticks; $t++ ) {
		// Bit 0: acelerador. Bit 1: turbo.
		$acelera = ( 0 === $patron ) ? 1 : ( ( $t % $patron ) < ( $patron - 10 ) ? 1 : 0 );
		$turbo   = ( $t % 300 ) < 90 ? 1 : 0;

		$registro[] = ( $acelera ? 1 : 0 ) | ( $turbo ? 2 : 0 );
	}

	$b64    = NavidadTVS_Sim_Entradas::codificar( $registro );
	$leidas = NavidadTVS_Sim_Entradas::decodificar( $b64, $ticks );

	if ( is_wp_error( $leidas ) ) {
		printf( "No se pudo codificar el log: %s\n", $leidas->get_error_message() );
		exit( 1 );
	}

	return array(
		'b64'    => $b64,
		'estado' => NavidadTVS_Sim_Simulacion::simular( $seed, $leidas, $ticks ),
	);
}

$hoy   = NavidadTVS_Plugin::hoy();
$ayer  = gmdate( 'Y-m-d', strtotime( $hoy . ' -1 day' ) );
$ids   = array();
$score_ids = array();

/** Borra todo lo que creó esta corrida. */
function limpiar( $db ) {
	global $wpdb;

	$wpdb->query( // phpcs:ignore
		"DELETE s FROM {$db->tabla_scores} s
		  JOIN {$db->tabla_participantes} p ON p.id = s.participante_id
		 WHERE p.placa LIKE 'ZZZ%'"
	);
	$wpdb->query( "DELETE FROM {$db->tabla_participantes} WHERE placa LIKE 'ZZZ%'" ); // phpcs:ignore
}

limpiar( $db );

// --- Datos de prueba --------------------------------------------------------
// Tres corredores hoy con distancias distintas y uno ayer, para que los
// filtros de fecha tengan algo que separar.
$semillas = array(
	array( 'sufijo' => 1, 'fecha' => $hoy,  'patron' => 0 ),    // sin soltar: el más lejos
	array( 'sufijo' => 2, 'fecha' => $hoy,  'patron' => 120 ),
	array( 'sufijo' => 3, 'fecha' => $hoy,  'patron' => 40 ),   // suelta mucho: el más corto
	array( 'sufijo' => 4, 'fecha' => $ayer, 'patron' => 0 ),
);

foreach ( $semillas as $i => $s ) {
	$wpdb->insert( // phpcs:ignore
		$db->tabla_participantes,
		array(
			'telefono'                 => '39000000' . str_pad( (string) $s['sufijo'], 2, '0', STR_PAD_LEFT ),
			'telefono_csv'             => '5739000000' . str_pad( (string) $s['sufijo'], 2, '0', STR_PAD_LEFT ),
			'cedula'                   => '990000' . $s['sufijo'],
			'placa'                    => 'ZZZ00' . $s['sufijo'],
			'fecha_concurso'           => $s['fecha'],
			'marca'                    => 'TVS',
			'ciudad_propietario'       => 'Medellín',
			'departamento_propietario' => 'Antioquia',
		)
	);

	$pid   = (int) $wpdb->insert_id;
	$ids[] = $pid;

	$seed  = 1000 + $s['sufijo'];
	$corre = correr( $seed, $s['patron'] );
	$e     = $corre['estado'];

	$sid = $db->registrar_score(
		array(
			'participante_id'          => $pid,
			'sesion_id'                => 0,
			'nombre'                   => 'Corredor ' . $s['sufijo'],
			'cedula'                   => '990000' . $s['sufijo'],
			'telefono'                 => '39000000' . str_pad( (string) $s['sufijo'], 2, '0', STR_PAD_LEFT ),
			'ciudad_propietario'       => 'Medellín',
			'departamento_propietario' => 'Antioquia',
			'fecha_concurso'           => $s['fecha'],
			'distancia_m'              => NavidadTVS_Sim_Simulacion::distancia_metros( $e ),
			'distancia_base_m'         => NavidadTVS_Sim_Simulacion::distancia_base_metros( $e ),
			'distancia_cliente_m'      => NavidadTVS_Sim_Simulacion::distancia_metros( $e ),
			'items_recogidos'          => $e['items'],
			'impulsores'               => $e['impulsores'],
			'caidas'                   => $e['caidas'],
			'sobrecalentamientos'      => $e['sobrecalentamientos'],
			'duracion_s'               => NAVIDAD_TVS_DURACION_SEGUNDOS,
			'seed'                     => $seed,
			'inputs'                   => $corre['b64'],
			'valido'                   => true,
			'motivo_descalificacion'   => '',
			'ip'                       => '127.0.0.1',
			'user_agent'               => 'prueba',
		)
	);

	$score_ids[] = (int) $sid;

	printf(
		"  corredor %d  jornada %s  %d m\n",
		$s['sufijo'],
		$s['fecha'],
		NavidadTVS_Sim_Simulacion::distancia_metros( $e )
	);
}

echo "\n";

try {
	$pantalla = new NavidadTVS_Ranking( $db, $cfg );

	/*
	 * Las comprobaciones van sobre INVARIANTES, no sobre cuentas absolutas.
	 *
	 * La base de desarrollo tiene padrón y carreras reales de las pruebas
	 * manuales, así que dar por hecho "hoy hay tres resultados" hace fallar la
	 * suite por un motivo que no es un defecto. Se comprueba lo que tiene que
	 * cumplirse con cualquier contenido: que el filtro no deje pasar otra
	 * fecha, que el orden sea descendente, que las páginas no se solapen y
	 * sumen el total, y que descalificar reste exactamente uno.
	 */

	/**
	 * Se queda solo con las filas que creó esta suite.
	 *
	 * @param array $filas Filas del ranking.
	 * @return array
	 */
	$mios = static function ( array $filas ) {
		return array_values(
			array_filter(
				$filas,
				static function ( $f ) {
					return 0 === strpos( (string) $f['nombre'], 'Corredor ' );
				}
			)
		);
	};

	// --- Orden y filtros ----------------------------------------------------
	echo "=== Ranking ===\n";

	$hoy_filas = $db->ranking_rango( $hoy, $hoy, 1, 500, false );
	$fechas    = array_unique( wp_list_pluck( $hoy_filas, 'fecha_concurso' ) );
	comprobar( 'el filtro de un día no deja pasar otra fecha', array( $hoy ) === array_values( $fechas ), implode( ',', $fechas ) );

	$distancias = array_map( 'intval', wp_list_pluck( $hoy_filas, 'distancia_m' ) );
	$ordenadas  = $distancias;
	rsort( $ordenadas );
	comprobar( 'ordena de mayor a menor distancia', $distancias === $ordenadas, implode( ' > ', array_slice( $distancias, 0, 6 ) ) );

	$corredores = $mios( $hoy_filas );
	comprobar( 'los tres de hoy están en el listado', 3 === count( $corredores ), count( $corredores ) . ' de los míos' );
	comprobar( 'el que no soltó el acelerador va primero de los míos', 'Corredor 1' === $corredores[0]['nombre'], $corredores[0]['nombre'] );

	$solo_ayer = $db->ranking_rango( $ayer, $ayer, 1, 500, false );
	$fechas_a  = array_unique( wp_list_pluck( $solo_ayer, 'fecha_concurso' ) );
	comprobar( 'el filtro de ayer no deja pasar hoy', ! in_array( $hoy, $fechas_a, true ), implode( ',', $fechas_a ) );
	comprobar( 'y el de ayer aparece ahí', 1 === count( $mios( $solo_ayer ) ) );

	comprobar( 'el conteo coincide con las filas', $db->contar_ranking_rango( $hoy, $hoy, false ) === count( $hoy_filas ) );

	$sin_filtro = $db->contar_ranking_rango( '', '', false );
	comprobar( 'sin filtro hay al menos tantos como en un día', $sin_filtro >= count( $hoy_filas ), (string) $sin_filtro );

	// --- Paginación ---------------------------------------------------------
	echo "\n=== Paginación ===\n";

	$total_hoy = $db->contar_ranking_rango( $hoy, $hoy, false );
	$tamano    = 2;
	$paginas   = (int) ceil( $total_hoy / $tamano );
	$vistos    = array();
	$repetidos = 0;

	for ( $p = 1; $p <= $paginas; $p++ ) {
		foreach ( $db->ranking_rango( $hoy, $hoy, $p, $tamano, false ) as $f ) {
			if ( isset( $vistos[ $f['id'] ] ) ) {
				$repetidos++;
			}
			$vistos[ $f['id'] ] = true;
		}
	}

	comprobar( 'recorrer todas las páginas da el total', count( $vistos ) === $total_hoy, count( $vistos ) . ' de ' . $total_hoy );
	comprobar( 'y ninguna fila sale dos veces', 0 === $repetidos, $repetidos . ' repetidas' );

	$ultima = $db->ranking_rango( $hoy, $hoy, $paginas, $tamano, false );
	comprobar( 'la última página no viene vacía', count( $ultima ) > 0 );

	$fuera = $db->ranking_rango( $hoy, $hoy, $paginas + 5, $tamano, false );
	comprobar( 'pedir una página que no existe devuelve vacío, no un error', array() === $fuera );

	// --- Reejecución --------------------------------------------------------
	echo "\n=== Reejecución (la vista de auditoría) ===\n";

	$auditar = new ReflectionMethod( $pantalla, 'auditar' );
	$auditar->setAccessible( true );

	$score = $db->buscar_score( $score_ids[0] );
	$a     = $auditar->invoke( $pantalla, $score );

	comprobar( 'un resultado legítimo se reproduce', ! empty( $a['reproducible'] ), isset( $a['error'] ) ? $a['error'] : implode( ',', array_keys( $a['diferencias'] ) ) );
	comprobar( 'y el cliente no desfasa', 0 === (int) $a['desfase_cliente'], (string) $a['desfase_cliente'] );

	/*
	 * Ahora se altera la distancia guardada a mano, como si alguien hubiera
	 * tocado la fila en la base. La auditoría tiene que cazarlo: si no, no
	 * sirve para nada.
	 */
	$wpdb->update( $db->tabla_scores, array( 'distancia_m' => 9999 ), array( 'id' => $score_ids[0] ), array( '%d' ), array( '%d' ) ); // phpcs:ignore
	$a2 = $auditar->invoke( $pantalla, $db->buscar_score( $score_ids[0] ) );

	comprobar( 'una fila manipulada NO se reproduce', empty( $a2['reproducible'] ) );
	comprobar( 'y señala el campo que no cuadra', isset( $a2['diferencias']['distancia_m'] ), implode( ',', array_keys( $a2['diferencias'] ) ) );

	$wpdb->update( $db->tabla_scores, array( 'distancia_m' => $score['distancia_m'] ), array( 'id' => $score_ids[0] ), array( '%d' ), array( '%d' ) ); // phpcs:ignore

	$roto           = $score;
	$roto['inputs'] = 'no-es-base64-valido!!';
	$a3             = $auditar->invoke( $pantalla, $roto );
	comprobar( 'un log ilegible se reporta y no revienta', isset( $a3['error'] ) );

	// --- Descalificación ----------------------------------------------------
	echo "\n=== Descalificación ===\n";

	$antes_validos = $db->contar_ranking_rango( $hoy, $hoy, false );
	$antes_todos   = $db->contar_ranking_rango( $hoy, $hoy, true );

	$r = $db->marcar_score_valido( $score_ids[1], false, '' );
	comprobar( 'no deja descalificar sin motivo', is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : 'lo permitió' );

	$r = $db->marcar_score_valido( $score_ids[1], false, 'Prueba automatizada' );
	comprobar( 'descalifica con motivo', true === $r );

	$f = $db->buscar_score( $score_ids[1] );
	comprobar( 'queda marcado como no válido', 0 === (int) $f['valido'] );
	comprobar( 'y guarda el motivo', 'Prueba automatizada' === $f['motivo_descalificacion'], $f['motivo_descalificacion'] );
	comprobar( 'el log de entradas sigue intacto', '' !== $f['inputs'] );

	comprobar(
		'el ranking pierde exactamente uno',
		$db->contar_ranking_rango( $hoy, $hoy, false ) === $antes_validos - 1,
		$db->contar_ranking_rango( $hoy, $hoy, false ) . ' frente a ' . $antes_validos
	);
	comprobar(
		'pero sigue ahí al incluir descalificados',
		$db->contar_ranking_rango( $hoy, $hoy, true ) === $antes_todos
	);

	$r = $db->marcar_score_valido( $score_ids[1], true );
	comprobar( 'se puede rehabilitar', true === $r );

	$f = $db->buscar_score( $score_ids[1] );
	comprobar( 'vuelve a contar y se limpia el motivo', 1 === (int) $f['valido'] && '' === $f['motivo_descalificacion'] );
	comprobar( 'y el ranking recupera su cuenta', $db->contar_ranking_rango( $hoy, $hoy, false ) === $antes_validos );

	$r = $db->marcar_score_valido( 999999999, false, 'x' );
	comprobar( 'rechaza un id que no existe', is_wp_error( $r ) );

	// --- Padrón con participación -------------------------------------------
	echo "\n=== Exportación del padrón ===\n";

	// Alguien del padrón que no jugó.
	$wpdb->insert( // phpcs:ignore
		$db->tabla_participantes,
		array(
			'telefono'       => '3900000099',
			'telefono_csv'   => '573900000099',
			'cedula'         => '9900099',
			'placa'          => 'ZZZ099',
			'fecha_concurso' => $hoy,
			'marca'          => 'TVS',
		)
	);
	$ids[] = (int) $wpdb->insert_id;

	$padron = $db->padron_con_participacion( $hoy, $hoy );
	$filas_mias = array_values(
		array_filter(
			$padron,
			static function ( $f ) {
				return 0 === strpos( (string) $f['placa'], 'ZZZ' );
			}
		)
	);

	comprobar( 'salen los cuatro de hoy, jugaran o no', 4 === count( $filas_mias ), count( $filas_mias ) . ' filas' );

	$jugaron    = array_filter( $filas_mias, static function ( $f ) { return ! empty( $f['score_id'] ); } );
	$no_jugaron = array_filter( $filas_mias, static function ( $f ) { return empty( $f['score_id'] ); } );

	comprobar( 'tres jugaron', 3 === count( $jugaron ), (string) count( $jugaron ) );
	comprobar( 'y uno no, que es la gracia del informe', 1 === count( $no_jugaron ) );

	$sin_jugar = array_values( $no_jugaron )[0];
	comprobar( 'al que no jugó no se le inventa distancia', null === $sin_jugar['distancia_m'] );

	$fechas_p = array_unique( wp_list_pluck( $padron, 'fecha_concurso' ) );
	comprobar( 'el filtro de fecha también aplica al padrón', array( $hoy ) === array_values( $fechas_p ), implode( ',', $fechas_p ) );

	// --- Validación de fechas de la URL --------------------------------------
	echo "\n=== Filtros manipulados ===\n";

	$fecha = new ReflectionMethod( $pantalla, 'fecha' );
	$fecha->setAccessible( true );

	$casos = array(
		array( '2026-09-30', '2026-09-30', 'acepta una fecha válida' ),
		array( '2026-02-31', '', 'descarta un 31 de febrero' ),
		array( "2026-09-30' OR 1=1", '', 'descarta una inyección' ),
		array( '30/09/2026', '', 'descarta el formato con barras' ),
		array( '', '', 'una fecha vacía se queda vacía' ),
	);

	foreach ( $casos as $caso ) {
		list( $entra, $espera, $que ) = $caso;
		$sale = $fecha->invoke( $pantalla, $entra );
		comprobar( $que, $espera === $sale, "'" . $sale . "'" );
	}

} finally {
	limpiar( $db );
	echo "\nFilas de prueba borradas.\n";
}

$marcador = cuenta( 'resumen' );

printf(
	"\n%s  %d comprobaciones, %d fallos\n",
	0 === $marcador['fallos'] ? 'TODO OK' : 'HAY FALLOS',
	$marcador['hechas'],
	$marcador['fallos']
);
