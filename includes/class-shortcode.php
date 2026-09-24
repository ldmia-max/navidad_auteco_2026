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
			self::version_asset( 'assets/css/arcade.css' )
		);

		wp_register_script(
			'navidad-tvs-acceso',
			NAVIDAD_TVS_URL . 'assets/js/acceso.js',
			array(),
			self::version_asset( 'assets/js/acceso.js' ),
			true
		);
	}

	/**
	 * Renderiza la página de acceso.
	 *
	 * @param array $atributos Atributos del shortcode.
	 * @return string
	 */
	/**
	 * Versión con la que se cachea un archivo de assets.
	 *
	 * No basta con NAVIDAD_TVS_VERSION. Entre dos versiones del plugin la hoja
	 * de estilos y el JavaScript se editan decenas de veces, y el navegador
	 * sirve la copia vieja bajo el mismo ?ver=. Revisar un cambio de diseño y
	 * ver el anterior hace perder el tiempo a quien revisa y lleva a buscar el
	 * fallo donde no está.
	 *
	 * Con la fecha del archivo el problema desaparece en desarrollo y en
	 * producción: cualquier despliegue la cambia.
	 *
	 * @param string $relativa Ruta dentro del plugin.
	 * @return string
	 */
	private static function version_asset( $relativa ) {
		$fecha = @filemtime( NAVIDAD_TVS_PATH . $relativa ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $fecha ? NAVIDAD_TVS_VERSION . '.' . $fecha : NAVIDAD_TVS_VERSION;
	}

	/**
	 * URL de un archivo de assets con un cache buster fiable.
	 *
	 * El bundle y el tema no se encolan con wp_enqueue_script(): los pide el
	 * JavaScript, así que hay que ponerles la versión a mano.
	 *
	 * No basta con NAVIDAD_TVS_VERSION. Entre dos versiones del plugin el
	 * bundle se recompila decenas de veces y theme.json se edita a mano, y el
	 * navegador sirve la copia vieja bajo el mismo ?ver=. El resultado es un
	 * juego a medio actualizar —sprites nuevos con los colores y los textos
	 * viejos— que parece un error del código y no lo es. Ya pasó una vez.
	 *
	 * Con la fecha del archivo el problema desaparece en desarrollo y en
	 * producción: cualquier despliegue la cambia. La versión se conserva
	 * delante para que la URL siga diciendo de qué release viene.
	 *
	 * @param string $relativa Ruta dentro del plugin.
	 * @return string
	 */
	private static function url_asset( $relativa ) {
		return NAVIDAD_TVS_URL . $relativa . '?ver=' . rawurlencode( self::version_asset( $relativa ) );
	}

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
				'endpointTerminar' => rest_url( NAVIDAD_TVS_REST_NS . '/carrera/terminar' ),
				/*
				 * El bundle del juego no se encola con la página: pesa bastante
				 * y quien solo viene a leer los términos no tiene por qué
				 * descargarlo. acceso.js lo pide en cuanto el acceso es válido,
				 * mientras el participante lee las instrucciones, así que para
				 * cuando pulsa "Iniciar carrera" ya está listo.
				 */
				'urlJuego'        => self::url_asset( 'assets/game/juego.js' ),
				/*
				 * Colores y textos del juego. Se puede editar el archivo sin
				 * recompilar el bundle: sirve para ajustar el cartel de la
				 * tribuna o un color de marca sin tocar TypeScript.
				 */
				'urlTema'         => self::url_asset( 'assets/game/theme.json' ),
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
					'enviando'      => __( 'Enviando tu resultado… no cierres esta página.', 'navidad-tvs' ),
					'enviado'       => __( 'Resultado registrado. Ya puedes cerrar la página.', 'navidad-tvs' ),
					'envioFallo'    => __( 'No pudimos enviar tu resultado. Revisa tu conexión y vuelve a intentarlo SIN cerrar esta página.', 'navidad-tvs' ),
					'envioRechazado' => __( 'El servidor no aceptó el resultado.', 'navidad-tvs' ),
					'envioNoValido' => __( 'Tu resultado quedó registrado, pero marcado para revisión del organizador.', 'navidad-tvs' ),
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
