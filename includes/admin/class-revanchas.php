<?php
/**
 * Revanchas: segundas oportunidades, generales o de una sola persona.
 *
 * Hay dos clases y responden a necesidades distintas.
 *
 * La GENERAL abre una jornada extra para todo el que no haya ganado todavía.
 * Es la segunda oportunidad de campaña: quien no pudo jugar su día, o jugó y
 * no ganó, vuelve a tener un intento por otro premio.
 *
 * La INDIVIDUAL devuelve el intento a una persona concreta. Existe porque en
 * una campaña anterior hubo un derecho de petición, y hace falta poder
 * atender un reclamo sin abrirle la puerta a todo el mundo. Por eso el motivo
 * es obligatorio: esa decisión alguien va a tener que explicarla.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de revanchas.
 */
class NavidadTVS_Revanchas {

	/**
	 * Acceso a la base.
	 *
	 * @var NavidadTVS_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param NavidadTVS_Database $database Acceso a datos.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Engancha las acciones que escriben.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_action( 'admin_post_navidad_tvs_revancha_general', array( $this, 'crear_general' ) );
		add_action( 'admin_post_navidad_tvs_revancha_individual', array( $this, 'crear_individual' ) );
		add_action( 'admin_post_navidad_tvs_borrar_revancha', array( $this, 'borrar' ) );
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}

		$database = $this->database;

		// phpcs:disable WordPress.Security.NonceVerification.Recurring
		$aviso   = isset( $_GET['aviso'] ) ? sanitize_text_field( wp_unslash( $_GET['aviso'] ) ) : '';
		$detalle = isset( $_GET['detalle'] ) ? sanitize_text_field( wp_unslash( $_GET['detalle'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recurring

		$revanchas = $database->listar_revanchas();
		$hoy       = NavidadTVS_Plugin::hoy();

		include NAVIDAD_TVS_PATH . 'includes/admin/templates/revanchas.php';
	}

	/**
	 * Abre una jornada de revancha para todos.
	 *
	 * @return void
	 */
	public function crear_general() {
		$this->comprobar( 'navidad_tvs_revancha_general' );

		$fecha  = $this->fecha( isset( $_POST['fecha'] ) ? wp_unslash( $_POST['fecha'] ) : '' );
		$motivo = isset( $_POST['motivo'] ) ? sanitize_text_field( wp_unslash( $_POST['motivo'] ) ) : '';

		if ( '' === $fecha ) {
			$this->volver( 'error', __( 'La fecha va en formato AAAA-MM-DD y tiene que existir en el calendario.', 'navidad-tvs' ) );
		}

		$resultado = $this->database->crear_revancha( 0, $fecha, $motivo, get_current_user_id() );

		if ( is_wp_error( $resultado ) ) {
			$this->volver( 'error', $resultado->get_error_message() );
		}

		$this->volver(
			'creada',
			sprintf(
				/* translators: %s: fecha de la jornada */
				__( 'El %s vuelve a jugar todo el que no haya ganado.', 'navidad-tvs' ),
				$fecha
			)
		);
	}

	/**
	 * Devuelve el intento a una sola persona.
	 *
	 * @return void
	 */
	public function crear_individual() {
		$this->comprobar( 'navidad_tvs_revancha_individual' );

		$busqueda = isset( $_POST['busqueda'] ) ? sanitize_text_field( wp_unslash( $_POST['busqueda'] ) ) : '';
		$fecha    = $this->fecha( isset( $_POST['fecha'] ) ? wp_unslash( $_POST['fecha'] ) : '' );
		$motivo   = isset( $_POST['motivo'] ) ? sanitize_text_field( wp_unslash( $_POST['motivo'] ) ) : '';

		if ( '' === $fecha ) {
			$this->volver( 'error', __( 'La fecha va en formato AAAA-MM-DD y tiene que existir en el calendario.', 'navidad-tvs' ) );
		}

		/*
		 * Se busca por cédula o celular, que es lo que trae quien reclama. La
		 * búsqueda tiene que devolver UNA sola persona: conceder la revancha a
		 * la primera de varias coincidencias sería devolverle el intento a
		 * quien no lo pidió.
		 */
		$encontrados = $this->database->buscar_participantes( $busqueda, 5 );

		if ( empty( $encontrados ) ) {
			$this->volver( 'error', __( 'No hay nadie en el padrón con esa cédula o ese celular.', 'navidad-tvs' ) );
		}

		if ( count( $encontrados ) > 1 ) {
			$this->volver( 'error', __( 'Ese dato coincide con más de una persona. Búscala en Participantes y usa su cédula exacta.', 'navidad-tvs' ) );
		}

		$resultado = $this->database->crear_revancha( (int) $encontrados[0]['id'], $fecha, $motivo, get_current_user_id() );

		if ( is_wp_error( $resultado ) ) {
			$this->volver( 'error', $resultado->get_error_message() );
		}

		$this->volver(
			'creada',
			sprintf(
				/* translators: 1: teléfono, 2: fecha */
				__( '%1$s puede volver a jugar el %2$s.', 'navidad-tvs' ),
				$encontrados[0]['telefono'],
				$fecha
			)
		);
	}

	/**
	 * Retira una revancha.
	 *
	 * @return void
	 */
	public function borrar() {
		$this->comprobar( 'navidad_tvs_borrar_revancha' );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$this->database->borrar_revancha( $id );

		$this->volver( 'borrada', '' );
	}

	/**
	 * Permisos y nonce.
	 *
	 * @param string $accion Nombre del nonce.
	 * @return void
	 */
	private function comprobar( $accion ) {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para conceder revanchas.', 'navidad-tvs' ) );
		}

		check_admin_referer( $accion );
	}

	/**
	 * Valida una fecha Y-m-d.
	 *
	 * @param string $valor Texto recibido.
	 * @return string Cadena vacía si no es una fecha válida.
	 */
	private function fecha( $valor ) {
		$valor = sanitize_text_field( (string) $valor );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valor ) ) {
			return '';
		}

		list( $anio, $mes, $dia ) = array_map( 'intval', explode( '-', $valor ) );

		return checkdate( $mes, $dia, $anio ) ? $valor : '';
	}

	/**
	 * Vuelve a la pantalla con el aviso puesto.
	 *
	 * @param string $aviso   'creada', 'borrada' o 'error'.
	 * @param string $detalle Mensaje.
	 * @return void
	 */
	private function volver( $aviso, $detalle ) {
		$args = array(
			'page'  => NavidadTVS_Admin::SLUG . '-revanchas',
			'aviso' => $aviso,
		);

		if ( '' !== $detalle ) {
			$args['detalle'] = $detalle;
		}

		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
