<?php
/**
 * Backoffice del concurso: ranking, exportación, auditoría y descalificación.
 *
 * Es la pantalla con la que se decide quién gana, así que todo lo que muestra
 * sale de la tabla de scores, que es lo que calculó el servidor. Nada de lo
 * que envió el navegador se usa para ordenar.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ranking y auditoría.
 */
class NavidadTVS_Ranking {

	/**
	 * Acceso a la base.
	 *
	 * @var NavidadTVS_Database
	 */
	private $database;

	/**
	 * Configuración del concurso.
	 *
	 * @var NavidadTVS_Settings
	 */
	private $settings;

	/**
	 * Filas por página en la tabla.
	 */
	private const POR_PAGINA = 50;

	/**
	 * Constructor.
	 *
	 * @param NavidadTVS_Database $database Acceso a datos.
	 * @param NavidadTVS_Settings $settings Configuración.
	 */
	public function __construct( $database, $settings ) {
		$this->database = $database;
		$this->settings = $settings;
	}

	/**
	 * Engancha las acciones que escriben o descargan.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_action( 'admin_post_navidad_tvs_exportar_ranking', array( $this, 'exportar_ranking' ) );
		add_action( 'admin_post_navidad_tvs_exportar_padron', array( $this, 'exportar_padron' ) );
		add_action( 'admin_post_navidad_tvs_descalificar', array( $this, 'descalificar' ) );
		add_action( 'admin_post_navidad_tvs_ganadores', array( $this, 'guardar_ganadores' ) );
		add_action( 'admin_post_navidad_tvs_exportar_ganadores', array( $this, 'exportar_ganadores' ) );
		add_action( 'admin_post_navidad_tvs_informe_general', array( $this, 'exportar_informe_general' ) );
		add_action( 'admin_post_navidad_tvs_informe_ejecutivo', array( $this, 'exportar_informe_ejecutivo' ) );
	}

	/**
	 * Pinta el ranking o, si se pide un intento concreto, su auditoría.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}

		/*
		 * Los filtros son de solo lectura, así que van por GET y sin nonce; lo
		 * que protege esta pantalla es la comprobación de capacidad. El nonce
		 * está en las acciones que escriben o descargan.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recurring
		$desde     = $this->fecha( isset( $_GET['desde'] ) ? wp_unslash( $_GET['desde'] ) : '' );
		$hasta     = $this->fecha( isset( $_GET['hasta'] ) ? wp_unslash( $_GET['hasta'] ) : '' );
		$todos     = ! empty( $_GET['incluir_invalidos'] );
		$pagina    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$ver       = isset( $_GET['ver'] ) ? (int) $_GET['ver'] : 0;
		$aviso     = isset( $_GET['aviso'] ) ? sanitize_text_field( wp_unslash( $_GET['aviso'] ) ) : '';
		$detalle   = isset( $_GET['detalle'] ) ? sanitize_text_field( wp_unslash( $_GET['detalle'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recurring

		$database = $this->database;
		$settings = $this->settings;

		if ( $ver > 0 ) {
			$score     = $database->buscar_score( $ver );
			$auditoria = $score ? $this->auditar( $score ) : null;

			include NAVIDAD_TVS_PATH . 'includes/admin/templates/replay.php';
			return;
		}

		$total      = $database->contar_ranking_rango( $desde, $hasta, $todos );
		$paginas    = max( 1, (int) ceil( $total / self::POR_PAGINA ) );
		$pagina     = min( $pagina, $paginas );
		$filas      = $database->ranking_rango( $desde, $hasta, $pagina, self::POR_PAGINA, $todos );
		$por_pagina = self::POR_PAGINA;

		/*
		 * Corte de premios. El reglamento premia a los cuatro primeros y, si
		 * hay empate en el cuarto puesto, a todos los empatados; por eso el
		 * corte es una DISTANCIA y no una posición. Solo tiene sentido cuando
		 * se mira una jornada concreta: un "top 4" de varios días mezclados no
		 * premia a nadie.
		 */
		$una_jornada = '' !== $desde && $desde === $hasta;
		$corte       = null;

		if ( $una_jornada && ! $todos ) {
			$corte = $this->distancia_de_corte( $desde );
		}

		include NAVIDAD_TVS_PATH . 'includes/admin/templates/ranking.php';
	}

	/**
	 * Distancia del último puesto premiado de una jornada.
	 *
	 * @param string $fecha Jornada Y-m-d.
	 * @return int|null Distancia de corte, o null si no hay suficientes.
	 */
	private function distancia_de_corte( $fecha ) {
		$premios = max( 1, (int) $this->settings->get( 'premios_por_jornada', 4 ) );
		$top     = $this->database->ranking_rango( $fecha, $fecha, 1, $premios, false );

		if ( count( $top ) < $premios ) {
			return null;
		}

		$ultimo = end( $top );

		return (int) $ultimo['distancia_m'];
	}

	/**
	 * Reejecuta una carrera y compara con lo guardado.
	 *
	 * Esta es la vista de replay: no un vídeo, sino la prueba de que el
	 * resultado es reproducible. Se vuelve a correr la simulación con el mismo
	 * seed y el mismo log de entradas, y se comprueba que da exactamente lo
	 * mismo que quedó registrado.
	 *
	 * Sirve para dos cosas. Ante un reclamo, demuestra que la distancia no se
	 * inventó. Y si el resultado NO se reproduce, es que el bundle cambió
	 * entre la carrera y hoy, o que alguien tocó la fila: cualquiera de las
	 * dos es un problema que hay que ver antes de premiar.
	 *
	 * @param array $score Fila de la tabla de scores.
	 * @return array Resumen de la auditoría.
	 */
	private function auditar( array $score ) {
		require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-constantes.php';
		require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-prng.php';
		require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-pista.php';
		require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-entradas.php';
		require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-simulacion.php';

		$ticks    = NAVIDAD_TVS_TOTAL_TICKS;
		$entradas = NavidadTVS_Sim_Entradas::decodificar( $score['inputs'], $ticks );

		if ( is_wp_error( $entradas ) ) {
			return array(
				'reproducible' => false,
				'error'        => $entradas->get_error_message(),
			);
		}

		$inicio = microtime( true );
		$estado = NavidadTVS_Sim_Simulacion::simular( (int) $score['seed'], $entradas, $ticks );
		$ms     = ( microtime( true ) - $inicio ) * 1000;

		$recalculado = array(
			'distancia_m'         => NavidadTVS_Sim_Simulacion::distancia_metros( $estado ),
			'distancia_base_m'    => NavidadTVS_Sim_Simulacion::distancia_base_metros( $estado ),
			'items_recogidos'     => (int) $estado['items'],
			'impulsores'          => (int) $estado['impulsores'],
			'caidas'              => (int) $estado['caidas'],
			'sobrecalentamientos' => (int) $estado['sobrecalentamientos'],
		);

		$diferencias = array();

		foreach ( $recalculado as $campo => $valor ) {
			if ( (int) $score[ $campo ] !== (int) $valor ) {
				$diferencias[ $campo ] = array(
					'guardado'    => (int) $score[ $campo ],
					'recalculado' => (int) $valor,
				);
			}
		}

		/*
		 * Lo que dijo el navegador se guarda solo para auditar y no decide
		 * nada. Que difiera no es motivo de descalificación por sí solo: puede
		 * ser un bundle viejo en caché. Pero una diferencia grande y repetida
		 * sí merece una mirada.
		 */
		$desfase_cliente = (int) $score['distancia_cliente_m'] - (int) $score['distancia_m'];

		return array(
			'reproducible'    => empty( $diferencias ),
			'recalculado'     => $recalculado,
			'diferencias'     => $diferencias,
			'desfase_cliente' => $desfase_cliente,
			'ms'              => $ms,
			'ticks'           => $ticks,
		);
	}

	/**
	 * Descalifica o rehabilita un resultado.
	 *
	 * @return void
	 */
	public function descalificar() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para descalificar.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_descalificar' );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$valido = ! empty( $_POST['rehabilitar'] );
		$motivo = isset( $_POST['motivo'] ) ? sanitize_text_field( wp_unslash( $_POST['motivo'] ) ) : '';

		$resultado = $this->database->marcar_score_valido( $id, $valido, $motivo );

		$args = array(
			'page' => NavidadTVS_Admin::SLUG . '-ranking',
			'ver'  => $id,
		);

		if ( is_wp_error( $resultado ) ) {
			$args['aviso']   = 'error';
			$args['detalle'] = $resultado->get_error_message();
		} else {
			$args['aviso'] = $valido ? 'rehabilitado' : 'descalificado';
		}

		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Guarda de una vez los ganadores marcados en la tabla.
	 *
	 * Trabaja solo sobre las filas que estaban EN PANTALLA, que llegan en un
	 * campo oculto. Es la diferencia entre "los que marqué" y "todos los del
	 * concurso": sin esa lista, desmarcar una casilla en la página 2 borraría
	 * los ganadores de la página 1, porque no venían en el POST.
	 *
	 * @return void
	 */
	public function guardar_ganadores() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para marcar ganadores.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_ganadores' );

		$mostrados = isset( $_POST['mostrados'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['mostrados'] ) ) : array();
		$marcados  = isset( $_POST['ganador'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ganador'] ) ) : array();
		$notas     = isset( $_POST['nota'] ) ? (array) wp_unslash( $_POST['nota'] ) : array();

		$puestos  = 0;
		$quitados = 0;
		$errores  = array();

		foreach ( $mostrados as $id ) {
			$gana = in_array( $id, $marcados, true );
			$nota = isset( $notas[ $id ] ) ? sanitize_text_field( $notas[ $id ] ) : '';

			$score = $this->database->buscar_score( $id );

			if ( ! $score ) {
				continue;
			}

			$era = ! empty( $score['ganador'] );

			// Solo se escribe lo que cambia. Así ganador_en sigue diciendo
			// cuándo se decidió de verdad y no la última vez que alguien
			// pulsó guardar.
			if ( $era === $gana && ( ! $gana || $nota === $score['ganador_nota'] ) ) {
				continue;
			}

			$resultado = $this->database->marcar_ganador( $id, $gana, $nota );

			if ( is_wp_error( $resultado ) ) {
				$errores[] = $resultado->get_error_message();
				continue;
			}

			if ( $gana && ! $era ) {
				$puestos++;
			} elseif ( ! $gana && $era ) {
				$quitados++;
			}
		}

		$args = array(
			'page'  => NavidadTVS_Admin::SLUG . '-ranking',
			'aviso' => empty( $errores ) ? 'ganadores' : 'error',
		);

		if ( ! empty( $errores ) ) {
			$args['detalle'] = implode( ' ', array_unique( $errores ) );
		} else {
			$args['detalle'] = sprintf(
				/* translators: 1: ganadores marcados, 2: ganadores quitados */
				__( '%1$d marcados, %2$d quitados.', 'navidad-tvs' ),
				$puestos,
				$quitados
			);
		}

		foreach ( array( 'desde', 'hasta', 'paged' ) as $clave ) {
			if ( ! empty( $_POST[ $clave ] ) ) {
				$args[ $clave ] = sanitize_text_field( wp_unslash( $_POST[ $clave ] ) );
			}
		}

		if ( ! empty( $_POST['incluir_invalidos'] ) ) {
			$args['incluir_invalidos'] = '1';
		}

		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Descarga la lista de ganadores en CSV.
	 *
	 * @return void
	 */
	public function exportar_ganadores() {
		$this->comprobar_descarga( 'navidad_tvs_exportar_ganadores' );

		// phpcs:disable WordPress.Security.NonceVerification.Recurring
		$desde = $this->fecha( isset( $_GET['desde'] ) ? wp_unslash( $_GET['desde'] ) : '' );
		$hasta = $this->fecha( isset( $_GET['hasta'] ) ? wp_unslash( $_GET['hasta'] ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recurring

		$filas = $this->database->ganadores( $desde, $hasta );

		$this->enviar_csv(
			'ganadores',
			$desde,
			$hasta,
			array(
				'jornada',
				'nombre',
				'cedula',
				'telefono',
				'ciudad',
				'departamento',
				'distancia_m',
				'llaves',
				'caidas',
				'premio_o_puesto',
				'marcado_en',
				'jugo_en',
				'score_id',
			),
			array_map(
				static function ( $f ) {
					return array(
						$f['fecha_concurso'],
						$f['nombre'],
						$f['cedula'],
						$f['telefono'],
						$f['ciudad_propietario'],
						$f['departamento_propietario'],
						$f['distancia_m'],
						$f['items_recogidos'],
						$f['caidas'],
						$f['ganador_nota'],
						$f['ganador_en'],
						$f['creado_en'],
						$f['id'],
					);
				},
				$filas
			)
		);
	}

	/**
	 * Descarga el ranking filtrado en CSV.
	 *
	 * @return void
	 */
	public function exportar_ranking() {
		$this->comprobar_descarga( 'navidad_tvs_exportar_ranking' );

		// phpcs:disable WordPress.Security.NonceVerification.Recurring
		$desde = $this->fecha( isset( $_GET['desde'] ) ? wp_unslash( $_GET['desde'] ) : '' );
		$hasta = $this->fecha( isset( $_GET['hasta'] ) ? wp_unslash( $_GET['hasta'] ) : '' );
		$todos = ! empty( $_GET['incluir_invalidos'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recurring

		$total = $this->database->contar_ranking_rango( $desde, $hasta, $todos );
		$filas = $this->database->ranking_rango( $desde, $hasta, 1, max( 1, $total ), $todos );

		$cabeceras = array(
			'posicion',
			'id',
			'nombre',
			'cedula',
			'telefono',
			'ciudad',
			'departamento',
			'distancia_m',
			'llaves',
			'impulsores',
			'caidas',
			'sobrecalentamientos',
			'fecha_concurso',
			'valido',
			'motivo_descalificacion',
			'registrado_en',
		);

		$posicion = 0;

		$this->enviar_csv(
			'ranking',
			$desde,
			$hasta,
			$cabeceras,
			array_map(
				static function ( $f ) use ( &$posicion ) {
					$posicion++;

					return array(
						$posicion,
						$f['id'],
						$f['nombre'],
						$f['cedula'],
						$f['telefono'],
						$f['ciudad_propietario'],
						$f['departamento_propietario'],
						$f['distancia_m'],
						$f['items_recogidos'],
						$f['impulsores'],
						$f['caidas'],
						$f['sobrecalentamientos'],
						$f['fecha_concurso'],
						$f['valido'] ? 'si' : 'no',
						$f['motivo_descalificacion'],
						$f['creado_en'],
					);
				},
				$filas
			)
		);
	}

	/**
	 * Descarga el padrón completo marcando quién participó.
	 *
	 * @return void
	 */
	public function exportar_padron() {
		$this->comprobar_descarga( 'navidad_tvs_exportar_padron' );

		// phpcs:disable WordPress.Security.NonceVerification.Recurring
		$desde = $this->fecha( isset( $_GET['desde'] ) ? wp_unslash( $_GET['desde'] ) : '' );
		$hasta = $this->fecha( isset( $_GET['hasta'] ) ? wp_unslash( $_GET['hasta'] ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recurring

		$filas = $this->database->padron_con_participacion( $desde, $hasta );

		$cabeceras = array(
			'id',
			'telefono',
			'cedula',
			'placa',
			'fecha_concurso',
			'ciudad',
			'departamento',
			'establecimiento',
			'estado_padron',
			'participo',
			'nombre_digitado',
			'distancia_m',
			'llaves',
			'caidas',
			'sobrecalentamientos',
			'resultado_valido',
			'motivo_descalificacion',
			'jugo_en',
		);

		$this->enviar_csv(
			'padron-participacion',
			$desde,
			$hasta,
			$cabeceras,
			array_map(
				static function ( $f ) {
					$jugo = ! empty( $f['score_id'] );

					return array(
						$f['id'],
						$f['telefono'],
						$f['cedula'],
						$f['placa'],
						$f['fecha_concurso'],
						$f['ciudad_propietario'],
						$f['departamento_propietario'],
						$f['razon_social_establecimiento'],
						$f['estado'],
						$jugo ? 'si' : 'no',
						$jugo ? $f['nombre'] : '',
						$jugo ? $f['distancia_m'] : '',
						$jugo ? $f['items_recogidos'] : '',
						$jugo ? $f['caidas'] : '',
						$jugo ? $f['sobrecalentamientos'] : '',
						$jugo ? ( $f['valido'] ? 'si' : 'no' ) : '',
						$jugo ? $f['motivo_descalificacion'] : '',
						$jugo ? $f['creado_en'] : '',
					);
				},
				$filas
			)
		);
	}

	/**
	 * Informe general: una fila por participante del padrón.
	 *
	 * Distinto del padrón con participación, que saca una fila por carrera.
	 * Aquí la unidad es la PERSONA: cuántas veces jugó en toda la campaña, su
	 * mejor marca y si ganó. Es el informe que responde "¿qué hizo fulano?" de
	 * un vistazo, sin tener que juntar filas a mano en Excel.
	 *
	 * @return void
	 */
	public function exportar_informe_general() {
		$this->comprobar_descarga( 'navidad_tvs_informe_general' );

		$filas = $this->database->informe_por_participante();

		$this->enviar_csv(
			'informe-general',
			'',
			'',
			array(
				'id',
				'nombre',
				'cedula',
				'telefono',
				'placa',
				'ciudad',
				'departamento',
				'establecimiento',
				'jornada_asignada',
				'estado_padron',
				'intentos',
				'intentos_validos',
				'distancia_maxima_m',
				'fecha_mejor_intento',
				'primera_participacion',
				'ultima_participacion',
				'llaves_mejor_intento',
				'caidas_mejor_intento',
				'gano',
				'premio',
				'jornadas_en_que_jugo',
			),
			array_map(
				static function ( $f ) {
					return array(
						$f['id'],
						$f['nombre'],
						$f['cedula'],
						$f['telefono'],
						$f['placa'],
						$f['ciudad_propietario'],
						$f['departamento_propietario'],
						$f['razon_social_establecimiento'],
						$f['fecha_concurso'],
						$f['estado'],
						(int) $f['intentos'],
						(int) $f['intentos_validos'],
						null === $f['distancia_maxima'] ? '' : (int) $f['distancia_maxima'],
						(string) $f['fecha_mejor'],
						(string) $f['primera'],
						(string) $f['ultima'],
						null === $f['llaves_mejor'] ? '' : (int) $f['llaves_mejor'],
						null === $f['caidas_mejor'] ? '' : (int) $f['caidas_mejor'],
						$f['gano'] ? 'si' : 'no',
						(string) $f['premio'],
						(string) $f['jornadas'],
					);
				},
				$filas
			)
		);
	}

	/**
	 * Informe ejecutivo: los totales y el desglose por día.
	 *
	 * Pensado para abrirlo en Excel y graficarlo, así que va en formato largo
	 * —una fila por dato— y no en un cuadro con títulos de adorno. Un cuadro
	 * se lee bonito y no se grafica; una tabla de bloque, concepto, día y
	 * valor se convierte en gráfico seleccionando dos columnas.
	 *
	 * @return void
	 */
	public function exportar_informe_ejecutivo() {
		$this->comprobar_descarga( 'navidad_tvs_informe_ejecutivo' );

		$r = $this->database->resumen_ejecutivo();

		$filas = array();

		$totales = array(
			__( 'Inscritos en el padrón', 'navidad-tvs' )              => $r['inscritos'],
			__( 'Jornadas con padrón cargado', 'navidad-tvs' )         => $r['jornadas_padron'],
			__( 'Personas que jugaron alguna vez', 'navidad-tvs' )     => $r['personas_que_jugaron'],
			__( 'Personas que nunca jugaron', 'navidad-tvs' )          => $r['inscritos'] - $r['personas_que_jugaron'],
			__( 'Participaciones totales', 'navidad-tvs' )             => $r['participaciones'],
			__( 'Participaciones válidas', 'navidad-tvs' )             => $r['participaciones_validas'],
			__( 'Participaciones descalificadas', 'navidad-tvs' )      => $r['participaciones'] - $r['participaciones_validas'],
			__( 'Ganadores marcados', 'navidad-tvs' )                  => $r['ganadores'],
			__( 'Revanchas generales abiertas', 'navidad-tvs' )        => $r['revanchas_generales'],
			__( 'Revanchas individuales concedidas', 'navidad-tvs' )   => $r['revanchas_individuales'],
			__( 'Distancia máxima de la campaña (m)', 'navidad-tvs' )  => $r['distancia_maxima'],
			__( 'Distancia promedio (m)', 'navidad-tvs' )              => $r['distancia_promedio'],
		);

		foreach ( $totales as $concepto => $valor ) {
			$filas[] = array( 'Total', $concepto, '', $valor );
		}

		/*
		 * El porcentaje de conversión es el número que primero va a mirar
		 * quien reciba esto: de cada cien compradores habilitados, cuántos
		 * llegaron a jugar.
		 */
		if ( $r['inscritos'] > 0 ) {
			$filas[] = array(
				'Total',
				__( 'Porcentaje que jugó', 'navidad-tvs' ),
				'',
				round( ( $r['personas_que_jugaron'] * 100 ) / $r['inscritos'], 2 ),
			);
		}

		foreach ( $r['inscritos_por_dia'] as $dia => $cuantos ) {
			$filas[] = array( 'Inscritos por día', __( 'Inscritos', 'navidad-tvs' ), $dia, $cuantos );
		}

		foreach ( $r['por_dia'] as $dia => $datos ) {
			$filas[] = array( 'Participaciones por día', __( 'Participaciones', 'navidad-tvs' ), $dia, $datos['participaciones'] );
			$filas[] = array( 'Participaciones por día', __( 'Válidas', 'navidad-tvs' ), $dia, $datos['validas'] );
			$filas[] = array( 'Participaciones por día', __( 'Ganadores', 'navidad-tvs' ), $dia, $datos['ganadores'] );
			$filas[] = array( 'Participaciones por día', __( 'Distancia máxima (m)', 'navidad-tvs' ), $dia, $datos['maxima'] );
			$filas[] = array( 'Participaciones por día', __( 'Distancia promedio (m)', 'navidad-tvs' ), $dia, $datos['promedio'] );
		}

		foreach ( $r['por_departamento'] as $depto => $cuantos ) {
			$filas[] = array( 'Participaciones por departamento', __( 'Participaciones', 'navidad-tvs' ), $depto, $cuantos );
		}

		$this->enviar_csv(
			'informe-ejecutivo',
			'',
			'',
			array( 'bloque', 'concepto', 'dimension', 'valor' ),
			$filas
		);
	}

	/**
	 * Permisos y nonce de una descarga.
	 *
	 * @param string $accion Nombre del nonce.
	 * @return void
	 */
	private function comprobar_descarga( $accion ) {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para exportar.', 'navidad-tvs' ) );
		}

		check_admin_referer( $accion );
	}

	/**
	 * Manda un CSV al navegador y termina.
	 *
	 * Separador punto y coma y BOM UTF-8, igual que el archivo que entra: es
	 * lo que hace que Excel en español abra el archivo con las columnas
	 * separadas y las tildes correctas sin que nadie tenga que importarlo a
	 * mano.
	 *
	 * @param string $nombre    Prefijo del archivo.
	 * @param string $desde     Filtro aplicado, para el nombre.
	 * @param string $hasta     Filtro aplicado, para el nombre.
	 * @param array  $cabeceras Fila de cabeceras.
	 * @param array  $filas     Filas de datos.
	 * @return void
	 */
	private function enviar_csv( $nombre, $desde, $hasta, array $cabeceras, array $filas ) {
		$rango = '';

		if ( '' !== $desde || '' !== $hasta ) {
			$rango = '-' . ( '' !== $desde ? $desde : 'inicio' ) . '_' . ( '' !== $hasta ? $hasta : 'hoy' );
		}

		$archivo = sprintf( 'navidad-tvs-%s%s.csv', $nombre, $rango );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $archivo . '"' );

		$salida = fopen( 'php://output', 'w' );

		// BOM para que Excel reconozca UTF-8.
		fwrite( $salida, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		fputcsv( $salida, $cabeceras, ';' );

		foreach ( $filas as $fila ) {
			fputcsv( $salida, $fila, ';' );
		}

		fclose( $salida ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Valida una fecha Y-m-d.
	 *
	 * Devuelve cadena vacía si no lo es, que es lo que los métodos de la base
	 * interpretan como "sin filtro". Así un parámetro manipulado en la URL no
	 * rompe la consulta: como mucho, no filtra.
	 *
	 * @param string $valor Texto recibido.
	 * @return string
	 */
	private function fecha( $valor ) {
		$valor = sanitize_text_field( (string) $valor );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valor ) ) {
			return '';
		}

		list( $anio, $mes, $dia ) = array_map( 'intval', explode( '-', $valor ) );

		return checkdate( $mes, $dia, $anio ) ? $valor : '';
	}
}
