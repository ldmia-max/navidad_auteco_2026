<?php
/**
 * Menú del panel de administración.
 *
 * Esta clase solo registra el menú y despacha. Cada módulo real vive en
 * includes/admin/ y recibe $database y $settings por constructor.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menú y páginas del admin.
 */
class NavidadTVS_Admin {

	const CAPACIDAD = 'manage_options';
	const SLUG      = 'navidad-tvs';

	/** @var NavidadTVS_Database */
	private $database;

	/** @var NavidadTVS_Settings */
	private $settings;

	/** @var NavidadTVS_Import_Padron */
	private $importador;

	/** @var NavidadTVS_Admin_Settings */
	private $config;

	/**
	 * @param NavidadTVS_Database $database Acceso a datos.
	 * @param NavidadTVS_Settings $settings Configuración.
	 */
	public function __construct( $database, $settings ) {
		$this->database = $database;
		$this->settings = $settings;

		require_once NAVIDAD_TVS_PATH . 'includes/admin/class-import-padron.php';
		$this->importador = new NavidadTVS_Import_Padron( $database );

		require_once NAVIDAD_TVS_PATH . 'includes/admin/class-admin-settings.php';
		$this->config = new NavidadTVS_Admin_Settings( $settings );
	}

	/**
	 * Cablea los hooks del admin.
	 *
	 * Al agregar un módulo nuevo: require_once + instanciar en el constructor
	 * + registrar sus hooks aquí.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_action( 'admin_menu', array( $this, 'registrar_menu' ) );
		$this->importador->registrar_hooks();
		$this->config->registrar_hooks();
	}

	/**
	 * Registra el menú y sus subpáginas.
	 *
	 * @return void
	 */
	public function registrar_menu() {
		add_menu_page(
			__( 'Concurso Navideño TVS', 'navidad-tvs' ),
			__( 'Concurso TVS', 'navidad-tvs' ),
			self::CAPACIDAD,
			self::SLUG,
			array( $this, 'render_estado' ),
			'dashicons-awards',
			30
		);

		add_submenu_page(
			self::SLUG,
			__( 'Estado', 'navidad-tvs' ),
			__( 'Estado', 'navidad-tvs' ),
			self::CAPACIDAD,
			self::SLUG,
			array( $this, 'render_estado' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Ranking', 'navidad-tvs' ),
			__( 'Ranking', 'navidad-tvs' ),
			self::CAPACIDAD,
			self::SLUG . '-ranking',
			array( $this, 'render_ranking' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Importar padrón', 'navidad-tvs' ),
			__( 'Importar padrón', 'navidad-tvs' ),
			self::CAPACIDAD,
			self::SLUG . '-importar',
			array( $this, 'render_importar' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Configuración', 'navidad-tvs' ),
			__( 'Configuración', 'navidad-tvs' ),
			self::CAPACIDAD,
			self::SLUG . '-configuracion',
			array( $this, 'render_configuracion' )
		);
	}

	/**
	 * Página de estado: qué hay instalado y si la ventana está abierta.
	 *
	 * @return void
	 */
	public function render_estado() {
		if ( ! current_user_can( self::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}

		$hoy      = NavidadTVS_Plugin::hoy();
		$abierta  = NavidadTVS_Plugin::ventana_abierta();
		$settings = $this->settings;

		include NAVIDAD_TVS_PATH . 'includes/admin/templates/estado.php';
	}

	/**
	 * Ranking de participantes. Se implementa en E8.
	 *
	 * @return void
	 */
	public function render_ranking() {
		$this->render_pendiente(
			__( 'Ranking', 'navidad-tvs' ),
			__( 'El listado de participantes y la exportación se implementan en la etapa E8.', 'navidad-tvs' )
		);
	}

	/**
	 * Importador del padrón.
	 *
	 * @return void
	 */
	public function render_importar() {
		if ( ! current_user_can( self::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}

		// El resultado se conserva hasta que el usuario recarga: así el enlace
		// de descarga del reporte de rechazos sigue funcionando.
		$resultado = $this->importador->leer_resultado();
		$database  = $this->database;

		include NAVIDAD_TVS_PATH . 'includes/admin/templates/importar.php';
	}

	/**
	 * Configuración del concurso.
	 *
	 * @return void
	 */
	public function render_configuracion() {
		if ( ! current_user_can( self::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}

		$settings = $this->settings;

		include NAVIDAD_TVS_PATH . 'includes/admin/templates/configuracion.php';
	}

	/**
	 * Marcador para las páginas que todavía no se construyen.
	 *
	 * @param string $titulo  Título de la página.
	 * @param string $mensaje Explicación de en qué etapa llega.
	 * @return void
	 */
	private function render_pendiente( $titulo, $mensaje ) {
		if ( ! current_user_can( self::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $titulo ); ?></h1>
			<div class="notice notice-info inline">
				<p><?php echo esc_html( $mensaje ); ?></p>
			</div>
		</div>
		<?php
	}
}
