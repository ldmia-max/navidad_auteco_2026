<?php
/**
 * Páginas públicas: home, FAQ y términos.
 *
 * Van como shortcodes y no como contenido del editor por la misma razón que el
 * texto vive en class-contenido.php: se versionan con el código, se traducen y
 * cuando la mecánica cambia se cambian con ella.
 *
 * El shortcode del juego vive aparte, en class-shortcode.php, porque tiene
 * bastante más máquina detrás.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/class-contenido.php';

/**
 * Las páginas que no son el juego.
 */
class NavidadTVS_Paginas {

	const TAG_HOME     = 'concurso_tvs_home';
	const TAG_FAQ      = 'concurso_tvs_faq';
	const TAG_TERMINOS = 'concurso_tvs_terminos';

	/** @var NavidadTVS_Settings */
	private $settings;

	/** @var bool Si la página actual lleva alguno de estos shortcodes. */
	private $activo = false;

	/**
	 * @param NavidadTVS_Settings $settings Configuración.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Cablea los hooks.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_shortcode( self::TAG_HOME, array( $this, 'render_home' ) );
		add_shortcode( self::TAG_FAQ, array( $this, 'render_faq' ) );
		add_shortcode( self::TAG_TERMINOS, array( $this, 'render_terminos' ) );

		// En 'init', no en 'wp_enqueue_scripts': con un tema de bloques el
		// shortcode se renderiza antes. Misma trampa que en class-shortcode.php.
		add_action( 'init', array( $this, 'registrar_assets' ) );
	}

	/**
	 * Registra la hoja de estilos del sitio público.
	 *
	 * @return void
	 */
	public function registrar_assets() {
		wp_register_style(
			'navidad-tvs-publico',
			NAVIDAD_TVS_URL . 'assets/css/publico.css',
			array(),
			NAVIDAD_TVS_VERSION
		);
	}

	/**
	 * Enlaces entre las páginas del concurso.
	 *
	 * Se resuelven desde los ajustes. Si una página no está configurada, su
	 * enlace no se pinta: es mejor que falte un enlace a que lleve a la
	 * portada del sitio sin explicación.
	 *
	 * @return array{juego: string, faq: string, terminos: string}
	 */
	private function enlaces() {
		$de = function ( $clave ) {
			$id = (int) $this->settings->get( $clave );
			return $id > 0 ? (string) get_permalink( $id ) : '';
		};

		return array(
			'juego'    => $de( 'pagina_juego' ),
			'faq'      => $de( 'pagina_faq' ),
			'terminos' => $de( 'pagina_terminos' ),
		);
	}

	/**
	 * Carga una plantilla y devuelve lo que imprime.
	 *
	 * @param string $nombre Archivo dentro de templates/user/.
	 * @param array  $datos  Variables para la plantilla.
	 * @return string
	 */
	private function plantilla( $nombre, array $datos ) {
		$this->activo = true;
		wp_enqueue_style( 'navidad-tvs-publico' );

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $datos, EXTR_SKIP );

		ob_start();
		include NAVIDAD_TVS_PATH . 'templates/user/' . $nombre;
		return (string) ob_get_clean();
	}

	/**
	 * [concurso_tvs_home]
	 *
	 * @return string
	 */
	public function render_home() {
		$horario = NavidadTVS_Plugin::instancia()->acceso->estado_ventana();

		return $this->plantilla(
			'home.php',
			array(
				'enlaces' => $this->enlaces(),
				'horario' => $horario['horario'],
				'abierta' => ! empty( $horario['abierta'] ),
				'premios' => (int) $this->settings->get( 'premios_por_jornada' ),
			)
		);
	}

	/**
	 * [concurso_tvs_faq]
	 *
	 * @return string
	 */
	public function render_faq() {
		return $this->plantilla(
			'faq.php',
			array(
				'grupos'  => NavidadTVS_Contenido::faq(),
				'enlaces' => $this->enlaces(),
			)
		);
	}

	/**
	 * [concurso_tvs_terminos]
	 *
	 * @return string
	 */
	public function render_terminos() {
		return $this->plantilla(
			'terminos.php',
			array(
				'clausulas' => NavidadTVS_Contenido::terminos(),
				'version'   => (string) $this->settings->get( 'terminos_version' ),
				'enlaces'   => $this->enlaces(),
			)
		);
	}

	/**
	 * Crea las páginas del concurso y las deja apuntadas en los ajustes.
	 *
	 * Idempotente: si una página ya está creada y sigue existiendo, no crea
	 * otra. Así se puede pulsar el botón dos veces sin llenar el sitio de
	 * borradores duplicados, que es lo que pasa siempre.
	 *
	 * @param NavidadTVS_Settings $settings Configuración.
	 * @return array<string, array{id: int, creada: bool, titulo: string}>
	 */
	public static function crear_paginas( $settings ) {
		$definiciones = array(
			'pagina_juego'    => array(
				'titulo'    => __( 'Jugar', 'navidad-tvs' ),
				'slug'      => 'jugar',
				'contenido' => '[' . NavidadTVS_Shortcode::TAG . ']',
			),
			'pagina_faq'      => array(
				'titulo'    => __( 'Preguntas frecuentes', 'navidad-tvs' ),
				'slug'      => 'preguntas-frecuentes',
				'contenido' => '[' . self::TAG_FAQ . ']',
			),
			'pagina_terminos' => array(
				'titulo'    => __( 'Términos y condiciones', 'navidad-tvs' ),
				'slug'      => 'terminos-y-condiciones',
				'contenido' => '[' . self::TAG_TERMINOS . ']',
			),
			'pagina_home'     => array(
				'titulo'    => __( 'Concurso Navideño TVS', 'navidad-tvs' ),
				'slug'      => 'concurso-navideno-tvs',
				'contenido' => '[' . self::TAG_HOME . ']',
			),
		);

		$resultado = array();
		$guardar   = array();

		foreach ( $definiciones as $clave => $def ) {
			$id = (int) $settings->get( $clave );

			if ( $id > 0 && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
				$resultado[ $clave ] = array(
					'id'     => $id,
					'creada' => false,
					'titulo' => get_the_title( $id ),
				);
				continue;
			}

			// Puede existir de un intento anterior en que no se guardó el ajuste.
			$existente = get_page_by_path( $def['slug'] );

			if ( $existente instanceof WP_Post ) {
				$guardar[ $clave ]   = (int) $existente->ID;
				$resultado[ $clave ] = array(
					'id'     => (int) $existente->ID,
					'creada' => false,
					'titulo' => $existente->post_title,
				);
				continue;
			}

			$nuevo = wp_insert_post(
				array(
					'post_title'   => $def['titulo'],
					'post_name'    => $def['slug'],
					'post_content' => $def['contenido'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				true
			);

			if ( is_wp_error( $nuevo ) ) {
				$resultado[ $clave ] = array(
					'id'     => 0,
					'creada' => false,
					'titulo' => $nuevo->get_error_message(),
				);
				continue;
			}

			$guardar[ $clave ]   = (int) $nuevo;
			$resultado[ $clave ] = array(
				'id'     => (int) $nuevo,
				'creada' => true,
				'titulo' => $def['titulo'],
			);
		}

		if ( ! empty( $guardar ) ) {
			$settings->set( $guardar );
		}

		return $resultado;
	}
}
