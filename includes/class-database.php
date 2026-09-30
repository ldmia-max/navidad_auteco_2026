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

	/** @var string Jornadas de revancha, generales o de una sola persona. */
	public $tabla_revanchas;

	public function __construct() {
		global $wpdb;

		$this->tabla_participantes = $wpdb->prefix . 'navidad_tvs_participantes';
		$this->tabla_sesiones      = $wpdb->prefix . 'navidad_tvs_sesiones';
		$this->tabla_scores        = $wpdb->prefix . 'navidad_tvs_scores';
		$this->tabla_revanchas     = $wpdb->prefix . 'navidad_tvs_revanchas';
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

		/*
		 * El cambio de clave única va ANTES de dbDelta y no con el resto de
		 * migraciones.
		 *
		 * En una instalación que viene de antes, scores todavía tiene
		 * UNIQUE(participante_id). dbDelta ve que el esquema nuevo pide un
		 * KEY normal con ese mismo nombre, intenta crearlo y choca con el que
		 * ya está: "Duplicate key name". El resultado final acababa siendo
		 * correcto porque la migración lo arreglaba después, pero dejaba un
		 * error de base de datos en el log de cada actualización, y un error
		 * que se puede evitar no debe estar ahí: el día que aparezca uno de
		 * verdad, nadie lo va a distinguir del ruido.
		 */
		if ( $this->tiene_tabla( $this->tabla_scores ) ) {
			$this->migrar_intento_por_jornada();
		}

		dbDelta( $this->sql_scores( $collate ) );
		dbDelta( $this->sql_revanchas( $collate ) );

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
			fecha_concurso date NOT NULL,
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
			KEY participante_jornada (participante_id,fecha_concurso,estado),
			KEY estado (estado),
			KEY creada_en (creada_en)
		) {$collate};";
	}

	/**
	 * Resultados validados por el servidor.
	 *
	 * La clave única es (participante_id, fecha_concurso): un solo intento por
	 * persona Y POR JORNADA, garantizado por la base y no solo por la lógica.
	 *
	 * Era UNIQUE(participante_id) a secas, un intento en toda la campaña. Lo
	 * cambiaron las revanchas: quien no jugó o no ganó puede volver en otra
	 * fecha. Lo que no cambia es que en una misma jornada nadie juega dos
	 * veces, y eso lo sigue sosteniendo la base de datos.
	 *
	 * ganador se marca a mano desde el panel. No se deduce de la distancia
	 * porque el reglamento premia a los cuatro primeros Y a todos los
	 * empatados con el cuarto, y porque el organizador puede tener motivos
	 * para dejar a alguien fuera; que quede escrito quién ganó, y no
	 * calculado al vuelo, es lo que permite defender el acta después.
	 *
	 * Los datos del participante (cédula, teléfono, ciudad, departamento) se
	 * copian aquí al registrar el score. Así el ranking y el acta de ganadores
	 * sobreviven aunque el padrón se purgue o se reimporte. Es la misma
	 * lección del proyecto Trivia con sus columnas user_*.
	 *
	 * inputs guarda el log de entradas comprimido en base64, que es lo que
	 * permite reejecutar la carrera y auditarla meses después.
	 *
	 * distancia_m la calcula SIEMPRE el servidor reejecutando el log.
	 * distancia_cliente_m es lo que dijo el navegador, y se guarda solo para
	 * auditar: si las dos no coinciden, o alguien tocó el JavaScript o está
	 * corriendo una versión vieja del bundle. No decide nada.
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
			distancia_cliente_m int(10) unsigned NOT NULL DEFAULT 0,
			items_recogidos smallint(5) unsigned NOT NULL DEFAULT 0,
			impulsores smallint(5) unsigned NOT NULL DEFAULT 0,
			caidas smallint(5) unsigned NOT NULL DEFAULT 0,
			sobrecalentamientos smallint(5) unsigned NOT NULL DEFAULT 0,
			duracion_s int(10) unsigned NOT NULL DEFAULT 0,
			seed bigint(20) unsigned NOT NULL,
			inputs longtext NOT NULL,
			valido tinyint(1) NOT NULL DEFAULT 1,
			motivo_descalificacion varchar(255) NOT NULL DEFAULT '',
			ganador tinyint(1) NOT NULL DEFAULT 0,
			ganador_en datetime DEFAULT NULL,
			ganador_nota varchar(255) NOT NULL DEFAULT '',
			ip varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			creado_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY participante_jornada (participante_id,fecha_concurso),
			KEY participante_id (participante_id),
			KEY sesion_id (sesion_id),
			KEY ranking (fecha_concurso,valido,distancia_m),
			KEY ganadores (fecha_concurso,ganador),
			KEY creado_en (creado_en)
		) {$collate};";
	}

	/**
	 * Jornadas de revancha.
	 *
	 * Una revancha es una fecha extra en la que alguien puede volver a jugar.
	 * Hay dos clases y se distinguen por participante_id:
	 *
	 * - GENERAL (participante_id NULL): ese día vuelve a jugar todo el que no
	 *   haya ganado todavía. Es la segunda oportunidad de campaña.
	 * - INDIVIDUAL (participante_id con valor): ese día vuelve a jugar una
	 *   sola persona. Existe porque en una campaña anterior hubo un derecho de
	 *   petición, y hace falta poder atender un reclamo concreto sin abrirle
	 *   la puerta a todo el mundo.
	 *
	 * El motivo es obligatorio en las individuales y por eso hay una columna:
	 * devolver un intento a una persona concreta es una decisión que alguien
	 * va a tener que explicar, y sin el motivo escrito no se puede.
	 *
	 * La clave única deja una sola revancha por fecha y persona. Para las
	 * generales el participante va a 0 y no a NULL, porque en MySQL dos NULL
	 * no chocan entre sí y se podrían crear cien revanchas generales del mismo
	 * día sin que la base dijera nada.
	 *
	 * @param string $collate Charset y collation de WordPress.
	 * @return string
	 */
	private function sql_revanchas( $collate ) {
		return "CREATE TABLE {$this->tabla_revanchas} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			participante_id bigint(20) unsigned NOT NULL DEFAULT 0,
			fecha date NOT NULL,
			motivo varchar(255) NOT NULL DEFAULT '',
			creada_por bigint(20) unsigned NOT NULL DEFAULT 0,
			creada_en datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY persona_fecha (participante_id,fecha),
			KEY fecha (fecha)
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
		global $wpdb;

		/*
		 * E6 añadió columnas de auditoría a scores. dbDelta() ya las crea en
		 * una instalación limpia, pero en una que venga de una versión
		 * anterior hay que agregarlas a mano: dbDelta compara el esquema y a
		 * veces no acierta con los ALTER, y prefiero no depender de eso para
		 * algo que sostiene el acta de ganadores.
		 *
		 * Idempotente: comprueba antes de tocar.
		 */
		$nuevas = array(
			'distancia_cliente_m' => "ADD COLUMN distancia_cliente_m int(10) unsigned NOT NULL DEFAULT 0 AFTER distancia_base_m",
			'impulsores'          => "ADD COLUMN impulsores smallint(5) unsigned NOT NULL DEFAULT 0 AFTER items_recogidos",
			'caidas'              => "ADD COLUMN caidas smallint(5) unsigned NOT NULL DEFAULT 0 AFTER impulsores",
			'sobrecalentamientos' => "ADD COLUMN sobrecalentamientos smallint(5) unsigned NOT NULL DEFAULT 0 AFTER caidas",
			'duracion_s'          => "ADD COLUMN duracion_s int(10) unsigned NOT NULL DEFAULT 0 AFTER sobrecalentamientos",
		);

		foreach ( $nuevas as $columna => $clausula ) {
			if ( ! $this->tiene_columna( $this->tabla_scores, $columna ) ) {
				$wpdb->query( "ALTER TABLE {$this->tabla_scores} {$clausula}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			}
		}

		$this->migrar_columnas_ganador();
		$this->migrar_jornada_en_sesiones();
	}

	/**
	 * La sesión pasa a saber a qué jornada pertenece.
	 *
	 * Antes no hacía falta: cada persona tenía un solo intento en toda la
	 * campaña, así que bastaba con preguntar si existía una sesión consumida.
	 * Con revanchas la pregunta es otra —¿consumió una HOY?— y sin esta
	 * columna habría que deducir la jornada convirtiendo consumida_en de UTC a
	 * hora de Colombia en cada consulta. La jornada es un dato del concurso,
	 * no una conversión de reloj, y se guarda como tal.
	 *
	 * A las filas que ya existen se les pone la jornada del participante, que
	 * es la que tenían por definición: antes de las revanchas no había otra.
	 *
	 * @return void
	 */
	private function migrar_jornada_en_sesiones() {
		global $wpdb;

		/*
		 * El relleno va SIEMPRE, no solo cuando falta la columna.
		 *
		 * Esto costó un rato de depuración. dbDelta crea la columna él solo,
		 * porque está en el esquema de sql_sesiones(), y la rellena con
		 * '0000-00-00'. Cuando esta migración comprobaba "¿existe la columna?"
		 * para decidir si hacer algo, se la encontraba ya creada y se iba sin
		 * copiar las fechas. Resultado: la columna estaba, vacía, y ningún
		 * participante podía entrar porque su sesión no pertenecía a ninguna
		 * jornada.
		 *
		 * El UPDATE es idempotente: solo toca las filas sin fecha, así que
		 * correrlo en cada actualización no cuesta nada y no puede pisar una
		 * jornada buena.
		 */
		if ( ! $this->tiene_columna( $this->tabla_sesiones, 'fecha_concurso' ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE {$this->tabla_sesiones}
				   ADD COLUMN fecha_concurso date NOT NULL AFTER participante_id"
			);
		}

		// A las filas de antes se les pone la jornada del participante, que es
		// la que tenían por definición: antes de las revanchas no había otra.
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			"UPDATE {$this->tabla_sesiones} s
			   JOIN {$this->tabla_participantes} p ON p.id = s.participante_id
			    SET s.fecha_concurso = p.fecha_concurso
			  WHERE s.fecha_concurso = '0000-00-00' OR s.fecha_concurso IS NULL"
		);
	}

	/**
	 * Columnas de la marca de ganador.
	 *
	 * @return void
	 */
	private function migrar_columnas_ganador() {
		global $wpdb;

		$nuevas = array(
			'ganador'      => "ADD COLUMN ganador tinyint(1) NOT NULL DEFAULT 0 AFTER motivo_descalificacion",
			'ganador_en'   => "ADD COLUMN ganador_en datetime DEFAULT NULL AFTER ganador",
			'ganador_nota' => "ADD COLUMN ganador_nota varchar(255) NOT NULL DEFAULT '' AFTER ganador_en",
		);

		foreach ( $nuevas as $columna => $clausula ) {
			if ( ! $this->tiene_columna( $this->tabla_scores, $columna ) ) {
				$wpdb->query( "ALTER TABLE {$this->tabla_scores} {$clausula}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			}
		}
	}

	/**
	 * El intento único pasa de ser por campaña a ser por jornada.
	 *
	 * Hasta las revanchas, scores tenía UNIQUE(participante_id): una persona,
	 * un intento y se acabó. Con revanchas la misma persona puede jugar otro
	 * día, así que la clave pasa a (participante_id, fecha_concurso).
	 *
	 * dbDelta NO hace esto solo. Sabe agregar índices, pero no quitar el viejo,
	 * y dejar los dos convertiría cada revancha en un error de clave duplicada
	 * justo cuando el participante pulsa "iniciar carrera". Por eso se hace a
	 * mano y comprobando antes, que es la regla de la casa para los cambios de
	 * esquema.
	 *
	 * @return void
	 */
	private function migrar_intento_por_jornada() {
		global $wpdb;

		$indices = $wpdb->get_results( "SHOW INDEX FROM {$this->tabla_scores}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

		$columnas_por_indice = array();

		foreach ( (array) $indices as $fila ) {
			$columnas_por_indice[ $fila['Key_name'] ][] = $fila['Column_name'];
		}

		// El de jornada primero: si algo fallara, mejor quedarse con los dos
		// que sin ninguno, porque sin clave única se puede colar un intento
		// doble en la misma jornada.
		if ( ! isset( $columnas_por_indice['participante_jornada'] ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE {$this->tabla_scores}
				   ADD UNIQUE KEY participante_jornada (participante_id, fecha_concurso)"
			);
		}

		$viejo = $columnas_por_indice['participante_id'] ?? array();

		// Solo se quita si es el índice ÚNICO de una sola columna. El índice
		// normal del mismo nombre que declara el esquema nuevo tiene que
		// quedarse.
		if ( array( 'participante_id' ) === $viejo ) {
			$unico = false;

			foreach ( (array) $indices as $fila ) {
				if ( 'participante_id' === $fila['Key_name'] && '0' === (string) $fila['Non_unique'] ) {
					$unico = true;
				}
			}

			if ( $unico ) {
				$wpdb->query( "ALTER TABLE {$this->tabla_scores} DROP INDEX participante_id" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "ALTER TABLE {$this->tabla_scores} ADD KEY participante_id (participante_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			}
		}
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
	 * Busca qué teléfonos, cédulas y placas ya están en el padrón.
	 *
	 * Tres consultas por lote en vez de tres por fila: con 500 filas, eso son
	 * 1500 consultas que no se hacen.
	 *
	 * @param string[] $telefonos Teléfonos normalizados a buscar.
	 * @param string[] $cedulas   Cédulas normalizadas a buscar.
	 * @param string[] $placas    Placas normalizadas a buscar.
	 * @return array{telefono: array<string,string>, cedula: array<string,string>, placa: array<string,string>}
	 *         Para cada campo, un mapa valor => fecha_concurso en la que ya está registrado.
	 */
	public function buscar_existentes( $telefonos, $cedulas, $placas ) {
		return array(
			'telefono' => $this->buscar_columna( 'telefono', $telefonos ),
			'cedula'   => $this->buscar_columna( 'cedula', $cedulas ),
			'placa'    => $this->buscar_columna( 'placa', $placas ),
		);
	}

	/**
	 * Devuelve los valores de una columna que ya existen en el padrón.
	 *
	 * @param string   $columna Nombre de columna. Solo se aceptan las tres
	 *                          columnas únicas del padrón.
	 * @param string[] $valores Valores a buscar.
	 * @return array<string, string> Mapa valor => fecha_concurso.
	 */
	private function buscar_columna( $columna, $valores ) {
		global $wpdb;

		// Lista blanca: la columna se interpola en el SQL, así que no puede
		// venir de ningún lado que no sea este archivo.
		if ( ! in_array( $columna, array( 'telefono', 'cedula', 'placa' ), true ) ) {
			return array();
		}

		$valores = array_values( array_unique( array_filter( (array) $valores ) ) );

		if ( empty( $valores ) ) {
			return array();
		}

		$encontrados = array();

		// Se trocea para no armar una sentencia gigante con archivos grandes.
		foreach ( array_chunk( $valores, 500 ) as $lote ) {
			$huecos = implode( ',', array_fill( 0, count( $lote ), '%s' ) );

			$sql = $wpdb->prepare(
				"SELECT {$columna} AS valor, fecha_concurso FROM {$this->tabla_participantes} WHERE {$columna} IN ({$huecos})", // phpcs:ignore WordPress.DB.PreparedSQL
				$lote
			);

			$filas = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

			foreach ( (array) $filas as $fila ) {
				$encontrados[ $fila['valor'] ] = $fila['fecha_concurso'];
			}
		}

		return $encontrados;
	}

	/**
	 * Inserta filas del padrón en lotes.
	 *
	 * Usa INSERT IGNORE: si dos peticiones simultáneas intentaran insertar el
	 * mismo teléfono, los índices únicos de la tabla frenan la segunda sin
	 * abortar el lote entero.
	 *
	 * @param array $filas  Filas ya validadas por el importador.
	 * @param int   $tamano Filas por sentencia.
	 * @return int Cantidad de filas efectivamente insertadas.
	 */
	public function insertar_participantes( $filas, $tamano = 200 ) {
		global $wpdb;

		if ( empty( $filas ) ) {
			return 0;
		}

		$columnas = array(
			'telefono',
			'telefono_csv',
			'cedula',
			'placa',
			'fecha_concurso',
			'marca',
			'fecha_matricula',
			'fecha_acta',
			'ciudad_propietario',
			'departamento_propietario',
			'razon_social_establecimiento',
		);

		$lista_columnas = '`' . implode( '`,`', $columnas ) . '`';
		$insertadas     = 0;

		foreach ( array_chunk( $filas, max( 1, (int) $tamano ) ) as $lote ) {
			$grupos = array();
			$datos  = array();

			foreach ( $lote as $fila ) {
				$marcadores = array();

				foreach ( $columnas as $columna ) {
					$valor = isset( $fila[ $columna ] ) ? $fila[ $columna ] : '';

					// Las fechas opcionales vacías van como NULL, no como
					// '0000-00-00', que MySQL en modo estricto rechaza.
					if ( in_array( $columna, array( 'fecha_matricula', 'fecha_acta' ), true ) && '' === $valor ) {
						$marcadores[] = 'NULL';
						continue;
					}

					$marcadores[] = '%s';
					$datos[]      = $valor;
				}

				$grupos[] = '(' . implode( ',', $marcadores ) . ')';
			}

			$sql = "INSERT IGNORE INTO {$this->tabla_participantes} ({$lista_columnas}) VALUES " . implode( ',', $grupos );

			$resultado = $wpdb->query( $wpdb->prepare( $sql, $datos ) ); // phpcs:ignore WordPress.DB.PreparedSQL

			if ( false !== $resultado ) {
				$insertadas += (int) $resultado;
			}
		}

		return $insertadas;
	}

	// ---------------------------------------------------------------------
	// Acceso al juego
	// ---------------------------------------------------------------------

	/**
	 * Busca un participante por su teléfono ya normalizado.
	 *
	 * @param string $telefono Diez dígitos.
	 * @return array|null Fila del padrón, o null si no existe.
	 */
	public function buscar_participante_por_telefono( $telefono ) {
		global $wpdb;

		if ( '' === $telefono ) {
			return null;
		}

		$fila = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_participantes} WHERE telefono = %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$telefono
			),
			ARRAY_A
		);

		return $fila ? $fila : null;
	}

	/**
	 * Busca participantes por teléfono, cédula o placa.
	 *
	 * Para la pantalla de edición del panel. No busca por nombre porque el
	 * padrón no trae nombre: el participante lo digita al entrar y no se
	 * guarda en esta tabla.
	 *
	 * El teléfono se prueba en sus dos formas. El operador puede tener a mano
	 * el número de doce dígitos del archivo o el de diez que digita la
	 * persona, y buscar el que no es devolvía "no encontrado" sobre alguien
	 * que sí estaba.
	 *
	 * @param string $texto  Lo que escribió el operador.
	 * @param int    $limite Máximo de filas.
	 * @return array Filas del padrón.
	 */
	public function buscar_participantes( $texto, $limite = 50 ) {
		global $wpdb;

		$texto = trim( (string) $texto );

		if ( '' === $texto ) {
			return array();
		}

		$limite   = max( 1, min( 200, (int) $limite ) );
		$telefono = NavidadTVS_Plugin::normalizar_telefono( $texto );
		$suelto   = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $texto ) );

		if ( '' === $suelto ) {
			return array();
		}

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_participantes}
				  WHERE telefono = %s
				     OR telefono_csv = %s
				     OR cedula = %s
				     OR placa = %s
				  ORDER BY fecha_concurso DESC, id DESC
				  LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$telefono,
				$suelto,
				$suelto,
				$suelto,
				$limite
			),
			ARRAY_A
		);

		return $filas ? $filas : array();
	}

	/**
	 * Actualiza los datos de un participante.
	 *
	 * Solo toca las columnas que se le pasan. Devuelve un WP_Error cuando el
	 * cambio chocaría con otra fila: teléfono, cédula y placa son claves
	 * únicas, y sin esta comprobación el UPDATE fallaría con un error de MySQL
	 * que el operador no puede interpretar.
	 *
	 * @param int   $id     Id del participante.
	 * @param array $datos  Columnas a cambiar.
	 * @return true|WP_Error
	 */
	public function actualizar_participante( $id, array $datos ) {
		global $wpdb;

		$id = (int) $id;

		if ( $id <= 0 || empty( $datos ) ) {
			return new WP_Error( 'sin_cambios', __( 'No hay nada que guardar.', 'navidad-tvs' ) );
		}

		$actual = $this->buscar_participante( $id );

		if ( ! $actual ) {
			return new WP_Error( 'no_existe', __( 'Ese participante ya no está en el padrón.', 'navidad-tvs' ) );
		}

		/*
		 * Choques con las claves únicas, comprobados antes de escribir. Se
		 * mira fila por fila para poder decir CUÁL de los tres campos choca y
		 * con qué jornada, que es lo que el operador necesita saber para
		 * decidir.
		 */
		foreach ( array( 'telefono', 'cedula', 'placa' ) as $campo ) {
			if ( ! isset( $datos[ $campo ] ) || $datos[ $campo ] === $actual[ $campo ] ) {
				continue;
			}

			$choque = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, fecha_concurso FROM {$this->tabla_participantes}
					  WHERE {$campo} = %s AND id <> %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
					$datos[ $campo ],
					$id
				),
				ARRAY_A
			);

			if ( $choque ) {
				return new WP_Error(
					'duplicado',
					sprintf(
						/* translators: 1: campo, 2: valor, 3: fecha de la otra jornada */
						__( 'Ya hay otro participante con ese %1$s (%2$s), en la jornada del %3$s.', 'navidad-tvs' ),
						$campo,
						$datos[ $campo ],
						$choque['fecha_concurso']
					)
				);
			}
		}

		$formatos = array_fill( 0, count( $datos ), '%s' );

		$resultado = $wpdb->update( $this->tabla_participantes, $datos, array( 'id' => $id ), $formatos, array( '%d' ) );

		if ( false === $resultado ) {
			return new WP_Error(
				'fallo_bd',
				__( 'La base de datos rechazó el cambio. Revisa el registro de errores.', 'navidad-tvs' )
			);
		}

		return true;
	}

	/**
	 * Indica si un participante ya tiene un resultado registrado.
	 *
	 * Incluye los descalificados a propósito: haber sido descalificado no
	 * devuelve el derecho a otro intento.
	 *
	 * @param int $participante_id Id del participante.
	 * @return bool
	 */
	public function tiene_score( $participante_id ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_scores} WHERE participante_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$participante_id
			)
		);

		return $id > 0;
	}

	/**
	 * Indica si el participante ya arrancó su carrera alguna vez.
	 *
	 * Una sesión consumida significa que la carrera empezó. Da igual si llegó
	 * a enviarse un resultado: el intento está gastado.
	 *
	 * @param int $participante_id Id del participante.
	 * @return bool
	 */
	public function tiene_sesion_consumida( $participante_id ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_sesiones} WHERE participante_id = %d AND estado = 'consumida' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				$participante_id
			)
		);

		return $id > 0;
	}

	/**
	 * Devuelve la sesión emitida y todavía vigente de un participante.
	 *
	 * Permite que quien recargue la página antes de arrancar la carrera
	 * retome su sesión en vez de generar una nueva cada vez.
	 *
	 * Se acota a la jornada además de a los minutos. El plazo de vigencia ya
	 * impediría reutilizar la de otro día, pero dejarlo implícito es pedir que
	 * alguien suba ese plazo un día y reviva sin querer la sesión de una
	 * jornada anterior.
	 *
	 * @param int    $participante_id Id del participante.
	 * @param int    $minutos         Vigencia en minutos.
	 * @param string $jornada         Jornada Y-m-d.
	 * @return array|null
	 */
	public function sesion_vigente_de( $participante_id, $minutos, $jornada ) {
		global $wpdb;

		$fila = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_sesiones}
				  WHERE participante_id = %d
				    AND fecha_concurso = %s
				    AND estado = 'emitida'
				    AND creada_en > ( UTC_TIMESTAMP() - INTERVAL %d MINUTE )
				  ORDER BY id DESC
				  LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				$participante_id,
				$jornada,
				$minutos
			),
			ARRAY_A
		);

		return $fila ? $fila : null;
	}

	/**
	 * Crea una sesión de carrera.
	 *
	 * @param array $datos Campos de la sesión.
	 * @return int|false Id de la sesión, o false si falló el INSERT.
	 */
	public function crear_sesion( array $datos ) {
		global $wpdb;

		/*
		 * Sin jornada, la sesión nace rota.
		 *
		 * Una sesión con fecha '0000-00-00' no la encuentra ninguna de las
		 * comprobaciones por jornada, y el resultado se guardaría con esa
		 * fecha imposible: fuera de todo ranking y contando como un intento
		 * que nadie puede ver. Antes de que exista esa fila es mejor asumir
		 * que la jornada es hoy, que es lo único que puede ser.
		 */
		$jornada = isset( $datos['fecha_concurso'] ) ? (string) $datos['fecha_concurso'] : '';

		if ( '' === $jornada || '0000-00-00' === $jornada ) {
			$jornada = NavidadTVS_Plugin::hoy();
		}

		$ok = $wpdb->insert(
			$this->tabla_sesiones,
			array(
				'participante_id'  => (int) $datos['participante_id'],
				'fecha_concurso'   => $jornada,
				'nombre_digitado'  => (string) $datos['nombre_digitado'],
				'seed'             => (int) $datos['seed'],
				'nonce'            => (string) $datos['nonce'],
				'estado'           => 'emitida',
				'acepto_terminos'  => ! empty( $datos['acepto_terminos'] ) ? 1 : 0,
				'terminos_version' => (string) $datos['terminos_version'],
				'ip'               => (string) $datos['ip'],
				'user_agent'       => (string) $datos['user_agent'],
				'creada_en'        => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Busca una sesión por su nonce.
	 *
	 * @param string $nonce Token de 64 caracteres.
	 * @return array|null
	 */
	public function buscar_sesion_por_nonce( $nonce ) {
		global $wpdb;

		if ( 64 !== strlen( (string) $nonce ) ) {
			return null;
		}

		$fila = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_sesiones} WHERE nonce = %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$nonce
			),
			ARRAY_A
		);

		return $fila ? $fila : null;
	}

	/**
	 * Marca una sesión como consumida.
	 *
	 * El UPDATE lleva la condición estado = 'emitida' dentro del propio WHERE,
	 * así que si llegan dos peticiones a la vez solo una afecta una fila. Sin
	 * eso, dos pulsaciones rápidas del botón darían dos carreras.
	 *
	 * @param int $sesion_id Id de la sesión.
	 * @return bool True si esta llamada fue la que la consumió.
	 */
	public function consumir_sesion( $sesion_id ) {
		global $wpdb;

		$afectadas = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->tabla_sesiones}
				    SET estado = 'consumida', consumida_en = %s
				  WHERE id = %d AND estado = 'emitida'", // phpcs:ignore WordPress.DB.PreparedSQL
				gmdate( 'Y-m-d H:i:s' ),
				$sesion_id
			)
		);

		return 1 === (int) $afectadas;
	}

	/**
	 * Devuelve un participante por su id.
	 *
	 * @param int $id Id del participante.
	 * @return array|null
	 */
	public function buscar_participante( $id ) {
		global $wpdb;

		$fila = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_participantes} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$id
			),
			ARRAY_A
		);

		return $fila ? $fila : null;
	}

	/**
	 * Resumen del padrón agrupado por jornada.
	 *
	 * @param int $limite Máximo de jornadas a devolver, de la más reciente hacia atrás.
	 * @return array<int, array{fecha_concurso: string, total: int, jugaron: int}>
	 */
	public function resumen_por_jornada( $limite = 30 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT p.fecha_concurso,
			        COUNT(*) AS total,
			        SUM(CASE WHEN s.id IS NOT NULL AND s.valido = 1 THEN 1 ELSE 0 END) AS jugaron
			   FROM {$this->tabla_participantes} p
			   LEFT JOIN {$this->tabla_scores} s ON s.participante_id = p.id
			  GROUP BY p.fecha_concurso
			  ORDER BY p.fecha_concurso DESC
			  LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
			(int) $limite
		);

		$filas = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		return array_map(
			static function ( $fila ) {
				return array(
					'fecha_concurso' => $fila['fecha_concurso'],
					'total'          => (int) $fila['total'],
					'jugaron'        => (int) $fila['jugaron'],
				);
			},
			(array) $filas
		);
	}

	/**
	 * Guarda el resultado que calculó el servidor.
	 *
	 * Los datos del participante se copian aquí a propósito. El padrón se
	 * reimporta cada día y algún día se purgará; el acta de ganadores tiene
	 * que poder leerse meses después sin depender de eso.
	 *
	 * participante_id es UNIQUE, así que dos peticiones simultáneas del mismo
	 * teléfono no pueden dejar dos filas: la segunda choca contra la base de
	 * datos y devuelve false. Esa es la garantía de un solo intento, no el if
	 * de más arriba, que siempre tiene una rendija.
	 *
	 * @param array $datos Campos del score.
	 * @return int|false ID de la fila, o false si ya había una.
	 */
	public function registrar_score( array $datos ) {
		global $wpdb;

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->tabla_scores,
			array(
				'participante_id'          => (int) $datos['participante_id'],
				'sesion_id'                => (int) $datos['sesion_id'],
				'nombre'                   => (string) $datos['nombre'],
				'cedula'                   => (string) $datos['cedula'],
				'telefono'                 => (string) $datos['telefono'],
				'ciudad_propietario'       => (string) $datos['ciudad_propietario'],
				'departamento_propietario' => (string) $datos['departamento_propietario'],
				'fecha_concurso'           => (string) $datos['fecha_concurso'],
				'distancia_m'              => (int) $datos['distancia_m'],
				'distancia_base_m'         => (int) $datos['distancia_base_m'],
				'distancia_cliente_m'      => (int) $datos['distancia_cliente_m'],
				'items_recogidos'          => (int) $datos['items_recogidos'],
				'impulsores'               => (int) $datos['impulsores'],
				'caidas'                   => (int) $datos['caidas'],
				'sobrecalentamientos'      => (int) $datos['sobrecalentamientos'],
				'duracion_s'               => (int) $datos['duracion_s'],
				'seed'                     => (int) $datos['seed'],
				'inputs'                   => (string) $datos['inputs'],
				'valido'                   => empty( $datos['valido'] ) ? 0 : 1,
				'motivo_descalificacion'   => (string) $datos['motivo_descalificacion'],
				'ip'                       => (string) $datos['ip'],
				'user_agent'               => substr( (string) $datos['user_agent'], 0, 255 ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Devuelve el score de un participante, si lo tiene.
	 *
	 * @param int $participante_id ID en el padrón.
	 * @return array|null
	 */
	public function score_de( $participante_id ) {
		global $wpdb;

		$fila = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_scores} WHERE participante_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$participante_id
			),
			ARRAY_A
		);

		return $fila ? $fila : null;
	}

	/**
	 * Ranking de una jornada, de mayor a menor distancia.
	 *
	 * Los empates salen todos: el reglamento premia a los cuatro mayores y, si
	 * hay empate, a todos los empatados. Por eso no se corta en 4 aquí sino
	 * que se devuelve la lista y quien la use decide dónde está el corte.
	 *
	 * @param string $fecha  Jornada, Y-m-d.
	 * @param int    $limite Máximo de filas.
	 * @return array
	 */
	public function ranking( $fecha, $limite = 50 ) {
		global $wpdb;

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT nombre, cedula, telefono, ciudad_propietario, departamento_propietario,
				        distancia_m, items_recogidos, caidas, creado_en
				   FROM {$this->tabla_scores}
				  WHERE fecha_concurso = %s AND valido = 1
				  ORDER BY distancia_m DESC, creado_en ASC
				  LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$fecha,
				(int) $limite
			),
			ARRAY_A
		);

		return (array) $filas;
	}

	/**
	 * Ranking de un rango de fechas, con paginación.
	 *
	 * Ordena por distancia descendente y, a igualdad, por hora de registro:
	 * quien llegó antes a la misma marca queda arriba. Es un desempate que no
	 * premia ni castiga a nadie por algo que no controla.
	 *
	 * Los descalificados se pueden incluir porque el operador necesita verlos
	 * para revisar una decisión; van marcados con valido = 0 y quien pinte la
	 * tabla decide cómo mostrarlos.
	 *
	 * @param string $desde             Fecha Y-m-d, o vacío para no acotar.
	 * @param string $hasta             Fecha Y-m-d, o vacío para no acotar.
	 * @param int    $pagina            Página, base 1.
	 * @param int    $por_pagina        Filas por página.
	 * @param bool   $incluir_invalidos Si se incluyen los descalificados.
	 * @return array
	 */
	public function ranking_rango( $desde = '', $hasta = '', $pagina = 1, $por_pagina = 50, $incluir_invalidos = false ) {
		global $wpdb;

		$por_pagina = max( 1, min( 500, (int) $por_pagina ) );
		$salto      = max( 0, ( max( 1, (int) $pagina ) - 1 ) * $por_pagina );

		list( $where, $valores ) = $this->where_ranking( $desde, $hasta, $incluir_invalidos );

		$valores[] = $por_pagina;
		$valores[] = $salto;

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, participante_id, nombre, cedula, telefono,
				        ciudad_propietario, departamento_propietario,
				        fecha_concurso, distancia_m, distancia_base_m,
				        items_recogidos, impulsores, caidas, sobrecalentamientos,
				        valido, motivo_descalificacion,
				        ganador, ganador_en, ganador_nota, creado_en
				   FROM {$this->tabla_scores}
				  {$where}
				  ORDER BY distancia_m DESC, creado_en ASC
				  LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$valores
			),
			ARRAY_A
		);

		return (array) $filas;
	}

	/**
	 * Cuántas filas tiene el ranking con esos filtros.
	 *
	 * Va aparte de ranking_rango() porque la paginación necesita el total
	 * antes de saber qué página pedir.
	 *
	 * @param string $desde             Fecha Y-m-d, o vacío.
	 * @param string $hasta             Fecha Y-m-d, o vacío.
	 * @param bool   $incluir_invalidos Si se incluyen los descalificados.
	 * @return int
	 */
	public function contar_ranking_rango( $desde = '', $hasta = '', $incluir_invalidos = false ) {
		global $wpdb;

		list( $where, $valores ) = $this->where_ranking( $desde, $hasta, $incluir_invalidos );

		if ( empty( $valores ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_scores} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$this->tabla_scores} {$where}", $valores ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Arma el WHERE del ranking y sus valores.
	 *
	 * En un solo sitio para que el listado y el conteo no puedan discrepar:
	 * si filtraran distinto, la paginación prometería páginas que no existen.
	 *
	 * @param string $desde             Fecha Y-m-d, o vacío.
	 * @param string $hasta             Fecha Y-m-d, o vacío.
	 * @param bool   $incluir_invalidos Si se incluyen los descalificados.
	 * @return array{0:string,1:array}
	 */
	private function where_ranking( $desde, $hasta, $incluir_invalidos ) {
		$condiciones = array();
		$valores     = array();

		if ( ! $incluir_invalidos ) {
			$condiciones[] = 'valido = 1';
		}

		if ( '' !== $desde ) {
			$condiciones[] = 'fecha_concurso >= %s';
			$valores[]     = $desde;
		}

		if ( '' !== $hasta ) {
			$condiciones[] = 'fecha_concurso <= %s';
			$valores[]     = $hasta;
		}

		$where = empty( $condiciones ) ? '' : 'WHERE ' . implode( ' AND ', $condiciones );

		return array( $where, $valores );
	}

	/**
	 * Devuelve un score por su id, con todo lo necesario para auditarlo.
	 *
	 * @param int $id Id del score.
	 * @return array|null
	 */
	public function buscar_score( $id ) {
		global $wpdb;

		$fila = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tabla_scores} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $id
			),
			ARRAY_A
		);

		return $fila ? $fila : null;
	}

	/**
	 * Marca o desmarca un resultado como descalificado.
	 *
	 * No se borra nada. Un resultado descalificado sigue en la tabla con su
	 * log de entradas intacto: si mañana alguien reclama, hay que poder volver
	 * a mirarlo y, si la decisión estuvo mal, deshacerla.
	 *
	 * El motivo es obligatorio al descalificar. Una descalificación sin razón
	 * escrita no se puede defender delante de un participante.
	 *
	 * @param int    $id     Id del score.
	 * @param bool   $valido true rehabilita, false descalifica.
	 * @param string $motivo Motivo, obligatorio al descalificar.
	 * @return true|WP_Error
	 */
	public function marcar_score_valido( $id, $valido, $motivo = '' ) {
		global $wpdb;

		$id     = (int) $id;
		$motivo = trim( (string) $motivo );

		if ( $id <= 0 || ! $this->buscar_score( $id ) ) {
			return new WP_Error( 'no_existe', __( 'Ese resultado ya no existe.', 'navidad-tvs' ) );
		}

		if ( ! $valido && '' === $motivo ) {
			return new WP_Error( 'sin_motivo', __( 'Hay que escribir el motivo de la descalificación.', 'navidad-tvs' ) );
		}

		$ok = $wpdb->update(
			$this->tabla_scores,
			array(
				'valido'                 => $valido ? 1 : 0,
				'motivo_descalificacion' => $valido ? '' : substr( $motivo, 0, 255 ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'fallo_bd', __( 'La base de datos rechazó el cambio.', 'navidad-tvs' ) );
		}

		return true;
	}

	// ---------------------------------------------------------------------
	// Revanchas y ganadores
	// ---------------------------------------------------------------------

	/**
	 * Indica si un participante ya tiene resultado en una jornada concreta.
	 *
	 * Sustituye a tiene_score() a secas, que preguntaba por toda la campaña.
	 * Con revanchas la pregunta correcta es por jornada: haber jugado el lunes
	 * no impide jugar el sábado de revancha.
	 *
	 * @param int    $participante_id Id del participante.
	 * @param string $fecha           Jornada Y-m-d.
	 * @return bool
	 */
	public function tiene_score_en( $participante_id, $fecha ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_scores}
				  WHERE participante_id = %d AND fecha_concurso = %s", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $participante_id,
				$fecha
			)
		);

		return $id > 0;
	}

	/**
	 * Indica si ya arrancó una carrera en esa jornada.
	 *
	 * Una sesión consumida significa que la carrera empezó. Da igual si llegó
	 * a enviarse un resultado: el intento de ese día está gastado.
	 *
	 * @param int    $participante_id Id del participante.
	 * @param string $fecha           Jornada Y-m-d.
	 * @return bool
	 */
	public function tiene_sesion_consumida_en( $participante_id, $fecha ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_sesiones}
				  WHERE participante_id = %d AND fecha_concurso = %s AND estado = 'consumida'
				  LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $participante_id,
				$fecha
			)
		);

		return $id > 0;
	}

	/**
	 * Indica si el participante ya ganó algún premio en la campaña.
	 *
	 * Gobierna las revanchas generales: la segunda oportunidad es para quien
	 * no jugó o no ganó, no para quien ya se llevó una moto.
	 *
	 * @param int $participante_id Id del participante.
	 * @return bool
	 */
	public function es_ganador( $participante_id ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_scores}
				  WHERE participante_id = %d AND ganador = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $participante_id
			)
		);

		return $id > 0;
	}

	/**
	 * Indica si una fecha es jornada de revancha general.
	 *
	 * @param string $fecha Jornada Y-m-d.
	 * @return bool
	 */
	public function hay_revancha_general( $fecha ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_revanchas}
				  WHERE participante_id = 0 AND fecha = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				$fecha
			)
		);

		return $id > 0;
	}

	/**
	 * Indica si una persona concreta tiene revancha para esa fecha.
	 *
	 * @param int    $participante_id Id del participante.
	 * @param string $fecha           Jornada Y-m-d.
	 * @return bool
	 */
	public function hay_revancha_individual( $participante_id, $fecha ) {
		global $wpdb;

		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tabla_revanchas}
				  WHERE participante_id = %d AND fecha = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				(int) $participante_id,
				$fecha
			)
		);

		return $id > 0;
	}

	/**
	 * Crea una revancha.
	 *
	 * @param int    $participante_id 0 para una revancha general.
	 * @param string $fecha           Jornada Y-m-d.
	 * @param string $motivo          Por qué se concede.
	 * @param int    $usuario         Quién la concede.
	 * @return int|WP_Error Id de la revancha.
	 */
	public function crear_revancha( $participante_id, $fecha, $motivo, $usuario = 0 ) {
		global $wpdb;

		$participante_id = max( 0, (int) $participante_id );

		if ( $participante_id > 0 && ! $this->buscar_participante( $participante_id ) ) {
			return new WP_Error( 'no_existe', __( 'Ese participante no está en el padrón.', 'navidad-tvs' ) );
		}

		if ( $participante_id > 0 && '' === trim( (string) $motivo ) ) {
			return new WP_Error(
				'sin_motivo',
				__( 'Hay que escribir por qué se le devuelve el intento a esta persona.', 'navidad-tvs' )
			);
		}

		$ok = $wpdb->insert(
			$this->tabla_revanchas,
			array(
				'participante_id' => $participante_id,
				'fecha'           => $fecha,
				'motivo'          => substr( trim( (string) $motivo ), 0, 255 ),
				'creada_por'      => (int) $usuario,
				'creada_en'       => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $ok ) {
			/*
			 * El choque contra la clave única no es un error que haya que
			 * enseñar como tal: significa que esa revancha ya existía, y el
			 * resultado es el que el operador quería.
			 */
			if ( $this->hay_revancha_individual( $participante_id, $fecha ) ) {
				return new WP_Error( 'ya_existe', __( 'Esa revancha ya estaba concedida.', 'navidad-tvs' ) );
			}

			return new WP_Error( 'fallo_bd', __( 'La base de datos rechazó la revancha.', 'navidad-tvs' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Borra una revancha.
	 *
	 * @param int $id Id de la revancha.
	 * @return bool
	 */
	public function borrar_revancha( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->tabla_revanchas, array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Lista las revanchas, con los datos de la persona cuando es individual.
	 *
	 * @param int $limite Máximo de filas.
	 * @return array
	 */
	public function listar_revanchas( $limite = 200 ) {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, p.telefono, p.cedula, p.placa
				   FROM {$this->tabla_revanchas} r
				   LEFT JOIN {$this->tabla_participantes} p ON p.id = r.participante_id
				  ORDER BY r.fecha DESC, r.id DESC
				  LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
				max( 1, min( 500, (int) $limite ) )
			),
			ARRAY_A
		);
	}

	/**
	 * Marca o desmarca un resultado como ganador.
	 *
	 * @param int    $id     Id del score.
	 * @param bool   $gana   true marca, false desmarca.
	 * @param string $nota   Anotación opcional (premio, puesto…).
	 * @return true|WP_Error
	 */
	public function marcar_ganador( $id, $gana, $nota = '' ) {
		global $wpdb;

		$score = $this->buscar_score( (int) $id );

		if ( ! $score ) {
			return new WP_Error( 'no_existe', __( 'Ese resultado ya no existe.', 'navidad-tvs' ) );
		}

		/*
		 * Un resultado descalificado no puede ganar. Es la comprobación que
		 * evita el error más caro posible en esta pantalla: marcar como
		 * ganador a alguien cuya carrera ya se había anulado.
		 */
		if ( $gana && ! $score['valido'] ) {
			return new WP_Error(
				'descalificado',
				__( 'Ese resultado está descalificado y no puede ganar. Rehabilítalo primero si fue un error.', 'navidad-tvs' )
			);
		}

		$ok = $wpdb->update(
			$this->tabla_scores,
			array(
				'ganador'      => $gana ? 1 : 0,
				'ganador_en'   => $gana ? gmdate( 'Y-m-d H:i:s' ) : null,
				'ganador_nota' => $gana ? substr( trim( (string) $nota ), 0, 255 ) : '',
			),
			array( 'id' => (int) $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'fallo_bd', __( 'La base de datos rechazó el cambio.', 'navidad-tvs' ) );
		}

		return true;
	}

	/**
	 * Devuelve los ganadores, opcionalmente de un rango de fechas.
	 *
	 * @param string $desde Fecha Y-m-d, o vacío.
	 * @param string $hasta Fecha Y-m-d, o vacío.
	 * @return array
	 */
	public function ganadores( $desde = '', $hasta = '' ) {
		global $wpdb;

		$condiciones = array( 'ganador = 1' );
		$valores     = array();

		if ( '' !== $desde ) {
			$condiciones[] = 'fecha_concurso >= %s';
			$valores[]     = $desde;
		}

		if ( '' !== $hasta ) {
			$condiciones[] = 'fecha_concurso <= %s';
			$valores[]     = $hasta;
		}

		$where = 'WHERE ' . implode( ' AND ', $condiciones );
		$sql   = "SELECT * FROM {$this->tabla_scores} {$where}
		           ORDER BY fecha_concurso DESC, distancia_m DESC";

		if ( empty( $valores ) ) {
			return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $valores ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Padrón completo con la participación de cada uno, para exportar.
	 *
	 * LEFT JOIN y no INNER: la gracia del informe es justamente ver quién NO
	 * jugó. Con un INNER saldrían solo los que sí, que es lo que ya muestra el
	 * ranking.
	 *
	 * @param string $desde Fecha Y-m-d, o vacío.
	 * @param string $hasta Fecha Y-m-d, o vacío.
	 * @return array
	 */
	public function padron_con_participacion( $desde = '', $hasta = '' ) {
		global $wpdb;

		$condiciones = array();
		$valores     = array();

		if ( '' !== $desde ) {
			$condiciones[] = 'p.fecha_concurso >= %s';
			$valores[]     = $desde;
		}

		if ( '' !== $hasta ) {
			$condiciones[] = 'p.fecha_concurso <= %s';
			$valores[]     = $hasta;
		}

		$where = empty( $condiciones ) ? '' : 'WHERE ' . implode( ' AND ', $condiciones );

		$sql = "SELECT p.id, p.telefono, p.cedula, p.placa, p.fecha_concurso,
		               p.ciudad_propietario, p.departamento_propietario,
		               p.razon_social_establecimiento, p.estado,
		               s.id AS score_id, s.nombre, s.distancia_m, s.items_recogidos,
		               s.caidas, s.sobrecalentamientos, s.valido,
		               s.motivo_descalificacion, s.creado_en
		          FROM {$this->tabla_participantes} p
		          LEFT JOIN {$this->tabla_scores} s ON s.participante_id = p.id
		          {$where}
		         ORDER BY p.fecha_concurso DESC, s.distancia_m DESC, p.id ASC";

		if ( empty( $valores ) ) {
			return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $valores ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Una fila por persona del padrón, con el resumen de su campaña.
	 *
	 * Distinto de padron_con_participacion(), que devuelve una fila por
	 * carrera. Aquí la unidad es la PERSONA: cuántas veces jugó, su mejor
	 * marca y si ganó. Con revanchas, alguien puede tener varias carreras y
	 * juntarlas a mano en Excel es justo lo que este informe evita.
	 *
	 * El LEFT JOIN es lo que mantiene a los que nunca jugaron, que suelen ser
	 * la mayoría y son el dato interesante de la campaña.
	 *
	 * @return array
	 */
	public function informe_por_participante() {
		global $wpdb;

		/*
		 * La subconsulta de la mejor carrera va aparte porque MySQL no
		 * garantiza qué fila acompaña a un MAX() en un GROUP BY: pedir
		 * MAX(distancia) y fecha en la misma agregación puede devolver la
		 * distancia de una carrera y la fecha de otra. Se busca primero el id
		 * de la mejor y luego se traen SUS datos.
		 */
		$sql = "SELECT p.id, p.telefono, p.cedula, p.placa, p.fecha_concurso,
		               p.ciudad_propietario, p.departamento_propietario,
		               p.razon_social_establecimiento, p.estado,
		               COUNT(s.id) AS intentos,
		               SUM(CASE WHEN s.valido = 1 THEN 1 ELSE 0 END) AS intentos_validos,
		               MAX(CASE WHEN s.valido = 1 THEN s.distancia_m END) AS distancia_maxima,
		               MIN(s.fecha_concurso) AS primera,
		               MAX(s.fecha_concurso) AS ultima,
		               MAX(CASE WHEN s.ganador = 1 THEN 1 ELSE 0 END) AS gano,
		               GROUP_CONCAT(DISTINCT s.fecha_concurso ORDER BY s.fecha_concurso SEPARATOR ' ') AS jornadas,
		               GROUP_CONCAT(DISTINCT NULLIF(s.ganador_nota,'') SEPARATOR ' / ') AS premio,
		               mejor.nombre AS nombre,
		               mejor.fecha_concurso AS fecha_mejor,
		               mejor.items_recogidos AS llaves_mejor,
		               mejor.caidas AS caidas_mejor
		          FROM {$this->tabla_participantes} p
		          LEFT JOIN {$this->tabla_scores} s ON s.participante_id = p.id
		          LEFT JOIN {$this->tabla_scores} mejor
		                 ON mejor.id = (
		                      SELECT s2.id FROM {$this->tabla_scores} s2
		                       WHERE s2.participante_id = p.id
		                       ORDER BY s2.valido DESC, s2.distancia_m DESC, s2.creado_en ASC
		                       LIMIT 1
		                    )
		         GROUP BY p.id
		         ORDER BY gano DESC, distancia_maxima DESC, p.id ASC";

		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Totales de la campaña y su desglose por día.
	 *
	 * @return array
	 */
	public function resumen_ejecutivo() {
		global $wpdb;

		$resumen = array(
			'inscritos'              => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_participantes}" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'jornadas_padron'        => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT fecha_concurso) FROM {$this->tabla_participantes}" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'participaciones'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_scores}" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'participaciones_validas' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_scores} WHERE valido = 1" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'personas_que_jugaron'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT participante_id) FROM {$this->tabla_scores}" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'ganadores'              => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_scores} WHERE ganador = 1" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'distancia_maxima'       => (int) $wpdb->get_var( "SELECT COALESCE(MAX(distancia_m),0) FROM {$this->tabla_scores} WHERE valido = 1" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'distancia_promedio'     => (int) round( (float) $wpdb->get_var( "SELECT COALESCE(AVG(distancia_m),0) FROM {$this->tabla_scores} WHERE valido = 1" ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			'revanchas_generales'    => 0,
			'revanchas_individuales' => 0,
			'inscritos_por_dia'      => array(),
			'por_dia'                => array(),
			'por_departamento'       => array(),
		);

		if ( $this->tiene_tabla( $this->tabla_revanchas ) ) {
			$resumen['revanchas_generales']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_revanchas} WHERE participante_id = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL
			$resumen['revanchas_individuales'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tabla_revanchas} WHERE participante_id > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		$filas = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT fecha_concurso, COUNT(*) AS total
			   FROM {$this->tabla_participantes}
			  GROUP BY fecha_concurso ORDER BY fecha_concurso ASC",
			ARRAY_A
		);

		foreach ( (array) $filas as $f ) {
			$resumen['inscritos_por_dia'][ $f['fecha_concurso'] ] = (int) $f['total'];
		}

		$filas = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT fecha_concurso,
			        COUNT(*) AS participaciones,
			        SUM(CASE WHEN valido = 1 THEN 1 ELSE 0 END) AS validas,
			        SUM(CASE WHEN ganador = 1 THEN 1 ELSE 0 END) AS ganadores,
			        COALESCE(MAX(CASE WHEN valido = 1 THEN distancia_m END),0) AS maxima,
			        COALESCE(ROUND(AVG(CASE WHEN valido = 1 THEN distancia_m END)),0) AS promedio
			   FROM {$this->tabla_scores}
			  GROUP BY fecha_concurso ORDER BY fecha_concurso ASC",
			ARRAY_A
		);

		foreach ( (array) $filas as $f ) {
			$resumen['por_dia'][ $f['fecha_concurso'] ] = array(
				'participaciones' => (int) $f['participaciones'],
				'validas'         => (int) $f['validas'],
				'ganadores'       => (int) $f['ganadores'],
				'maxima'          => (int) $f['maxima'],
				'promedio'        => (int) $f['promedio'],
			);
		}

		$filas = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT departamento_propietario AS depto, COUNT(*) AS total
			   FROM {$this->tabla_scores}
			  WHERE departamento_propietario <> ''
			  GROUP BY departamento_propietario ORDER BY total DESC",
			ARRAY_A
		);

		foreach ( (array) $filas as $f ) {
			$resumen['por_departamento'][ $f['depto'] ] = (int) $f['total'];
		}

		return $resumen;
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
