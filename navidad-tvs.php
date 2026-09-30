<?php
/**
 * Plugin Name: Concurso Navideño TVS
 * Plugin URI:  https://www.auteco.com.co/tvs
 * Description: Juego arcade-retro estilo Excitebike para la campaña navideña de Auteco TVS. Carrera de 90 segundos; gana quien recorra más metros.
 * Version:     1.19.0
 * Author:      Auteco
 * Text Domain: navidad-tvs
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versión del plugin.
 *
 * Al subirla hay que tocar DOS lugares: el header "Version:" de arriba y esta
 * constante. Cambiar la constante es lo que dispara maybe_upgrade() ->
 * create_tables() en instalaciones ya activas, y además invalida el caché de
 * CSS y JS en los navegadores.
 */
define( 'NAVIDAD_TVS_VERSION', '1.19.0' );

define( 'NAVIDAD_TVS_FILE', __FILE__ );
define( 'NAVIDAD_TVS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NAVIDAD_TVS_URL', plugin_dir_url( __FILE__ ) );

/** Única marca admitida. Las demás se rechazan al importar el padrón. */
define( 'NAVIDAD_TVS_MARCA', 'TVS' );

/** Namespace de la API REST. Nunca se usa admin-ajax.php. */
define( 'NAVIDAD_TVS_REST_NS', 'concurso/v1' );

/** Zona horaria de referencia. La hora del dispositivo del participante no se consulta. */
define( 'NAVIDAD_TVS_TZ', 'America/Bogota' );

/** Parámetros de la simulación. Deben coincidir con docs/mecanica-y-balanceo.md. */
define( 'NAVIDAD_TVS_TICKS_POR_SEGUNDO', 60 );
define( 'NAVIDAD_TVS_DURACION_SEGUNDOS', 90 );
define( 'NAVIDAD_TVS_TOTAL_TICKS', NAVIDAD_TVS_TICKS_POR_SEGUNDO * NAVIDAD_TVS_DURACION_SEGUNDOS );

/**
 * Tope de plausibilidad: por encima de esto se rechaza sin analizar.
 *
 * Espejo de DISTANCIA_MAXIMA_M en game/src/sim/constantes.ts, donde está la
 * explicación de cómo sale el número. Los dos tienen que cambiar juntos.
 */
define( 'NAVIDAD_TVS_DISTANCIA_MAXIMA_M', 4200 );

require_once NAVIDAD_TVS_PATH . 'includes/class-settings.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-database.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-acceso.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-validador.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-rest.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-shortcode.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-seguridad.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-paginas.php';
require_once NAVIDAD_TVS_PATH . 'includes/class-admin.php';

/**
 * Clase principal del plugin.
 *
 * Singleton. Cablea las piezas y expone los helpers transversales
 * (normalización de teléfono y ventana de participación).
 */
final class NavidadTVS_Plugin {

	/** @var NavidadTVS_Plugin|null */
	private static $instancia = null;

	/** @var NavidadTVS_Settings */
	public $settings;

	/** @var NavidadTVS_Database */
	public $database;

	/** @var NavidadTVS_Admin */
	public $admin;

	/** @var NavidadTVS_Acceso */
	public $acceso;

	/** @var NavidadTVS_Validador */
	public $validador;

	/** @var NavidadTVS_Rest */
	public $rest;

	/** @var NavidadTVS_Shortcode */
	public $shortcode;

	/** @var NavidadTVS_Seguridad */
	public $seguridad;

	/** @var NavidadTVS_Paginas */
	public $paginas;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return NavidadTVS_Plugin
	 */
	public static function instancia() {
		if ( null === self::$instancia ) {
			self::$instancia = new self();
		}
		return self::$instancia;
	}

	private function __construct() {
		$this->settings = new NavidadTVS_Settings();
		$this->database = new NavidadTVS_Database();
		$this->acceso   = new NavidadTVS_Acceso( $this->database, $this->settings );

		$this->validador = new NavidadTVS_Validador( $this->database );

		$this->rest = new NavidadTVS_Rest( $this->acceso, $this->validador );
		$this->rest->registrar_hooks();

		$this->shortcode = new NavidadTVS_Shortcode( $this->acceso, $this->settings );
		$this->shortcode->registrar_hooks();

		$this->seguridad = new NavidadTVS_Seguridad( $this->settings );
		$this->seguridad->registrar_hooks();

		$this->paginas = new NavidadTVS_Paginas( $this->settings );
		$this->paginas->registrar_hooks();

		if ( is_admin() ) {
			$this->admin = new NavidadTVS_Admin( $this->database, $this->settings );
			$this->admin->registrar_hooks();
		}

		add_action( 'plugins_loaded', array( $this, 'cargar_traducciones' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Carga el text domain.
	 */
	public function cargar_traducciones() {
		load_plugin_textdomain(
			'navidad-tvs',
			false,
			dirname( plugin_basename( NAVIDAD_TVS_FILE ) ) . '/languages'
		);
	}

	/**
	 * Aplica migraciones cuando cambia NAVIDAD_TVS_VERSION.
	 */
	public function maybe_upgrade() {
		$instalada = get_option( 'navidad_tvs_db_version' );

		if ( NAVIDAD_TVS_VERSION === $instalada ) {
			return;
		}

		$this->database->create_tables();
		update_option( 'navidad_tvs_db_version', NAVIDAD_TVS_VERSION );
	}

	/**
	 * Se ejecuta al activar el plugin.
	 */
	public static function activar() {
		$plugin = self::instancia();
		$plugin->database->create_tables();
		$plugin->settings->asegurar_defaults();
		update_option( 'navidad_tvs_db_version', NAVIDAD_TVS_VERSION );
	}

	/**
	 * Se ejecuta al desactivar. No borra datos: el padrón y los scores se
	 * conservan para el acta de ganadores.
	 */
	public static function desactivar() {
		// Reservado. Nada que limpiar por ahora.
	}

	// ---------------------------------------------------------------------
	// Helpers transversales
	// ---------------------------------------------------------------------

	/**
	 * Normaliza un número de celular colombiano a 10 dígitos.
	 *
	 * El padrón llega con indicativo país ("573504567217", 12 dígitos) y el
	 * participante digita 10 ("3504567217"). Sin esta normalización en ambos
	 * extremos, nadie logra entrar.
	 *
	 * Acepta espacios, guiones, paréntesis y el prefijo "+57" o "57".
	 *
	 * @param string $telefono Número tal como viene del CSV o del formulario.
	 * @return string Diez dígitos, o cadena vacía si no se pudo normalizar.
	 */
	public static function normalizar_telefono( $telefono ) {
		$digitos = preg_replace( '/\D+/', '', (string) $telefono );

		if ( '' === $digitos ) {
			return '';
		}

		// 573504567217 -> 3504567217. Solo se quita el 57 si lo que queda son
		// 10 dígitos que empiezan por 3, que es el rango de celulares en
		// Colombia. Así un fijo mal digitado no se convierte en algo válido.
		if ( 12 === strlen( $digitos ) && str_starts_with( $digitos, '57' ) ) {
			$digitos = substr( $digitos, 2 );
		}

		if ( 10 !== strlen( $digitos ) || '3' !== $digitos[0] ) {
			return '';
		}

		return $digitos;
	}

	/**
	 * Fecha y hora actual en la zona del concurso.
	 *
	 * @return DateTimeImmutable
	 */
	public static function ahora() {
		return new DateTimeImmutable( 'now', new DateTimeZone( NAVIDAD_TVS_TZ ) );
	}

	/**
	 * Fecha de hoy en la zona del concurso, formato Y-m-d.
	 *
	 * @return string
	 */
	public static function hoy() {
		return self::ahora()->format( 'Y-m-d' );
	}

	/**
	 * Indica si en este momento está abierta la ventana de participación.
	 *
	 * Siempre con hora de servidor. La hora del dispositivo del participante
	 * no interviene: cambiarla no abre la ventana.
	 *
	 * @param DateTimeImmutable|null $momento Para pruebas. Por defecto, ahora.
	 * @return bool
	 */
	public static function ventana_abierta( $momento = null ) {
		$settings = self::instancia()->settings;

		if ( $settings->get( 'concurso_congelado' ) ) {
			return false;
		}

		$momento = $momento ? $momento : self::ahora();

		// 'N' devuelve 1 (lunes) a 7 (domingo).
		$dia = (int) $momento->format( 'N' );
		if ( ! in_array( $dia, $settings->get( 'dias_habiles' ), true ) ) {
			return false;
		}

		$actual = $momento->format( 'H:i' );

		return $actual >= $settings->get( 'hora_inicio' )
			&& $actual < $settings->get( 'hora_fin' );
	}
}

register_activation_hook( __FILE__, array( 'NavidadTVS_Plugin', 'activar' ) );
register_deactivation_hook( __FILE__, array( 'NavidadTVS_Plugin', 'desactivar' ) );

NavidadTVS_Plugin::instancia();
