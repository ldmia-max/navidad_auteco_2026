<?php
/**
 * API REST del concurso.
 *
 * Todo vive bajo /wp-json/concurso/v1/. Nunca se usa admin-ajax.php.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/class-acceso.php';

/**
 * Rutas REST públicas.
 */
class NavidadTVS_Rest {

	/** @var NavidadTVS_Acceso */
	private $acceso;

	/**
	 * @param NavidadTVS_Acceso $acceso Lógica de acceso.
	 */
	public function __construct( $acceso ) {
		$this->acceso = $acceso;
	}

	/**
	 * Cablea los hooks.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_action( 'rest_api_init', array( $this, 'registrar_rutas' ) );
	}

	/**
	 * Registra las rutas.
	 *
	 * @return void
	 */
	public function registrar_rutas() {
		register_rest_route(
			NAVIDAD_TVS_REST_NS,
			'/acceso',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'acceso' ),
				// Ruta pública: el participante no tiene cuenta de WordPress.
				// El control real es la ventana horaria, el padrón, el límite
				// de intentos y Turnstile.
				'permission_callback' => '__return_true',
				'args'                => array(
					'telefono'  => array(
						'required' => true,
						'type'     => 'string',
					),
					'nombre'    => array(
						'required' => true,
						'type'     => 'string',
					),
					'acepta'    => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'turnstile' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			NAVIDAD_TVS_REST_NS,
			'/carrera/iniciar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'iniciar_carrera' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			NAVIDAD_TVS_REST_NS,
			'/estado',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'estado' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /concurso/v1/acceso
	 *
	 * @param WP_REST_Request $peticion Petición.
	 * @return WP_REST_Response|WP_Error
	 */
	public function acceso( WP_REST_Request $peticion ) {
		$resultado = $this->acceso->validar(
			array(
				'telefono'  => (string) $peticion->get_param( 'telefono' ),
				'nombre'    => (string) $peticion->get_param( 'nombre' ),
				'acepta'    => (bool) $peticion->get_param( 'acepta' ),
				'turnstile' => (string) $peticion->get_param( 'turnstile' ),
			)
		);

		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}

		return new WP_REST_Response( $resultado, 200 );
	}

	/**
	 * POST /concurso/v1/carrera/iniciar
	 *
	 * Consume el intento y entrega el seed de la pista.
	 *
	 * @param WP_REST_Request $peticion Petición.
	 * @return WP_REST_Response|WP_Error
	 */
	public function iniciar_carrera( WP_REST_Request $peticion ) {
		$resultado = $this->acceso->iniciar_carrera( (string) $peticion->get_param( 'token' ) );

		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}

		return new WP_REST_Response( $resultado, 200 );
	}

	/**
	 * GET /concurso/v1/estado
	 *
	 * Permite que la página sepa si la ventana sigue abierta sin recargar.
	 *
	 * @return WP_REST_Response
	 */
	public function estado() {
		$estado = $this->acceso->estado_ventana();

		return new WP_REST_Response(
			array(
				'abierta' => (bool) $estado['abierta'],
				'mensaje' => $estado['mensaje'],
				'horario' => $estado['horario'],
				'ahora'   => NavidadTVS_Plugin::ahora()->format( 'Y-m-d H:i:s' ),
			),
			200
		);
	}
}
