<?php
/**
 * Validación del resultado de una carrera.
 *
 * Aquí se decide el score del concurso. El navegador no envía metros: envía el
 * registro de lo que pulsó el jugador, y el servidor reejecuta la carrera con
 * el mismo seed que él mismo emitió. Lo que el participante vio en pantalla es
 * cosmético hasta que sale de aquí.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-simulacion.php';

/**
 * Reejecuta, comprueba y persiste.
 */
class NavidadTVS_Validador {

	/**
	 * Margen que se le da a la carrera por encima de su duración, en segundos.
	 *
	 * Entre que el servidor consume la sesión y le llega el resultado pasan los
	 * 90 s de carrera más la cuenta regresiva, más lo que tarde la red. En un
	 * celular con mala señal eso se estira, y el intento es único: cortar fino
	 * aquí significa dejar sin premio a quien jugó bien con mala conexión.
	 *
	 * Por abajo el margen es estrecho a propósito: una carrera no puede llegar
	 * antes de lo que dura, y si llega es que no se jugó.
	 */
	const MARGEN_MAXIMO_S = 120;
	const MARGEN_MINIMO_S = 5;

	/** @var NavidadTVS_Database */
	private $database;

	/**
	 * @param NavidadTVS_Database $database Acceso a datos.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Recibe el resultado de una carrera y lo registra.
	 *
	 * @param array $datos token, entradas, distancia, ip, user_agent.
	 * @return array|WP_Error
	 */
	public function registrar( array $datos ) {
		$token = isset( $datos['token'] ) ? (string) $datos['token'] : '';

		$sesion = $this->database->buscar_sesion_por_nonce( $token );

		if ( null === $sesion ) {
			return new WP_Error(
				'sesion_invalida',
				__( 'Tu sesión no es válida.', 'navidad-tvs' ),
				array( 'status' => 403 )
			);
		}

		/*
		 * La sesión tiene que estar consumida: eso significa que pasó por
		 * /carrera/iniciar y que de ahí salió el seed. Si sigue en 'emitida',
		 * alguien está mandando un resultado de una carrera que nunca arrancó.
		 */
		if ( 'consumida' !== $sesion['estado'] ) {
			return new WP_Error(
				'carrera_no_iniciada',
				__( 'Esa carrera no se inició desde aquí.', 'navidad-tvs' ),
				array( 'status' => 403 )
			);
		}

		$participante_id = (int) $sesion['participante_id'];

		if ( $this->database->tiene_score( $participante_id ) ) {
			return new WP_Error(
				'ya_participo',
				__( 'Este número ya participó. Cada persona tiene un solo intento.', 'navidad-tvs' ),
				array( 'status' => 409 )
			);
		}

		$participante = $this->database->buscar_participante( $participante_id );

		if ( null === $participante ) {
			return new WP_Error(
				'sesion_invalida',
				__( 'Tu sesión no es válida.', 'navidad-tvs' ),
				array( 'status' => 403 )
			);
		}

		// --- El registro de entradas -------------------------------------
		$entradas_b64 = isset( $datos['entradas'] ) ? (string) $datos['entradas'] : '';
		$entradas     = NavidadTVS_Sim_Entradas::decodificar( $entradas_b64, NAVIDAD_TVS_TOTAL_TICKS );

		if ( is_wp_error( $entradas ) ) {
			$entradas->add_data( array( 'status' => 400 ) );
			return $entradas;
		}

		// --- La reejecución ------------------------------------------------
		$estado = NavidadTVS_Sim_Simulacion::simular(
			(int) $sesion['seed'],
			$entradas,
			NAVIDAD_TVS_TOTAL_TICKS
		);

		$distancia = NavidadTVS_Sim_Simulacion::distancia_metros( $estado );

		// --- Segunda malla: plausibilidad -----------------------------------
		$duracion = $this->duracion_de( $sesion );
		$motivo   = $this->motivo_implausible( $distancia, $duracion );

		$id = $this->database->registrar_score(
			array(
				'participante_id'          => $participante_id,
				'sesion_id'                => (int) $sesion['id'],
				'nombre'                   => $sesion['nombre_digitado'],
				'cedula'                   => $participante['cedula'],
				'telefono'                 => $participante['telefono'],
				'ciudad_propietario'       => isset( $participante['ciudad_propietario'] ) ? $participante['ciudad_propietario'] : '',
				'departamento_propietario' => isset( $participante['departamento_propietario'] ) ? $participante['departamento_propietario'] : '',
				'fecha_concurso'           => $participante['fecha_concurso'],
				'distancia_m'              => $distancia,
				'distancia_base_m'         => NavidadTVS_Sim_Simulacion::distancia_base_metros( $estado ),
				'distancia_cliente_m'      => max( 0, (int) ( isset( $datos['distancia'] ) ? $datos['distancia'] : 0 ) ),
				'items_recogidos'          => $estado['items'],
				'impulsores'               => $estado['impulsores'],
				'caidas'                   => $estado['caidas'],
				'sobrecalentamientos'      => $estado['sobrecalentamientos'],
				'duracion_s'               => $duracion,
				'seed'                     => (int) $sesion['seed'],
				'inputs'                   => $entradas_b64,
				'valido'                   => '' === $motivo,
				'motivo_descalificacion'   => $motivo,
				'ip'                       => isset( $datos['ip'] ) ? (string) $datos['ip'] : '',
				'user_agent'               => isset( $datos['user_agent'] ) ? (string) $datos['user_agent'] : '',
			)
		);

		/*
		 * El insert falla por dos motivos muy distintos y no se pueden
		 * confundir. Uno es que ya hubiera una fila de este participante,
		 * porque participante_id es UNIQUE: pasa cuando llegan dos peticiones
		 * a la vez, y es justo lo que el índice está ahí para impedir. El otro
		 * es que algo esté mal en la base de datos.
		 *
		 * Decirle "ya participaste" a alguien cuando lo que hay es una columna
		 * que falta sería el peor mensaje posible en día de concurso: la
		 * persona se queda sin intento, reclama, y en el registro no aparece
		 * nada raro. Así que se vuelve a mirar y, si no hay score, se admite
		 * que el fallo es del servidor.
		 */
		if ( false === $id ) {
			if ( $this->database->tiene_score( $participante_id ) ) {
				return new WP_Error(
					'ya_participo',
					__( 'Este número ya participó. Cada persona tiene un solo intento.', 'navidad-tvs' ),
					array( 'status' => 409 )
				);
			}

			return new WP_Error(
				'no_se_pudo_guardar',
				__( 'No pudimos guardar tu resultado. Comunícate con el organizador antes de cerrar esta página.', 'navidad-tvs' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'distancia' => $distancia,
			'llaves'    => $estado['items'],
			'caidas'    => $estado['caidas'],
			'nombre'    => $sesion['nombre_digitado'],
			'valido'    => '' === $motivo,
		);
	}

	/**
	 * Segundos entre que arrancó la carrera y llegó el resultado.
	 *
	 * @param array $sesion Fila de la sesión.
	 * @return int
	 */
	private function duracion_de( array $sesion ) {
		if ( empty( $sesion['consumida_en'] ) ) {
			return 0;
		}

		$inicio = strtotime( $sesion['consumida_en'] . ' UTC' );

		if ( false === $inicio ) {
			return 0;
		}

		return max( 0, time() - $inicio );
	}

	/**
	 * Comprueba lo que la reejecución no puede comprobar sola.
	 *
	 * La reejecución ya garantiza que la distancia sale de unas entradas
	 * concretas, así que nadie puede inventarse metros. Lo que queda por mirar
	 * es el reloj: una carrera dura 90 segundos y si el resultado llega a los
	 * diez, el registro no se jugó sino que se fabricó.
	 *
	 * El tope de distancia es una red por si algún día un cambio en la física
	 * abre un agujero: con los números de hoy no se puede alcanzar.
	 *
	 * Devuelve cadena vacía si todo está en orden.
	 *
	 * @param int $distancia Metros que calculó el servidor.
	 * @param int $duracion  Segundos que tardó en llegar.
	 * @return string
	 */
	private function motivo_implausible( $distancia, $duracion ) {
		if ( $distancia > NAVIDAD_TVS_DISTANCIA_MAXIMA_M ) {
			return sprintf(
				/* translators: 1: metros obtenidos, 2: tope. */
				__( 'Distancia de %1$d m por encima del tope de %2$d m.', 'navidad-tvs' ),
				$distancia,
				NAVIDAD_TVS_DISTANCIA_MAXIMA_M
			);
		}

		$minimo = NAVIDAD_TVS_DURACION_SEGUNDOS - self::MARGEN_MINIMO_S;

		if ( $duracion < $minimo ) {
			return sprintf(
				/* translators: 1: segundos que tardó, 2: mínimo. */
				__( 'La carrera llegó en %1$d s y no puede durar menos de %2$d.', 'navidad-tvs' ),
				$duracion,
				$minimo
			);
		}

		$maximo = NAVIDAD_TVS_DURACION_SEGUNDOS + self::MARGEN_MAXIMO_S;

		if ( $duracion > $maximo ) {
			return sprintf(
				/* translators: 1: segundos que tardó, 2: máximo. */
				__( 'La carrera llegó en %1$d s, más de los %2$d admitidos.', 'navidad-tvs' ),
				$duracion,
				$maximo
			);
		}

		return '';
	}
}
