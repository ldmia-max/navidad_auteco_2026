<?php
/**
 * Endurecimiento de las páginas y los endpoints del concurso.
 *
 * Las cabeceras se ponen SOLO donde vive el concurso, no en todo el sitio.
 * El plugin convive con un tema y con Elementor, y una cabecera de más en una
 * página que no es suya puede romper algo que funcionaba; además, nadie
 * espera que instalar un plugin de concursos le cambie las cabeceras al blog.
 *
 * Lo que NO se pone desde aquí, a propósito:
 *
 * - HSTS. Es del servidor y solo tiene sentido si todo el dominio va por
 *   HTTPS. Emitirla desde PHP en un sitio que aún sirva HTTP deja a los
 *   visitantes sin poder entrar durante el tiempo que dure la caché.
 * - Content-Security-Policy. Es la más útil de todas y también la que más
 *   rompe: el tema y Elementor inyectan estilos y scripts en línea, así que
 *   una CSP escrita a ciegas dejaría la página en blanco. Va en la
 *   configuración del servidor, medida contra el sitio real. Está anotada en
 *   docs/operacion-y-qa.md.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cabeceras de seguridad del concurso.
 */
class NavidadTVS_Seguridad {

	/**
	 * Configuración, para saber cuáles son las páginas del concurso.
	 *
	 * @var NavidadTVS_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param NavidadTVS_Settings $settings Configuración.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Engancha las cabeceras.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		/*
		 * template_redirect y no send_headers: cuando send_headers dispara, la
		 * consulta principal todavía puede no estar resuelta, y sin ella
		 * is_page() y has_shortcode() no saben responder. En template_redirect
		 * la consulta ya está y la salida todavía no ha empezado.
		 */
		add_action( 'template_redirect', array( $this, 'cabeceras_pagina' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'cabeceras_rest' ), 10, 3 );
	}

	/**
	 * Cabeceras de las páginas del concurso.
	 *
	 * @return void
	 */
	public function cabeceras_pagina() {
		if ( headers_sent() || ! $this->es_pagina_del_concurso() ) {
			return;
		}

		// El navegador respeta el Content-Type declarado y no adivina. Sin
		// esto, un archivo subido puede acabar ejecutándose como script.
		header( 'X-Content-Type-Options: nosniff' );

		/*
		 * Nadie debería poder meter el juego en un iframe dentro de otro
		 * sitio. No es estético: un iframe ajeno puede superponer botones
		 * invisibles y hacer que el participante gaste su único intento sin
		 * darse cuenta.
		 */
		header( 'X-Frame-Options: SAMEORIGIN' );

		// El dominio sale en el referer, la ruta no. Las URLs del concurso no
		// llevan datos en la ruta hoy, pero esto lo deja cubierto si mañana sí.
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		/*
		 * El juego no usa cámara, micrófono, ubicación ni pagos. Cerrarlos
		 * evita que un script de terceros que entre por el tema pueda pedirlos
		 * desde esta página.
		 */
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()' );
	}

	/**
	 * Cabeceras de los endpoints del concurso.
	 *
	 * @param WP_HTTP_Response $respuesta Respuesta que se va a enviar.
	 * @param WP_REST_Server   $servidor  Servidor REST.
	 * @param WP_REST_Request  $peticion  Petición atendida.
	 * @return WP_HTTP_Response
	 */
	public function cabeceras_rest( $respuesta, $servidor, $peticion ) {
		$ruta = $peticion instanceof WP_REST_Request ? $peticion->get_route() : '';

		if ( 0 !== strpos( ltrim( (string) $ruta, '/' ), NAVIDAD_TVS_REST_NS ) ) {
			return $respuesta;
		}

		if ( ! $respuesta instanceof WP_HTTP_Response ) {
			return $respuesta;
		}

		/*
		 * Nada de esto se guarda en ninguna caché.
		 *
		 * Las respuestas de /acceso y /carrera/iniciar llevan el token de la
		 * sesión y el seed de la pista. Que un proxy, un CDN o el propio
		 * navegador reutilicen una de esas respuestas para otra persona sería
		 * entregarle la sesión de un tercero. Los POST no suelen cachearse,
		 * pero "no suelen" no es una garantía que quiera dar por buena en algo
		 * que reparte premios.
		 */
		$respuesta->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$respuesta->header( 'Pragma', 'no-cache' );
		$respuesta->header( 'X-Content-Type-Options', 'nosniff' );

		return $respuesta;
	}

	/**
	 * Si la petición actual es una página del concurso.
	 *
	 * Se miran las dos formas en que el concurso puede estar en una página: ser
	 * una de las que creó el plugin, o llevar el shortcode puesto a mano. La
	 * segunda importa porque nada impide pegar el shortcode en otra página, y
	 * ahí el juego necesita las mismas protecciones.
	 *
	 * @return bool
	 */
	private function es_pagina_del_concurso() {
		if ( ! is_singular() ) {
			return false;
		}

		$id = (int) get_queried_object_id();

		if ( $id <= 0 ) {
			return false;
		}

		$nuestras = array(
			(int) $this->settings->get( 'pagina_home' ),
			(int) $this->settings->get( 'pagina_juego' ),
			(int) $this->settings->get( 'pagina_terminos' ),
			(int) $this->settings->get( 'pagina_faq' ),
		);

		if ( in_array( $id, $nuestras, true ) ) {
			return true;
		}

		$entrada = get_post( $id );

		return $entrada instanceof WP_Post && has_shortcode( $entrada->post_content, NavidadTVS_Shortcode::TAG );
	}
}
