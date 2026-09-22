<?php
/**
 * Validación de acceso al juego.
 *
 * Decide si una persona puede jugar y, si puede, le crea la sesión de carrera.
 *
 * Reglas, en orden:
 *
 * 1. La ventana de participación está abierta (hora de servidor, nunca la del
 *    dispositivo).
 * 2. El número no se pasó del límite de intentos.
 * 3. Turnstile válido, si está configurado.
 * 4. El teléfono está en el padrón, la marca es TVS y hoy es su jornada.
 * 5. No ha jugado antes.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/class-rate-limit.php';

/**
 * Puerta de entrada al juego.
 */
class NavidadTVS_Acceso {

	/** Vigencia de una sesión emitida, en minutos. */
	const VIGENCIA_MINUTOS = 20;

	/**
	 * Fallos por IP antes de bloquear.
	 *
	 * Alto a propósito: los operadores móviles colombianos usan CGNAT, así que
	 * decenas de participantes legítimos pueden compartir una misma IP de
	 * salida durante la ventana de 90 minutos. Un tope bajo dejaría afuera a
	 * gente que no hizo nada malo. La defensa fina es el tope por teléfono.
	 */
	const TOPE_IP = 60;

	/** Fallos por teléfono antes de bloquear. */
	const TOPE_TELEFONO = 5;

	/** Ventana de los límites, en segundos. */
	const VENTANA_LIMITE = 600;

	/**
	 * Errores que sí cuentan contra el límite de intentos.
	 *
	 * Son los que revelan si un número está en el padrón, que es justo lo que
	 * busca quien enumera teléfonos. Los demás (fuera de horario, nombre mal
	 * escrito, teléfono mal digitado) no dicen nada de nadie y no deben gastar
	 * cupo: si contaran, alguien que insiste a las 11:59 llegaría bloqueado a
	 * las 12:00, que es exactamente cuando necesita entrar.
	 */
	const ERRORES_QUE_CUENTAN = array( 'no_elegible', 'descalificado', 'ya_participo' );

	/** Largo máximo del nombre que digita el participante. */
	const MAX_NOMBRE = 60;

	/** @var NavidadTVS_Database */
	private $database;

	/** @var NavidadTVS_Settings */
	private $settings;

	/**
	 * @param NavidadTVS_Database $database Acceso a datos.
	 * @param NavidadTVS_Settings $settings Configuración.
	 */
	public function __construct( $database, $settings ) {
		$this->database = $database;
		$this->settings = $settings;
	}

	/**
	 * Valida el acceso y crea la sesión.
	 *
	 * Envuelve a validar_interno() para llevar la cuenta de fallos en un solo
	 * sitio: así el contador se alimenta de lo que realmente salió mal, y un
	 * acceso correcto nunca gasta cupo.
	 *
	 * @param array $datos Con 'telefono', 'nombre', 'acepta' y 'turnstile'.
	 * @return array|WP_Error Datos de la sesión, o el motivo del rechazo.
	 */
	public function validar( array $datos ) {
		$ip       = NavidadTVS_Rate_Limit::ip();
		$telefono = NavidadTVS_Plugin::normalizar_telefono( $datos['telefono'] ?? '' );

		$clave_ip  = '' !== $ip ? 'ip:' . $ip : '';
		$clave_tel = '' !== $telefono ? 'tel:' . $telefono : '';

		if ( NavidadTVS_Rate_Limit::bloqueado( $clave_ip, self::TOPE_IP )
			|| NavidadTVS_Rate_Limit::bloqueado( $clave_tel, self::TOPE_TELEFONO ) ) {
			return new WP_Error(
				'demasiados_intentos',
				__( 'Demasiados intentos. Espera unos minutos antes de volver a intentarlo.', 'navidad-tvs' ),
				array( 'status' => 429 )
			);
		}

		$resultado = $this->validar_interno( $datos, $ip, $telefono );

		if ( ! is_wp_error( $resultado ) ) {
			NavidadTVS_Rate_Limit::limpiar( $clave_tel );
			return $resultado;
		}

		if ( in_array( $resultado->get_error_code(), self::ERRORES_QUE_CUENTAN, true ) ) {
			NavidadTVS_Rate_Limit::registrar_fallo( $clave_ip, self::VENTANA_LIMITE );
			NavidadTVS_Rate_Limit::registrar_fallo( $clave_tel, self::VENTANA_LIMITE );
		}

		return $resultado;
	}

	/**
	 * Comprueba las reglas de acceso y crea la sesión.
	 *
	 * @param array  $datos    Datos del formulario.
	 * @param string $ip       IP del visitante.
	 * @param string $telefono Teléfono ya normalizado.
	 * @return array|WP_Error
	 */
	private function validar_interno( array $datos, $ip, $telefono ) {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
			: '';

		// --- 1. Ventana de participación -------------------------------------
		if ( ! NavidadTVS_Plugin::ventana_abierta() ) {
			return new WP_Error(
				'fuera_de_horario',
				__( 'En este momento no hay jornada de participación.', 'navidad-tvs' ),
				array( 'status' => 403 )
			);
		}

		// --- 2. Forma de los datos -------------------------------------------
		$nombre = $this->limpiar_nombre( $datos['nombre'] ?? '' );

		if ( empty( $datos['acepta'] ) ) {
			return new WP_Error(
				'sin_terminos',
				__( 'Debes aceptar los términos y condiciones para participar.', 'navidad-tvs' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $nombre ) {
			return new WP_Error(
				'nombre_invalido',
				__( 'Escribe tu nombre para continuar.', 'navidad-tvs' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $telefono ) {
			return new WP_Error(
				'telefono_invalido',
				__( 'Escribe tu número de celular a 10 dígitos.', 'navidad-tvs' ),
				array( 'status' => 400 )
			);
		}

		// --- 3. Turnstile ------------------------------------------------------
		$turnstile = $this->verificar_turnstile( $datos['turnstile'] ?? '', $ip );
		if ( is_wp_error( $turnstile ) ) {
			return $turnstile;
		}

		// --- 4. Elegibilidad ---------------------------------------------------
		$participante = $this->database->buscar_participante_por_telefono( $telefono );

		/*
		 * Un solo mensaje para "no está en el padrón", "es de otra marca" y
		 * "hoy no es su jornada". Distinguirlos convertiría el formulario en un
		 * buscador de quién compró una moto TVS.
		 */
		$no_elegible = new WP_Error(
			'no_elegible',
			__( 'Este número no está habilitado para participar hoy. Si compraste una moto TVS y crees que es un error, escríbenos por los canales de atención.', 'navidad-tvs' ),
			array( 'status' => 403 )
		);

		if ( null === $participante ) {
			return $no_elegible;
		}

		if ( strtoupper( $participante['marca'] ) !== NAVIDAD_TVS_MARCA ) {
			return $no_elegible;
		}

		if ( $participante['fecha_concurso'] !== NavidadTVS_Plugin::hoy() ) {
			return $no_elegible;
		}

		if ( 'descalificado' === $participante['estado'] ) {
			return new WP_Error(
				'descalificado',
				__( 'Tu participación fue descalificada. Escríbenos por los canales de atención si necesitas más información.', 'navidad-tvs' ),
				array( 'status' => 403 )
			);
		}

		// --- 5. Un solo intento -------------------------------------------------
		$ya_jugo = new WP_Error(
			'ya_participo',
			__( 'Este número ya participó. Cada persona tiene un solo intento.', 'navidad-tvs' ),
			array( 'status' => 409 )
		);

		if ( $this->database->tiene_score( (int) $participante['id'] ) ) {
			return $ya_jugo;
		}

		if ( $this->database->tiene_sesion_consumida( (int) $participante['id'] ) ) {
			return $ya_jugo;
		}

		// --- 6. Sesión ------------------------------------------------------------
		$sesion = $this->obtener_o_crear_sesion( $participante, $nombre, $ip, $ua );

		if ( is_wp_error( $sesion ) ) {
			return $sesion;
		}

		return array(
			'token'          => $sesion['nonce'],
			'nombre'         => $sesion['nombre_digitado'],
			'duracion'       => NAVIDAD_TVS_DURACION_SEGUNDOS,
			'vigencia'       => self::VIGENCIA_MINUTOS * MINUTE_IN_SECONDS,
			'cierre_jornada' => $this->settings->get( 'hora_fin' ),
		);
	}

	/**
	 * Reutiliza la sesión vigente del participante o crea una nueva.
	 *
	 * Reutilizarla importa: si cada recarga creara una sesión, alguien que
	 * refresque la página tres veces dejaría tres filas huérfanas y la
	 * auditoría se ensucia.
	 *
	 * La sesión queda en estado 'emitida'. El intento se marca consumido
	 * cuando arranca la carrera, no ahora: entrar y cerrar la pestaña sin
	 * jugar no debería gastar la única oportunidad.
	 *
	 * @param array  $participante Fila del padrón.
	 * @param string $nombre       Nombre digitado.
	 * @param string $ip           IP del visitante.
	 * @param string $ua           User agent.
	 * @return array|WP_Error
	 */
	private function obtener_o_crear_sesion( $participante, $nombre, $ip, $ua ) {
		$vigente = $this->database->sesion_vigente_de( (int) $participante['id'], self::VIGENCIA_MINUTOS );

		if ( null !== $vigente ) {
			return $vigente;
		}

		$id = $this->database->crear_sesion(
			array(
				'participante_id'  => (int) $participante['id'],
				'nombre_digitado'  => $nombre,
				// uint32: es lo que consume el PRNG mulberry32 del juego.
				'seed'             => random_int( 0, 4294967295 ),
				'nonce'            => bin2hex( random_bytes( 32 ) ),
				'acepto_terminos'  => 1,
				'terminos_version' => (string) $this->settings->get( 'terminos_version' ),
				'ip'               => $ip,
				'user_agent'       => $ua,
			)
		);

		if ( ! $id ) {
			return new WP_Error(
				'sesion',
				__( 'No se pudo iniciar la sesión de juego. Inténtalo de nuevo.', 'navidad-tvs' ),
				array( 'status' => 500 )
			);
		}

		return $this->database->sesion_vigente_de( (int) $participante['id'], self::VIGENCIA_MINUTOS );
	}

	/**
	 * Limpia el nombre que digita el participante.
	 *
	 * Es texto libre que va a aparecer en el ranking y en la pantalla final,
	 * así que se recorta y se le quita cualquier etiqueta.
	 *
	 * @param string $nombre Valor crudo.
	 * @return string
	 */
	private function limpiar_nombre( $nombre ) {
		$nombre = wp_strip_all_tags( (string) $nombre );
		$nombre = preg_replace( '/\s+/u', ' ', $nombre );
		$nombre = trim( $nombre );

		if ( function_exists( 'mb_substr' ) ) {
			$nombre = mb_substr( $nombre, 0, self::MAX_NOMBRE );
		} else {
			$nombre = substr( $nombre, 0, self::MAX_NOMBRE );
		}

		// Al menos dos caracteres que sean letras.
		if ( ! preg_match( '/\p{L}{2,}/u', $nombre ) ) {
			return '';
		}

		return $nombre;
	}

	/**
	 * Verifica el token de Cloudflare Turnstile.
	 *
	 * Si no hay claves configuradas, la verificación se omite: así el entorno
	 * de desarrollo funciona sin cuenta de Cloudflare.
	 *
	 * @param string $token Token que envía el widget.
	 * @param string $ip    IP del visitante.
	 * @return true|WP_Error
	 */
	private function verificar_turnstile( $token, $ip ) {
		$secreto = (string) $this->settings->get( 'turnstile_secret_key' );

		if ( '' === $secreto ) {
			return true;
		}

		if ( '' === $token ) {
			return new WP_Error(
				'turnstile',
				__( 'No se pudo comprobar que eres una persona. Recarga la página e inténtalo de nuevo.', 'navidad-tvs' ),
				array( 'status' => 400 )
			);
		}

		$respuesta = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secreto,
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			/*
			 * Cloudflare caído no puede dejar sin jugar a toda la jornada: son
			 * 90 minutos al día y no hay reintentos. Se deja pasar y se registra
			 * para poder auditarlo después.
			 */
			error_log( '[navidad-tvs] Turnstile inaccesible: ' . $respuesta->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return true;
		}

		$cuerpo = json_decode( wp_remote_retrieve_body( $respuesta ), true );

		if ( empty( $cuerpo['success'] ) ) {
			return new WP_Error(
				'turnstile',
				__( 'No se pudo comprobar que eres una persona. Recarga la página e inténtalo de nuevo.', 'navidad-tvs' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Describe la próxima apertura de la ventana, para el aviso de fuera de
	 * horario.
	 *
	 * @return array Con 'abierta', 'mensaje' y 'horario'.
	 */
	public function estado_ventana() {
		$abierta = NavidadTVS_Plugin::ventana_abierta();
		$inicio  = (string) $this->settings->get( 'hora_inicio' );
		$fin     = (string) $this->settings->get( 'hora_fin' );

		$horario = sprintf(
			/* translators: 1: hora de inicio, 2: hora de fin */
			__( 'de %1$s a %2$s', 'navidad-tvs' ),
			$this->hora_legible( $inicio ),
			$this->hora_legible( $fin )
		);

		if ( $abierta ) {
			return array(
				'abierta' => true,
				'mensaje' => '',
				'horario' => $horario,
			);
		}

		if ( $this->settings->get( 'concurso_congelado' ) ) {
			return array(
				'abierta' => false,
				'mensaje' => __( 'El concurso está cerrado por el momento.', 'navidad-tvs' ),
				'horario' => $horario,
			);
		}

		return array(
			'abierta' => false,
			'mensaje' => $this->mensaje_proxima_apertura( $inicio ),
			'horario' => $horario,
		);
	}

	/**
	 * Arma el aviso de cuándo vuelve a abrir.
	 *
	 * @param string $hora_inicio Hora de apertura, formato H:i.
	 * @return string
	 */
	private function mensaje_proxima_apertura( $hora_inicio ) {
		$ahora  = NavidadTVS_Plugin::ahora();
		$dias   = (array) $this->settings->get( 'dias_habiles' );
		$inicio = $hora_inicio;

		// Hoy es día hábil y todavía no abre.
		if ( in_array( (int) $ahora->format( 'N' ), $dias, true ) && $ahora->format( 'H:i' ) < $inicio ) {
			return sprintf(
				/* translators: %s: hora de apertura */
				__( 'La jornada de hoy abre a las %s', 'navidad-tvs' ),
				$this->hora_legible( $inicio )
			);
		}

		// Buscar el próximo día hábil, mirando como mucho una semana adelante.
		for ( $i = 1; $i <= 7; $i++ ) {
			$candidato = $ahora->modify( "+{$i} day" );

			if ( in_array( (int) $candidato->format( 'N' ), $dias, true ) ) {
				return sprintf(
					/* translators: 1: nombre del día, 2: hora de apertura */
					__( 'La próxima jornada es el %1$s a las %2$s', 'navidad-tvs' ),
					$this->nombre_dia( (int) $candidato->format( 'N' ) ),
					$this->hora_legible( $inicio )
				);
			}
		}

		return __( 'Consulta los términos y condiciones para conocer las fechas de participación.', 'navidad-tvs' );
	}

	/**
	 * Pasa una hora H:i a formato de 12 horas.
	 *
	 * @param string $hora Formato H:i.
	 * @return string
	 */
	private function hora_legible( $hora ) {
		// Devuelve "1:30 p. m." con su punto final. Quien la use al cerrar una
		// frase no debe agregar otro: en español la abreviatura lo absorbe.
		$partes = explode( ':', (string) $hora );
		$h      = isset( $partes[0] ) ? (int) $partes[0] : 0;
		$m      = isset( $partes[1] ) ? (int) $partes[1] : 0;

		$sufijo = $h >= 12 ? 'p. m.' : 'a. m.';
		$h12    = $h % 12;
		$h12    = 0 === $h12 ? 12 : $h12;

		return 0 === $m
			? sprintf( '%d:00 %s', $h12, $sufijo )
			: sprintf( '%d:%02d %s', $h12, $m, $sufijo );
	}

	/**
	 * Nombre del día de la semana.
	 *
	 * @param int $n 1 (lunes) a 7 (domingo).
	 * @return string
	 */
	private function nombre_dia( $n ) {
		$nombres = array(
			1 => __( 'lunes', 'navidad-tvs' ),
			2 => __( 'martes', 'navidad-tvs' ),
			3 => __( 'miércoles', 'navidad-tvs' ),
			4 => __( 'jueves', 'navidad-tvs' ),
			5 => __( 'viernes', 'navidad-tvs' ),
			6 => __( 'sábado', 'navidad-tvs' ),
			7 => __( 'domingo', 'navidad-tvs' ),
		);

		return isset( $nombres[ $n ] ) ? $nombres[ $n ] : '';
	}
}
