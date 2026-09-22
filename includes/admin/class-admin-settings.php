<?php
/**
 * Página de configuración del concurso.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formulario de configuración.
 */
class NavidadTVS_Admin_Settings {

	/** @var NavidadTVS_Settings */
	private $settings;

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
		add_action( 'admin_post_navidad_tvs_guardar_config', array( $this, 'guardar' ) );
	}

	/**
	 * Procesa el formulario.
	 *
	 * @return void
	 */
	public function guardar() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para cambiar la configuración.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_guardar_config' );

		$dias = array();
		if ( isset( $_POST['dias_habiles'] ) && is_array( $_POST['dias_habiles'] ) ) {
			foreach ( wp_unslash( $_POST['dias_habiles'] ) as $d ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$n = (int) $d;
				if ( $n >= 1 && $n <= 7 ) {
					$dias[] = $n;
				}
			}
		}
		sort( $dias );

		$valores = array(
			'hora_inicio'          => $this->sanear_hora( $_POST['hora_inicio'] ?? '', '12:00' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'hora_fin'             => $this->sanear_hora( $_POST['hora_fin'] ?? '', '13:30' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'dias_habiles'         => $dias,
			'premios_por_jornada'  => max( 1, (int) ( $_POST['premios_por_jornada'] ?? 4 ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'concurso_congelado'   => ! empty( $_POST['concurso_congelado'] ),
			'terminos_version'     => sanitize_text_field( wp_unslash( $_POST['terminos_version'] ?? '1.0' ) ),
			'pagina_terminos'      => (int) ( $_POST['pagina_terminos'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'pagina_faq'           => (int) ( $_POST['pagina_faq'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'turnstile_site_key'   => sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ?? '' ) ),
			'turnstile_secret_key' => sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ?? '' ) ),
		);

		$aviso = 'guardado';

		// Una hora de fin anterior o igual a la de inicio dejaría la ventana
		// permanentemente cerrada, sin ninguna señal de por qué.
		if ( $valores['hora_fin'] <= $valores['hora_inicio'] ) {
			$valores['hora_fin'] = $this->settings->get( 'hora_fin' );
			$aviso               = 'horario_invalido';
		}

		if ( empty( $valores['dias_habiles'] ) ) {
			$valores['dias_habiles'] = $this->settings->get( 'dias_habiles' );
			$aviso                   = 'sin_dias';
		}

		$this->settings->set( $valores );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => NavidadTVS_Admin::SLUG . '-configuracion',
					'aviso' => $aviso,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Valida una hora en formato H:i.
	 *
	 * @param string $valor Valor recibido.
	 * @param string $sino  Valor por defecto si no es válido.
	 * @return string
	 */
	private function sanear_hora( $valor, $sino ) {
		$valor = sanitize_text_field( wp_unslash( (string) $valor ) );

		if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $valor ) ) {
			return $valor;
		}

		return $sino;
	}
}
