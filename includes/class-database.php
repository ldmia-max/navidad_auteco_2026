<?php
/**
 * Esquema de base de datos y migraciones.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea y migra las tablas del concurso.
 *
 * Tres tablas:
 *
 * - participantes: el padrón importado desde el CSV diario.
 * - sesiones:      cada carrera emitida, con su seed y su nonce.
 * - scores:        el resultado validado. Una fila por participante.
 */
class NavidadTVS_Database {

	/** @var string Padrón de compradores habilitados. */
	public $tabla_participantes;

	/** @var string Carreras emitidas. */
	public $tabla_sesiones;

	/** @var string Resultados validados. */
	public $tabla_scores;

	public function __construct() {
		global $wpdb;

		$this->tabla_participantes = $wpdb->prefix . 'navidad_tvs_participantes';
		$this->tabla_sesiones      = $wpdb->prefix . 'navidad_tvs_sesiones';
		$this->tabla_scores        = $wpdb->prefix . 'navidad_tvs_scores';
	}

	/**
	 * Crea las tablas y aplica las migraciones pendientes.
	 *
	 * Idempotente: se puede llamar cuantas veces haga falta.
	 *
	 * Ojo: los CREATE TABLE van SIN "IF NOT EXISTS". dbDelta() no lo soporta
	 * (su regex toma "IF" como nombre de tabla y las sentencias terminan
	 * compartiendo clave interna, así que algunas tablas se saltan en una
	 * instalación limpia). Es una trampa que ya mordió en el proyecto Trivia.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();

		dbDelta( $this->sql_participantes( $collate ) );
		dbDelta( $this->sql_sesiones( $collate ) );
		dbDelta( $this->sql_scores( $collate ) );

		$this->migrar();
	}

	/**
	 * Padrón de compradores habilitados.
	 *
	 * Cada archivo CSV es el lote de un día: fecha_concurso indica la jornada
	 * en la que esa persona puede jugar, y es la única en la que puede.
	 *
	 * telefono guarda los 10 dígitos normalizados, que es por donde entra el
	 * participante. telefono_csv conserva lo que vino en el archivo (12
	 * dígitos con indicativo) para poder rastrear el origen del dato.
	 *
	 * @param string $collate Charset y collation de WordPress.
	 * @return string
	 */
	private function sql_participantes( $collate ) {
		return "CREATE TABLE {$this->tabla_participantes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			telefono varchar(10) NOT NULL,
			telefono_csv varchar(20) NOT NULL DEFAULT '',
			cedula varchar(20) NOT NULL,
			placa varchar(10) NOT NULL,
			fecha_concurso date NOT NULL,
			marca varchar(50) NOT NULL DEFAULT 'TVS',
			fecha_matricula date DEFAULT NULL,
			fecha_acta date DEFAULT NULL,
			ciudad_propietario varchar(100) NOT NULL DEFAULT '',
			departamento_propietario varchar(100) NOT NULL DEFAULT '',
			razon_social_establecimiento varchar(191) NOT NULL DEFAULT '',
			estado varchar(20) NOT NULL DEFAULT 'habilitado',
			importado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY telefono (telefono),
			UNIQUE KEY cedula (cedula),
			UNIQUE KEY placa (placa),
			KEY fecha_concurso (fecha_concurso),
			KEY estado (estado)
		) {$collate};";
	}

	/**
	 * Carreras emitidas.
	 *
	 * Se crea una fila al pulsar "Iniciar carrera". El nonce es de un solo uso
	 * y el seed es lo que determina la pista: sin ellos no se puede validar
	 * nada después.
	 *
	 * @param string $collate Charset y collation de WordPress.
	 * @return string
	 */
	private function sql_sesiones( $collate ) {
		return "CREATE TABLE {$this->tabla_sesiones} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			participante_id bigint(20) unsigned NOT NULL,
			nombre_digitado varchar(120) NOT NULL DEFAULT '',
			seed bigint(20) unsigned NOT NULL,
			nonce char(64) NOT NULL,
			estado varchar(20) NOT NULL DEFAULT 'emitida',
			acepto_terminos tinyint(1) NOT NULL DEFAULT 0,
			terminos_version varchar(20) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			creada_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			consumida_en datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY nonce (nonce),
			KEY participante_id (participante_id),
			KEY estado (estado),
			KEY creada_en (creada_en)
		) {$collate};";
	}

	/**
	 * Resultados validados por el servidor.
	 *
	 * participante_id es UNIQUE: un solo intento por persona, garantizado por
	 * la base de datos y no solo por la lógica de la aplicación.
	 *
	 * Los datos del participante (cédula, teléfono, ciudad, departamento) se
	 * copian aquí al registrar el score. Así el ranking y el acta de ganadores
	 * sobreviven aunque el padrón se purgue o se reimporte. Es la misma
	 * lección del proyecto Trivia con sus columnas user_*.
	 *
	 * inputs guarda el log de entradas comprimido en base64, que es lo que
	 * permite reejecutar la carrera y auditarla meses después.
	 *
	 * @param string $collate Charset y collation de WordPress.
	 * @return string
	 */
	private function sql_scores( $collate ) {
		return "CREATE TABLE {$this->tabla_scores} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			participante_id bigint(20) unsigned NOT NULL,
			sesion_id bigint(20) unsigned NOT NULL,
			nombre varchar(120) NOT NULL DEFAULT '',
			cedula varchar(20) NOT NULL DEFAULT '',
			telefono varchar(10) NOT NULL DEFAULT '',
			ciudad_propietario varchar(100) NOT NULL DEFAULT '',
			departamento_propietario varchar(100) NOT NULL DEFAULT '',
			fecha_concurso date NOT NULL,
			distancia_m int(10) unsigned NOT NULL DEFAULT 0,
			distancia_base_m int(10) unsigned NOT NULL DEFAULT 0,
			items_recogidos smallint(5) unsigned NOT NULL DEFAULT 0,
			seed bigint(20) unsigned NOT NULL,
			inputs longtext NOT NULL,
			valido tinyint(1) NOT NULL DEFAULT 1,
			motivo_descalificacion varchar(255) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY participante_id (participante_id),
			KEY sesion_id (sesion_id),
			KEY ranking (fecha_concurso,valido,distancia_m),
			KEY creado_en (creado_en)
		) {$collate};";
	}

	/**
	 * Migraciones de esquema.
	 *
	 * Cada cambio va en un método migrar_* idempotente que chequea con
	 * SHOW COLUMNS o SHOW TABLES antes de tocar nada, y se llama desde aquí.
	 * Después hay que subir NAVIDAD_TVS_VERSION para que maybe_upgrade() lo
	 * dispare en instalaciones existentes.
	 *
	 * @return void
	 */
	private function migrar() {
		// Todavía no hay migraciones: el esquema es el inicial.
	}

	/**
	 * Comprueba si existe una columna en una tabla.
	 *
	 * @param string $tabla   Nombre completo de la tabla.
	 * @param string $columna Nombre de la columna.
	 * @return bool
	 */
	protected function tiene_columna( $tabla, $columna ) {
		global $wpdb;

		$encontrada = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$tabla} LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$columna
			)
		);

		return ! empty( $encontrada );
	}

	/**
	 * Comprueba si existe una tabla.
	 *
	 * @param string $tabla Nombre completo de la tabla.
	 * @return bool
	 */
	public function tiene_tabla( $tabla ) {
		global $wpdb;

		$encontrada = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla )
		);

		return $encontrada === $tabla;
	}

	/**
	 * Devuelve las tres tablas y si existen. Útil para el panel de estado.
	 *
	 * @return array<string, bool>
	 */
	public function estado_tablas() {
		return array(
			$this->tabla_participantes => $this->tiene_tabla( $this->tabla_participantes ),
			$this->tabla_sesiones      => $this->tiene_tabla( $this->tabla_sesiones ),
			$this->tabla_scores        => $this->tiene_tabla( $this->tabla_scores ),
		);
	}

	/**
	 * Cuenta participantes del padrón, opcionalmente de una jornada.
	 *
	 * @param string $fecha Fecha Y-m-d, o cadena vacía para todas.
	 * @return int
	 */
	public function contar_participantes( $fecha = '' ) {
		global $wpdb;

		if ( '' === $fecha ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_participantes}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tabla_participantes} WHERE fecha_concurso = %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$fecha
			)
		);
	}

	/**
	 * Cuenta scores válidos, opcionalmente de una jornada.
	 *
	 * @param string $fecha Fecha Y-m-d, o cadena vacía para todas.
	 * @return int
	 */
	public function contar_scores( $fecha = '' ) {
		global $wpdb;

		if ( '' === $fecha ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_scores} WHERE valido = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tabla_scores} WHERE valido = 1 AND fecha_concurso = %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$fecha
			)
		);
	}
}
