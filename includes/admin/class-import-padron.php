<?php
/**
 * Importador del padrón de compradores.
 *
 * Cada CSV que envía el área comercial es el lote de UNA jornada: todas sus
 * filas traen la misma fecha_concurso, que es el único día en que esas
 * personas pueden jugar. La importación es acumulativa: nunca borra lo
 * anterior ni toca los scores ya registrados.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/admin/class-csv-reader.php';

/**
 * Lectura, validación e inserción del padrón.
 */
class NavidadTVS_Import_Padron {

	/** Tope de filas por archivo. */
	const MAX_FILAS = 20000;

	/** Tamaño máximo del archivo, en bytes. */
	const MAX_BYTES = 10485760; // 10 MB.

	/** Filas por sentencia INSERT. */
	const LOTE = 200;

	/** Transient donde viaja el resultado entre el POST y el redirect. */
	const TRANSIENT = 'navidad_tvs_import_resultado_';

	/** @var NavidadTVS_Database */
	private $database;

	/**
	 * @param NavidadTVS_Database $database Acceso a datos.
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Cablea los hooks del módulo.
	 *
	 * @return void
	 */
	public function registrar_hooks() {
		add_action( 'admin_post_navidad_tvs_importar_padron', array( $this, 'manejar_importacion' ) );
		add_action( 'admin_post_navidad_tvs_descargar_rechazos', array( $this, 'descargar_rechazos' ) );
	}

	// ---------------------------------------------------------------------
	// Mapeo de cabeceras
	// ---------------------------------------------------------------------

	/**
	 * Alias aceptados por columna.
	 *
	 * El padrón real llega en snake_case con separador ";", pero se aceptan
	 * también los nombres con acentos y espacios por si el área comercial
	 * exporta desde Excel con otro formato.
	 *
	 * Ojo: normalizar() no toca los guiones bajos, así que cada variante tiene
	 * que estar listada aparte.
	 *
	 * @return array<string, string[]>
	 */
	private function alias_cabeceras() {
		return array(
			'telefono'                     => array( 'telefono', 'telefono_celular', 'celular', 'numero', 'numero_celular' ),
			'cedula'                       => array( 'cedula', 'documento', 'numero_documento', 'cedula_propietario' ),
			'placa'                        => array( 'placa', 'matricula', 'placa_vehiculo' ),
			'fecha_concurso'               => array( 'fecha_concurso', 'fecha concurso', 'fecha_de_concurso', 'jornada' ),
			'marca'                        => array( 'marca', 'marca_moto', 'marca_vehiculo' ),
			'fecha_matricula'              => array( 'fecha_matricula', 'fecha matricula', 'fecha_de_matricula' ),
			'fecha_acta'                   => array( 'fecha_acta', 'fecha acta', 'fecha_de_acta' ),
			'ciudad_propietario'           => array( 'ciudad_propietario', 'ciudad propietario', 'ciudad' ),
			'departamento_propietario'     => array( 'departamento_propietario', 'departamento propietario', 'departamento' ),
			'razon_social_establecimiento' => array( 'razon_social_establecimiento', 'razon social establecimiento', 'establecimiento', 'concesionario' ),
		);
	}

	/** Columnas sin las cuales no se puede importar. */
	private function cabeceras_obligatorias() {
		return array( 'telefono', 'cedula', 'placa', 'fecha_concurso', 'marca' );
	}

	/**
	 * Empareja la fila de cabeceras del archivo con los campos internos.
	 *
	 * @param string[] $fila Fila de cabeceras cruda.
	 * @return array<string, int> Campo interno => índice de columna.
	 */
	private function mapear_cabeceras( $fila ) {
		$mapa  = array();
		$alias = $this->alias_cabeceras();

		foreach ( $fila as $indice => $celda ) {
			$clave = NavidadTVS_CSV_Reader::normalizar( $celda );

			foreach ( $alias as $campo => $variantes ) {
				if ( isset( $mapa[ $campo ] ) ) {
					continue;
				}
				if ( in_array( $clave, $variantes, true ) ) {
					$mapa[ $campo ] = $indice;
					break;
				}
			}
		}

		return $mapa;
	}

	// ---------------------------------------------------------------------
	// Normalizadores de campo
	// ---------------------------------------------------------------------

	/**
	 * Deja la cédula en solo dígitos.
	 *
	 * @param string $valor Valor crudo.
	 * @return string Cadena vacía si no queda nada utilizable.
	 */
	public static function normalizar_cedula( $valor ) {
		$digitos = preg_replace( '/\D+/', '', (string) $valor );
		return ( strlen( $digitos ) >= 5 && strlen( $digitos ) <= 20 ) ? $digitos : '';
	}

	/**
	 * Deja la placa en mayúsculas y sin separadores.
	 *
	 * @param string $valor Valor crudo.
	 * @return string Cadena vacía si no es una placa plausible.
	 */
	public static function normalizar_placa( $valor ) {
		$placa = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $valor ) );
		return ( strlen( $placa ) >= 5 && strlen( $placa ) <= 10 ) ? $placa : '';
	}

	/**
	 * Convierte una fecha del CSV a Y-m-d.
	 *
	 * Acepta Y-m-d, d/m/Y, d-m-Y y Y/m/d, que es lo que suele salir de Excel
	 * según la configuración regional de quien exporta.
	 *
	 * @param string $valor Valor crudo.
	 * @return string Fecha Y-m-d, o cadena vacía si no se pudo interpretar.
	 */
	public static function normalizar_fecha( $valor ) {
		$valor = trim( (string) $valor );

		if ( '' === $valor ) {
			return '';
		}

		foreach ( array( 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d H:i:s', 'd/m/Y H:i' ) as $formato ) {
			$fecha = DateTimeImmutable::createFromFormat( $formato, $valor );
			if ( $fecha instanceof DateTimeImmutable ) {
				$errores = DateTimeImmutable::getLastErrors();
				if ( empty( $errores['warning_count'] ) && empty( $errores['error_count'] ) ) {
					return $fecha->format( 'Y-m-d' );
				}
			}
		}

		return '';
	}

	// ---------------------------------------------------------------------
	// Análisis del archivo
	// ---------------------------------------------------------------------

	/**
	 * Lee y valida el archivo sin escribir nada en la base de datos.
	 *
	 * @param string $ruta Ruta del CSV.
	 * @return array|WP_Error array con 'filas', 'rechazos' y 'jornadas'.
	 */
	public function analizar( $ruta ) {
		$esperadas = array();
		foreach ( $this->alias_cabeceras() as $variantes ) {
			$esperadas = array_merge( $esperadas, $variantes );
		}

		$abierto = NavidadTVS_CSV_Reader::abrir( $ruta, $esperadas );

		if ( is_wp_error( $abierto ) ) {
			return $abierto;
		}

		$handle = $abierto['handle'];
		$delim  = $abierto['delimitador'];

		$cabecera = fgetcsv( $handle, 0, $delim, '"', '\\' );

		if ( ! is_array( $cabecera ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'cabeceras', __( 'No se pudo leer la fila de cabeceras.', 'navidad-tvs' ) );
		}

		$mapa     = $this->mapear_cabeceras( $cabecera );
		$faltan   = array_diff( $this->cabeceras_obligatorias(), array_keys( $mapa ) );

		if ( ! empty( $faltan ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error(
				'cabeceras',
				sprintf(
					/* translators: %s: lista de columnas que faltan */
					__( 'Al archivo le faltan columnas obligatorias: %s. La primera fila debe traer al menos: telefono, cedula, placa, fecha_concurso, marca.', 'navidad-tvs' ),
					implode( ', ', $faltan )
				)
			);
		}

		$filas    = array();
		$rechazos = array();
		$jornadas = array();

		// Para detectar duplicados dentro del propio archivo, recordando en
		// qué fila apareció cada valor por primera vez.
		$vistos = array(
			'telefono' => array(),
			'cedula'   => array(),
			'placa'    => array(),
		);

		$numero_fila = 1; // La cabecera es la fila 1.

		while ( true ) {
			$cruda = fgetcsv( $handle, 0, $delim, '"', '\\' );

			if ( false === $cruda || null === $cruda ) {
				break;
			}

			$numero_fila++;

			if ( count( $filas ) + count( $rechazos ) >= self::MAX_FILAS ) {
				$rechazos[] = $this->rechazo(
					$numero_fila,
					'',
					sprintf(
						/* translators: %d: máximo de filas */
						__( 'Se alcanzó el máximo de %d filas por archivo. Las siguientes se ignoraron.', 'navidad-tvs' ),
						self::MAX_FILAS
					)
				);
				break;
			}

			/*
			 * Saltar filas totalmente vacías sin contarlas como rechazo.
			 *
			 * fgetcsv devuelve array( null ) ante una línea en blanco, y desde
			 * PHP 8.1 pasarle null a trim() emite un aviso de obsolescencia. Un
			 * CSV con líneas vacías al final llenaría el log en producción.
			 */
			$contenido = '';
			foreach ( (array) $cruda as $celda ) {
				$contenido .= trim( (string) $celda );
			}
			if ( '' === $contenido ) {
				continue;
			}

			$leer = function ( $campo ) use ( $cruda, $mapa ) {
				if ( ! isset( $mapa[ $campo ] ) ) {
					return '';
				}
				$indice = $mapa[ $campo ];
				return isset( $cruda[ $indice ] ) ? trim( (string) $cruda[ $indice ] ) : '';
			};

			$telefono_csv = $leer( 'telefono' );
			$referencia   = '' !== $telefono_csv ? $telefono_csv : $leer( 'cedula' );

			// --- Marca: solo TVS ------------------------------------------
			$marca = NavidadTVS_CSV_Reader::normalizar( $leer( 'marca' ) );
			if ( strtolower( NAVIDAD_TVS_MARCA ) !== $marca ) {
				$rechazos[] = $this->rechazo(
					$numero_fila,
					$referencia,
					sprintf(
						/* translators: 1: marca encontrada, 2: marca admitida */
						__( 'Marca "%1$s" no admitida. Solo participa %2$s.', 'navidad-tvs' ),
						'' !== $marca ? $leer( 'marca' ) : __( '(vacía)', 'navidad-tvs' ),
						NAVIDAD_TVS_MARCA
					)
				);
				continue;
			}

			// --- Teléfono --------------------------------------------------
			$telefono = NavidadTVS_Plugin::normalizar_telefono( $telefono_csv );
			if ( '' === $telefono ) {
				$rechazos[] = $this->rechazo(
					$numero_fila,
					$referencia,
					'' === $telefono_csv
						? __( 'Falta el teléfono.', 'navidad-tvs' )
						: sprintf(
							/* translators: %s: teléfono encontrado */
							__( 'Teléfono "%s" no es un celular colombiano válido (se esperan 10 dígitos que empiecen por 3, con o sin indicativo 57).', 'navidad-tvs' ),
							$telefono_csv
						)
				);
				continue;
			}

			// --- Cédula ----------------------------------------------------
			$cedula = self::normalizar_cedula( $leer( 'cedula' ) );
			if ( '' === $cedula ) {
				$rechazos[] = $this->rechazo( $numero_fila, $referencia, __( 'Falta la cédula o no es válida.', 'navidad-tvs' ) );
				continue;
			}

			// --- Placa -----------------------------------------------------
			$placa = self::normalizar_placa( $leer( 'placa' ) );
			if ( '' === $placa ) {
				$rechazos[] = $this->rechazo( $numero_fila, $referencia, __( 'Falta la placa o no es válida.', 'navidad-tvs' ) );
				continue;
			}

			// --- Fecha de la jornada ---------------------------------------
			$fecha_concurso = self::normalizar_fecha( $leer( 'fecha_concurso' ) );
			if ( '' === $fecha_concurso ) {
				$rechazos[] = $this->rechazo(
					$numero_fila,
					$referencia,
					sprintf(
						/* translators: %s: valor encontrado */
						__( 'Fecha de concurso inválida: "%s". Se espera AAAA-MM-DD.', 'navidad-tvs' ),
						$leer( 'fecha_concurso' )
					)
				);
				continue;
			}

			// --- Duplicados dentro del propio archivo -----------------------
			$duplicado = false;
			foreach ( array(
				'telefono' => $telefono,
				'cedula'   => $cedula,
				'placa'    => $placa,
			) as $campo => $valor ) {
				if ( isset( $vistos[ $campo ][ $valor ] ) ) {
					$rechazos[] = $this->rechazo(
						$numero_fila,
						$referencia,
						sprintf(
							/* translators: 1: nombre del campo, 2: valor, 3: número de fila anterior */
							__( 'El campo %1$s con valor "%2$s" ya apareció en la fila %3$d de este archivo.', 'navidad-tvs' ),
							$campo,
							$valor,
							$vistos[ $campo ][ $valor ]
						)
					);
					$duplicado = true;
					break;
				}
			}
			if ( $duplicado ) {
				continue;
			}

			$vistos['telefono'][ $telefono ] = $numero_fila;
			$vistos['cedula'][ $cedula ]     = $numero_fila;
			$vistos['placa'][ $placa ]       = $numero_fila;

			$filas[] = array(
				'telefono'                     => $telefono,
				'telefono_csv'                 => substr( $telefono_csv, 0, 20 ),
				'cedula'                       => $cedula,
				'placa'                        => $placa,
				'fecha_concurso'               => $fecha_concurso,
				'marca'                        => NAVIDAD_TVS_MARCA,
				'fecha_matricula'              => self::normalizar_fecha( $leer( 'fecha_matricula' ) ),
				'fecha_acta'                   => self::normalizar_fecha( $leer( 'fecha_acta' ) ),
				'ciudad_propietario'           => substr( $leer( 'ciudad_propietario' ), 0, 100 ),
				'departamento_propietario'     => substr( $leer( 'departamento_propietario' ), 0, 100 ),
				'razon_social_establecimiento' => substr( $leer( 'razon_social_establecimiento' ), 0, 191 ),
				'fila'                         => $numero_fila,
				'referencia'                   => $referencia,
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// --- Duplicados contra lo ya importado ------------------------------
		$filas = $this->descartar_ya_existentes( $filas, $rechazos );

		// Las jornadas se cuentan sobre lo que realmente se va a insertar. Si
		// se contaran antes del filtro, reimportar un archivo ya cargado
		// mostraría "2026-12-01 (5)" junto a "Importadas: 0".
		foreach ( $filas as $fila ) {
			$fecha              = $fila['fecha_concurso'];
			$jornadas[ $fecha ] = isset( $jornadas[ $fecha ] ) ? $jornadas[ $fecha ] + 1 : 1;
		}

		ksort( $jornadas );

		return array(
			'filas'       => $filas,
			'rechazos'    => $rechazos,
			'jornadas'    => $jornadas,
			'delimitador' => $delim,
		);
	}

	/**
	 * Saca de la lista las filas cuyo teléfono, cédula o placa ya está en el
	 * padrón, y las pasa a rechazos con el motivo.
	 *
	 * Se resuelve con tres consultas por lote en vez de tres por fila: con 500
	 * filas eso son 1500 consultas evitadas.
	 *
	 * @param array $filas    Filas candidatas. Se consume por valor.
	 * @param array $rechazos Lista de rechazos, por referencia.
	 * @return array Filas que sí se pueden insertar.
	 */
	private function descartar_ya_existentes( $filas, &$rechazos ) {
		if ( empty( $filas ) ) {
			return $filas;
		}

		$existentes = $this->database->buscar_existentes(
			wp_list_pluck( $filas, 'telefono' ),
			wp_list_pluck( $filas, 'cedula' ),
			wp_list_pluck( $filas, 'placa' )
		);

		$limpias = array();

		foreach ( $filas as $fila ) {
			$choque = null;

			if ( isset( $existentes['telefono'][ $fila['telefono'] ] ) ) {
				$choque = array( 'teléfono', $fila['telefono'], $existentes['telefono'][ $fila['telefono'] ] );
			} elseif ( isset( $existentes['cedula'][ $fila['cedula'] ] ) ) {
				$choque = array( 'cédula', $fila['cedula'], $existentes['cedula'][ $fila['cedula'] ] );
			} elseif ( isset( $existentes['placa'][ $fila['placa'] ] ) ) {
				$choque = array( 'placa', $fila['placa'], $existentes['placa'][ $fila['placa'] ] );
			}

			if ( null === $choque ) {
				$limpias[] = $fila;
				continue;
			}

			$rechazos[] = $this->rechazo(
				$fila['fila'],
				$fila['referencia'],
				sprintf(
					/* translators: 1: nombre del campo, 2: valor, 3: jornada en que ya está registrado */
					__( 'Ya está en el padrón: el campo %1$s con valor "%2$s" pertenece a la jornada %3$s.', 'navidad-tvs' ),
					$choque[0],
					$choque[1],
					$choque[2]
				)
			);
		}

		return $limpias;
	}

	/**
	 * Arma una entrada de rechazo.
	 *
	 * @param int    $fila       Número de fila en el archivo.
	 * @param string $referencia Teléfono o cédula, para ubicar la fila.
	 * @param string $motivo     Explicación en lenguaje claro.
	 * @return array
	 */
	private function rechazo( $fila, $referencia, $motivo ) {
		return array(
			'fila'       => (int) $fila,
			'referencia' => (string) $referencia,
			'motivo'     => (string) $motivo,
		);
	}

	// ---------------------------------------------------------------------
	// Manejo del formulario
	// ---------------------------------------------------------------------

	/**
	 * Recibe el archivo, valida, importa y redirige con el resultado.
	 *
	 * @return void
	 */
	public function manejar_importacion() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para importar el padrón.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_importar_padron' );

		$destino = add_query_arg(
			'page',
			NavidadTVS_Admin::SLUG . '-importar',
			admin_url( 'admin.php' )
		);

		$error = $this->validar_subida();

		if ( is_wp_error( $error ) ) {
			$this->guardar_resultado( array( 'error' => $error->get_error_message() ) );
			wp_safe_redirect( $destino );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ruta      = $_FILES['archivo']['tmp_name'];
		$analisis  = $this->analizar( $ruta );

		if ( is_wp_error( $analisis ) ) {
			$this->guardar_resultado( array( 'error' => $analisis->get_error_message() ) );
			wp_safe_redirect( $destino );
			exit;
		}

		$insertadas = $this->database->insertar_participantes( $analisis['filas'], self::LOTE );

		$this->guardar_resultado(
			array(
				'archivo'    => sanitize_file_name( wp_unslash( $_FILES['archivo']['name'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'insertadas' => $insertadas,
				'rechazadas' => count( $analisis['rechazos'] ),
				'jornadas'   => $analisis['jornadas'],
				'rechazos'   => $analisis['rechazos'],
			)
		);

		wp_safe_redirect( $destino );
		exit;
	}

	/**
	 * Valida que la subida sea utilizable antes de abrir el archivo.
	 *
	 * @return true|WP_Error
	 */
	private function validar_subida() {
		if ( empty( $_FILES['archivo'] ) || ! isset( $_FILES['archivo']['tmp_name'] ) ) {
			return new WP_Error( 'sin_archivo', __( 'No se recibió ningún archivo.', 'navidad-tvs' ) );
		}

		$archivo = $_FILES['archivo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! empty( $archivo['error'] ) && UPLOAD_ERR_OK !== (int) $archivo['error'] ) {
			if ( UPLOAD_ERR_INI_SIZE === (int) $archivo['error'] || UPLOAD_ERR_FORM_SIZE === (int) $archivo['error'] ) {
				return new WP_Error( 'muy_grande', __( 'El archivo supera el tamaño máximo que acepta el servidor.', 'navidad-tvs' ) );
			}
			return new WP_Error( 'subida', __( 'La subida del archivo falló. Inténtalo de nuevo.', 'navidad-tvs' ) );
		}

		if ( ! is_uploaded_file( $archivo['tmp_name'] ) ) {
			return new WP_Error( 'invalido', __( 'El archivo recibido no es una subida válida.', 'navidad-tvs' ) );
		}

		if ( (int) $archivo['size'] > self::MAX_BYTES ) {
			return new WP_Error(
				'muy_grande',
				sprintf(
					/* translators: %s: tamaño máximo legible */
					__( 'El archivo pesa más de %s.', 'navidad-tvs' ),
					size_format( self::MAX_BYTES )
				)
			);
		}

		$extension = strtolower( pathinfo( $archivo['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'csv', 'txt' ), true ) ) {
			return new WP_Error( 'extension', __( 'El archivo debe ser .csv.', 'navidad-tvs' ) );
		}

		return true;
	}

	// ---------------------------------------------------------------------
	// Resultado y reporte
	// ---------------------------------------------------------------------

	/**
	 * Guarda el resultado de la importación para mostrarlo tras el redirect.
	 *
	 * @param array $resultado Datos a mostrar.
	 * @return void
	 */
	private function guardar_resultado( $resultado ) {
		set_transient( self::TRANSIENT . get_current_user_id(), $resultado, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Lee el resultado de la última importación de este usuario.
	 *
	 * @param bool $consumir Si true, lo borra tras leerlo.
	 * @return array|false
	 */
	public function leer_resultado( $consumir = false ) {
		$clave     = self::TRANSIENT . get_current_user_id();
		$resultado = get_transient( $clave );

		if ( $consumir && false !== $resultado ) {
			delete_transient( $clave );
		}

		return $resultado;
	}

	/**
	 * Descarga el reporte de filas rechazadas en CSV.
	 *
	 * @return void
	 */
	public function descargar_rechazos() {
		if ( ! current_user_can( NavidadTVS_Admin::CAPACIDAD ) ) {
			wp_die( esc_html__( 'No tienes permisos para descargar el reporte.', 'navidad-tvs' ) );
		}

		check_admin_referer( 'navidad_tvs_descargar_rechazos' );

		$resultado = $this->leer_resultado();
		$rechazos  = ( is_array( $resultado ) && ! empty( $resultado['rechazos'] ) ) ? $resultado['rechazos'] : array();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=rechazos-padron-' . gmdate( 'Ymd-His' ) . '.csv' );

		$salida = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// BOM para que Excel abra las tildes correctamente.
		fwrite( $salida, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		fputcsv( $salida, array( 'fila', 'referencia', 'motivo' ), ';', '"', '\\' );

		foreach ( $rechazos as $r ) {
			fputcsv( $salida, array( $r['fila'], $r['referencia'], $r['motivo'] ), ';', '"', '\\' );
		}

		fclose( $salida ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
