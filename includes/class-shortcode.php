<?php
/**
 * Shortcode de la página de acceso al juego.
 *
 * Uso: [concurso_tvs]
 *
 * Esta clase solo renderiza y encola. Toda la decisión de quién puede jugar
 * vive en NavidadTVS_Acceso, del lado del servidor.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render del frontend.
 */
class NavidadTVS_Shortcode {

	const TAG = 'concurso_tvs';

	/** @var NavidadTVS_Acceso */
	private $acceso;

	/** @var NavidadTVS_Settings */
	private $settings;

	/** @var bool Si la página actual contiene el shortcode. */
	private $activo = false;

	/**
	 * @param NavidadTVS_Acceso   $acceso   Lógica de acceso.
	 * @param NavidadTVS_Settings $settings Configuración.
	 */
	public function __construct( $acceso, $settings ) {
		$this->acceso   = $acceso;
		$this->settings = $settings;
	}

	/**
	 * Cablea los hooks.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_shortcode( self::TAG, array( $this, 'render' ) );

		/*
		 * Los assets se registran en 'init', no en 'wp_enqueue_scripts'.
		 *
		 * Con un tema de bloques, WordPress renderiza la plantilla —y con ella
		 * el contenido y sus shortcodes— ANTES de disparar
		 * 'wp_enqueue_scripts'. Registrando allí, cuando el shortcode corría el
		 * handle todavía no existía: wp_enqueue_script() lo dejaba en cola y se
		 * acababa imprimiendo, pero wp_localize_script() devolvía false y la
		 * página quedaba sin el objeto NAVIDAD_TVS. El fetch salía entonces
		 * contra undefined, recibía el HTML del 404 y el formulario mostraba
		 * "Unexpected token '<'".
		 *
		 * En 'init' el registro siempre ocurre antes de cualquier render.
		 */
		add_action( 'init', array( $this, 'registrar_assets' ) );
	}

	/**
	 * Registra CSS y JS sin encolarlos.
	 *
	 * Se encolan solo si la página usa el shortcode, para no cargar la hoja
	 * arcade en el resto del sitio, que va con la identidad de Auteco.
	 *
	 * @return void
	 */
	public function registrar_assets() {
		wp_register_style(
			'navidad-tvs-arcade',
			NAVIDAD_TVS_URL . 'assets/css/arcade.css',
			array(),
			NAVIDAD_TVS_VERSION
		);

		wp_register_script(
			'navidad-tvs-acceso',
			NAVIDAD_TVS_URL . 'assets/js/acceso.js',
			array(),
			NAVIDAD_TVS_VERSION,
			true
		);
	}

	/**
	 * Renderiza la página de acceso.
	 *
	 * @param array $atributos Atributos del shortcode.
	 * @return string
	 */
	public function render( $atributos = array() ) {
		$this->activo = true;

		wp_enqueue_style( 'navidad-tvs-arcade' );
		wp_enqueue_script( 'navidad-tvs-acceso' );

		$site_key = (string) $this->settings->get( 'turnstile_site_key' );

		if ( '' !== $site_key ) {
			wp_enqueue_script(
				'cloudflare-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				true
			);
		}

		wp_localize_script(
			'navidad-tvs-acceso',
			'NAVIDAD_TVS',
			array(
				'endpointAcceso'  => rest_url( NAVIDAD_TVS_REST_NS . '/acceso' ),
				'endpointEstado'  => rest_url( NAVIDAD_TVS_REST_NS . '/estado' ),
				'endpointIniciar' => rest_url( NAVIDAD_TVS_REST_NS . '/carrera/iniciar' ),
				/*
				 * El bundle del juego no se encola con la página: pesa bastante
				 * y quien solo viene a leer los términos no tiene por qué
				 * descargarlo. acceso.js lo pide en cuanto el acceso es válido,
				 * mientras el participante lee las instrucciones, así que para
				 * cuando pulsa "Iniciar carrera" ya está listo.
				 */
				'urlJuego'        => NAVIDAD_TVS_URL . 'assets/game/juego.js?ver=' . NAVIDAD_TVS_VERSION,
				/*
				 * Colores y textos del juego. Se puede editar el archivo sin
				 * recompilar el bundle: sirve para ajustar el cartel de la
				 * tribuna o un color de marca sin tocar TypeScript.
				 */
				'urlTema'         => NAVIDAD_TVS_URL . 'assets/game/theme.json?ver=' . NAVIDAD_TVS_VERSION,
				'turnstileKey'    => $site_key,
				'textos'         => array(
					'validando'     => __( 'Validando…', 'navidad-tvs' ),
					'jugar'         => __( 'Entrar al juego', 'navidad-tvs' ),
					'cargando'      => __( 'Cargando el juego…', 'navidad-tvs' ),
					'listo'         => __( 'Iniciar carrera', 'navidad-tvs' ),
					'errorJuego'    => __( 'No se pudo cargar el juego. Revisa tu conexión y recarga la página.', 'navidad-tvs' ),
					'preparando'    => __( 'Preparando la pista…', 'navidad-tvs' ),
					'errorRed'      => __( 'No pudimos conectarnos. Revisa tu conexión e inténtalo de nuevo.', 'navidad-tvs' ),
					'errorServidor' => __( 'El servidor no respondió como esperábamos. Inténtalo de nuevo en unos segundos.', 'navidad-tvs' ),
					'errorGeneral'  => __( 'Algo salió mal. Recarga la página e inténtalo de nuevo.', 'navidad-tvs' ),
				),
			)
		);

		$estado    = $this->acceso->estado_ventana();
		$settings  = $this->settings;
		$url_tyc   = $this->url_pagina( 'pagina_terminos' );
		$url_faq   = $this->url_pagina( 'pagina_faq' );
		$site_key  = $site_key;
		$duracion  = NAVIDAD_TVS_DURACION_SEGUNDOS;

		ob_start();

		if ( $estado['abierta'] ) {
			include NAVIDAD_TVS_PATH . 'templates/user/acceso.php';
		} else {
			include NAVIDAD_TVS_PATH . 'templates/user/fuera-de-horario.php';
		}

		return ob_get_clean();
	}

	/**
	 * URL de una página configurada, o '#' si todavía no se asignó.
	 *
	 * @param string $clave Clave de la configuración.
	 * @return string
	 */
	private function url_pagina( $clave ) {
		$id = (int) $this->settings->get( $clave );

		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return $url;
			}
		}

		return '#';
	}
}
