<?php
/**
 * Edición de participantes del padrón.
 *
 * Existe porque el padrón llega de un archivo y los archivos traen erratas:
 * un teléfono mal digitado, una placa cambiada o, lo más común, la fecha de
 * jornada equivocada. Sin esta pantalla la única salida era reimportar todo o
 * entrar a phpMyAdmin, y ninguna de las dos se le puede pedir a quien atiende
 * el concurso.
 *
 * No busca por nombre a propósito: el padrón no lo trae. El nombre lo digita
 * el participante al entrar y es solo informativo; quien identifica a una
 * persona aquí es el teléfono, la cédula o la placa.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de búsqueda y edición del padrón.
 */
class NavidadTVS_Participantes {

	/**
	 * Acceso a la base.
	 *
	 * @var NavidadTVS_Database
	 */
	private $database;

	/**
	 * Columnas que esta pantalla deja cambiar.
	 *
	 * Fuera quedan `id` e `importado_en`, que no son datos del participante
	 * sino del registro, y `marca`, porque el concurso es solo para TVS y
	 * cambiarla a mano habilitaría a alguien que no compró la moto.
	 *
	 * @var string[]
	 */
	private const CAMPOS = array(
		'telefono',
		'cedula',
		'placa',
		'fecha_concurso',
		'estado',
		'ciudad_propietario',
		'departamento_propietario',
		'razon_social_establecimiento',
	);

	/**
	 * Constructor.
	 *
	 * @param NavidadTVS_Database $database Acceso a la base.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Engancha el guardado.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_action( 'admin_post_navidad_tvs_guardar_participante', array( $this, 'guardar' ) );
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'navidad-tvs' ) );
		}

		$database = $this->database;

		/*
		 * La búsqueda es de solo lectura, así que va por GET y sin nonce; lo
		 * que la protege es la comprobación de capacidad de arriba. El nonce
		 * está donde se escribe, que es el formulario de guardar.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recurring
		$busqueda = isset( $_GET['buscar'] ) ? sanitize_text_field( wp_unslash( $_GET['buscar'] ) ) : '';
		$editar   = isset( $_GET['editar'] ) ? (int) $_GET['editar'] : 0;
		$aviso    = isset( $_GET['aviso'] ) ? sanitize_text_field( wp_unslash( $_GET['aviso'] ) ) : '';
		$detalle  = isset( $_GET['detalle'] ) ? sanitize_text_field( wp_unslash( $_GET['detalle'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recurring

		$resultados  = '' !== $busqueda ? $database->buscar_participantes( $busqueda ) : array();
		$participante = $editar > 0 ? $database->buscar_participante( $editar ) : null;

		$intento = null;

		if ( $participante ) {
			$intento = $this->estado_intento( (int) $participante['id'] );
		}

		include NAVIDAD_TVS_PATH . 'includes/admin/templates/participantes.php';
	}

	/**
	 * Resume si el participante todavía puede jugar y, si no, por qué.
	 *
	 * Espejo de las comprobaciones de NavidadTVS_Acceso::validar(). Se repite
	 * aquí a propósito: cambiar la fecha no sirve de nada si la persona ya
	 * gastó el intento, y sin esto el operador cambiaría la fecha, vería que
	 * sigue sin poder entrar y no sabría por qué.
	 *
	 * @param int $id Id del participante.
	 * @return array Con 'puede', 'motivos' y los datos sueltos.
	 */
	private function estado_intento( $id ) {
		$participante = $this->database->buscar_participante( $id );

		if ( ! $participante ) {
			return array(
				'puede'   => false,
				'motivos' => array( __( 'El participante ya no está en el padrón.', 'navidad-tvs' ) ),
			);
		}

		$motivos = array();

		if ( strtoupper( $participante['marca'] ) !== NAVIDAD_TVS_MARCA ) {
			$motivos[] = sprintf(
				/* translators: %s: marca de la moto */
				__( 'La marca es %s y el concurso es solo para TVS.', 'navidad-tvs' ),
				$participante['marca']
			);
		}

		if ( $participante['fecha_concurso'] !== NavidadTVS_Plugin::hoy() ) {
			$motivos[] = sprintf(
				/* translators: 1: fecha de la jornada, 2: fecha de hoy */
				__( 'Su jornada es el %1$s y hoy es %2$s. Solo puede jugar el día que le toca.', 'navidad-tvs' ),
				$participante['fecha_concurso'],
				NavidadTVS_Plugin::hoy()
			);
		}

		if ( 'descalificado' === $participante['estado'] ) {
			$motivos[] = __( 'Está descalificado.', 'navidad-tvs' );
		}

		$ya_jugo = $this->database->tiene_score( $id ) || $this->database->tiene_sesion_consumida( $id );

		if ( $ya_jugo ) {
			$motivos[] = __( 'Ya gastó su intento. Esto NO se arregla desde aquí: cambiar la fecha no le devuelve el turno.', 'navidad-tvs' );
		}

		return array(
			'puede'   => empty( $motivos ),
			'motivos' => $motivos,
			'ya_jugo' => $ya_jugo,
		);
	}

	/**
	 * Guarda los cambios de un participante.
	 *
	 * @return void
	 */
	public function guardar() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para editar el padrón.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_guardar_participante' );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			$this->volver( $id, '', 'error', __( 'Falta el participante.', 'navidad-tvs' ) );
		}

		$datos = array();

		foreach ( self::CAMPOS as $campo ) {
			if ( ! isset( $_POST[ $campo ] ) ) {
				continue;
			}

			$valor = sanitize_text_field( wp_unslash( $_POST[ $campo ] ) );
			$valor = $this->sanear( $campo, $valor );

			if ( is_wp_error( $valor ) ) {
				$this->volver( $id, '', 'error', $valor->get_error_message() );
			}

			$datos[ $campo ] = $valor;
		}

		$resultado = $this->database->actualizar_participante( $id, $datos );

		if ( is_wp_error( $resultado ) ) {
			$this->volver( $id, '', 'error', $resultado->get_error_message() );
		}

		$busqueda = isset( $_POST['buscar'] ) ? sanitize_text_field( wp_unslash( $_POST['buscar'] ) ) : '';

		$this->volver( $id, $busqueda, 'guardado', '' );
	}

	/**
	 * Valida y normaliza un campo.
	 *
	 * Las reglas son las mismas del importador, y por el mismo motivo: un dato
	 * que entra por aquí tiene que quedar igual que si hubiera entrado por el
	 * CSV, o la comparación con lo que digita el participante falla.
	 *
	 * @param string $campo Nombre de la columna.
	 * @param string $valor Valor recibido.
	 * @return string|WP_Error
	 */
	private function sanear( $campo, $valor ) {
		switch ( $campo ) {
			case 'telefono':
				$normalizado = NavidadTVS_Plugin::normalizar_telefono( $valor );

				if ( ! preg_match( '/^3\d{9}$/', $normalizado ) ) {
					return new WP_Error(
						'telefono',
						__( 'El teléfono tiene que ser un celular colombiano de diez dígitos que empiece por 3.', 'navidad-tvs' )
					);
				}

				return $normalizado;

			case 'cedula':
				$limpio = preg_replace( '/\D+/', '', $valor );

				if ( strlen( $limpio ) < 5 || strlen( $limpio ) > 20 ) {
					return new WP_Error( 'cedula', __( 'La cédula tiene que ser un número de entre 5 y 20 dígitos.', 'navidad-tvs' ) );
				}

				return $limpio;

			case 'placa':
				$limpio = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $valor ) );

				if ( strlen( $limpio ) < 5 || strlen( $limpio ) > 10 ) {
					return new WP_Error( 'placa', __( 'La placa tiene que tener entre 5 y 10 caracteres.', 'navidad-tvs' ) );
				}

				return $limpio;

			case 'fecha_concurso':
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valor ) ) {
					return new WP_Error( 'fecha', __( 'La fecha de la jornada va en formato AAAA-MM-DD.', 'navidad-tvs' ) );
				}

				/*
				 * checkdate porque el patrón de arriba deja pasar un 2026-02-31.
				 * Con una fecha imposible la persona no podría jugar ningún día
				 * y nadie entendería por qué.
				 */
				list( $anio, $mes, $dia ) = array_map( 'intval', explode( '-', $valor ) );

				if ( ! checkdate( $mes, $dia, $anio ) ) {
					return new WP_Error( 'fecha', __( 'Esa fecha no existe en el calendario.', 'navidad-tvs' ) );
				}

				return $valor;

			case 'estado':
				return in_array( $valor, array( 'habilitado', 'descalificado' ), true ) ? $valor : 'habilitado';

			default:
				return $valor;
		}
	}

	/**
	 * Vuelve a la pantalla con el aviso puesto.
	 *
	 * @param int    $id       Participante en edición.
	 * @param string $busqueda Búsqueda que traía el operador.
	 * @param string $aviso    'guardado' o 'error'.
	 * @param string $detalle  Mensaje cuando es error.
	 * @return void
	 */
	private function volver( $id, $busqueda, $aviso, $detalle ) {
		$args = array(
			'page'  => NavidadTVS_Admin::SLUG . '-participantes',
			'aviso' => $aviso,
		);

		if ( $id > 0 ) {
			$args['editar'] = $id;
		}

		if ( '' !== $busqueda ) {
			$args['buscar'] = $busqueda;
		}

		if ( '' !== $detalle ) {
			$args['detalle'] = $detalle;
		}

		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
