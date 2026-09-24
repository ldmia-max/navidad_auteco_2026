<?php
/**
 * Configuración del concurso.
 *
 * Todo vive en una sola opción de WordPress, navidad_tvs_settings.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lectura y escritura de la configuración.
 */
class NavidadTVS_Settings {

	const OPCION = 'navidad_tvs_settings';

	/** @var array|null Caché en memoria de la opción. */
	private $cache = null;

	/**
	 * Valores por defecto.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			// Ventana de participación, hora de Colombia.
			'hora_inicio'          => '12:00',
			'hora_fin'             => '13:30',
			// 1 = lunes ... 7 = domingo. Sin domingos.
			'dias_habiles'         => array( 1, 2, 3, 4, 5, 6 ),

			// Cuántos participantes premiados por jornada. En caso de empate
			// en el último puesto, todos los empatados ganan.
			'premios_por_jornada'  => 4,

			// Corta toda participación sin desactivar el plugin.
			'concurso_congelado'   => false,

			// Se guarda junto al consentimiento para saber qué texto aceptó
			// cada participante.
			'terminos_version'     => '1.0',

			// Páginas del sitio público. Las crea el botón de la pantalla de
			// configuración y se pueden reasignar a mano si el cliente ya
			// tenía páginas hechas.
			'pagina_home'          => 0,
			'pagina_juego'         => 0,
			'pagina_terminos'      => 0,
			'pagina_faq'           => 0,

			// Cloudflare Turnstile. Vacío = desactivado.
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
		);
	}

	/**
	 * Devuelve toda la configuración, con los defaults aplicados.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$guardado    = get_option( self::OPCION, array() );
			$guardado    = is_array( $guardado ) ? $guardado : array();
			$this->cache = wp_parse_args( $guardado, $this->defaults() );
		}
		return $this->cache;
	}

	/**
	 * Devuelve un valor de configuración.
	 *
	 * @param string $clave  Clave a leer.
	 * @param mixed  $sino   Valor si la clave no existe.
	 * @return mixed
	 */
	public function get( $clave, $sino = null ) {
		$todo = $this->all();
		return array_key_exists( $clave, $todo ) ? $todo[ $clave ] : $sino;
	}

	/**
	 * Escribe uno o varios valores.
	 *
	 * @param array $valores Pares clave => valor.
	 * @return void
	 */
	public function set( array $valores ) {
		$nuevo = array_merge( $this->all(), $valores );
		update_option( self::OPCION, $nuevo );
		$this->cache = $nuevo;
	}

	/**
	 * Crea la opción con los defaults si todavía no existe.
	 *
	 * @return void
	 */
	public function asegurar_defaults() {
		if ( false === get_option( self::OPCION, false ) ) {
			add_option( self::OPCION, $this->defaults() );
		}
		$this->cache = null;
	}
}
