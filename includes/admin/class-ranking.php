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
