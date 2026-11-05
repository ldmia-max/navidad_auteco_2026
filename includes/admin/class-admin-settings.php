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
		add_action( 'admin_post_navidad_tvs_crear_paginas', array( $this, 'crear_paginas' ) );
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
			/*
			 * Una fecha ilegible se guarda como vacía, no como la anterior.
			 * Vacío aquí significa "sin límite", que es un estado válido y
			 * visible en la pantalla; conservar a escondidas la fecha previa
			 * dejaría el formulario diciendo una cosa y el concurso haciendo
			 * otra.
			 */
			'fecha_inicio'         => $this->sanear_fecha( $_POST['fecha_inicio'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'fecha_fin'            => $this->sanear_fecha( $_POST['fecha_fin'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'hora_inicio'          => $this->sanear_hora( $_POST['hora_inicio'] ?? '', '12:00' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'hora_fin'             => $this->sanear_hora( $_POST['hora_fin'] ?? '', '13:30' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'dias_habiles'         => $dias,
			'premios_por_jornada'  => max( 1, (int) ( $_POST['premios_por_jornada'] ?? 4 ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'concurso_congelado'   => ! empty( $_POST['concurso_congelado'] ),
			'terminos_version'     => sanitize_text_field( wp_unslash( $_POST['terminos_version'] ?? '1.0' ) ),
			'pagina_home'          => (int) ( $_POST['pagina_home'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'pagina_juego'         => (int) ( $_POST['pagina_juego'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'pagina_terminos'      => (int) ( $_POST['pagina_terminos'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'pagina_faq'           => (int) ( $_POST['pagina_faq'] ?? 0 ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			/*
			 * Se guarda normalizado a diez dígitos, igual que el padrón, para
			 * que dé lo mismo cómo lo escriba quien lo configure: con
			 * indicativo, con espacios o con guiones.
			 */
			'telefono_pruebas'     => NavidadTVS_Plugin::normalizar_telefono(
				sanitize_text_field( wp_unslash( $_POST['telefono_pruebas'] ?? '' ) )
			),
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

		/*
		 * Un rango al revés no cierra el concurso un rato: lo cierra para
		 * siempre, y sin decir por qué. Se rechaza el cambio entero —las dos
		 * fechas, no solo la de cierre— y se dejan las que había.
		 *
		 * Volver a las dos anteriores es lo que mantiene la invariante: el par
		 * guardado siempre es un rango válido, así que restaurarlo no puede
		 * dejar otro inválido. Devolver solo una sí podría.
		 *
		 * Las dos fechas iguales se aceptan: es un concurso de un solo día.
		 */
		if ( '' !== $valores['fecha_inicio'] && '' !== $valores['fecha_fin']
			&& $valores['fecha_fin'] < $valores['fecha_inicio'] ) {
			$valores['fecha_inicio'] = (string) $this->settings->get( 'fecha_inicio' );
			$valores['fecha_fin']    = (string) $this->settings->get( 'fecha_fin' );
			$aviso                   = 'fechas_invalidas';
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
	 * Crea las cuatro páginas del sitio público y las deja apuntadas.
	 *
	 * Es idempotente, así que pulsar el botón dos veces no duplica nada.
	 *
	 * @return void
	 */
	public function crear_paginas() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para crear las páginas.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_crear_paginas' );

		$resultado = NavidadTVS_Paginas::crear_paginas( $this->settings );

		$creadas = 0;
		foreach ( $resultado as $info ) {
			if ( ! empty( $info['creada'] ) ) {
				$creadas++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => NavidadTVS_Admin::SLUG . '-configuracion',
					'aviso'   => 'paginas',
					'creadas' => $creadas,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Valida una fecha en formato Y-m-d.
	 *
	 * Vacío es un valor legítimo: significa que no hay límite por ese lado.
	 *
	 * checkdate() hace falta además del patrón. El input de tipo date no deja
	 * escribir un 31 de febrero, pero el formulario se puede enviar sin
	 * navegador, y una fecha así la aceptaría la expresión regular y luego
	 * DateTime la correría al 3 de marzo.
	 *
	 * @param string $valor Valor recibido.
	 * @return string Fecha Y-m-d, o cadena vacía.
	 */
	private function sanear_fecha( $valor ) {
		$valor = sanitize_text_field( wp_unslash( (string) $valor ) );

		if ( '' === $valor ) {
			return '';
		}

		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m ) ) {
			return '';
		}

		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		return $valor;
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
