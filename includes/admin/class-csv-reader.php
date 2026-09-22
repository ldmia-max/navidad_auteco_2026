<?php
/**
 * Lector de CSV.
 *
 * Resuelve las trampas habituales de los archivos que salen de Excel: BOM
 * UTF-8, línea "sep=;" al principio, delimitador coma / punto y coma /
 * tabulación / barra vertical, y comparación de textos con tildes y
 * mayúsculas.
 *
 * Portado del proyecto Trivia Auteco, donde ya sobrevivió a los archivos
 * reales del área comercial.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apertura y normalización de archivos CSV.
 */
class NavidadTVS_CSV_Reader {

	/** Delimitadores que se prueban, en orden de preferencia. */
	const DELIMITADORES = array( ';', ',', "\t", '|' );

	/**
	 * Normaliza texto para comparaciones: sin acentos, en minúsculas, sin
	 * espacios repetidos y sin comillas tipográficas.
	 *
	 * Ojo: no toca los guiones bajos, así que cada variante snake_case de una
	 * cabecera tiene que listarse aparte en el mapa de alias.
	 *
	 * @param string $texto Texto crudo.
	 * @return string
	 */
	public static function normalizar( $texto ) {
		$texto = (string) $texto;

		// Quitar BOM UTF-8 si viene pegado al contenido.
		$texto = str_replace( "\xEF\xBB\xBF", '', $texto );

		// Unificar comillas tipográficas y espacios duros.
		$texto = str_replace(
			array( "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xC2\xA0" ),
			array( "'", "'", '"', '"', ' ' ),
			$texto
		);

		$texto = remove_accents( $texto );
		$texto = strtolower( trim( $texto ) );
		$texto = preg_replace( '/\s+/', ' ', $texto );

		// Si el campo llegó con comillas literales (CSV mal formado), se quitan.
		$texto = trim( $texto, "\"'" );

		return trim( $texto );
	}

	/**
	 * Abre un CSV dejando el puntero en la fila de cabeceras y devolviendo el
	 * delimitador correcto.
	 *
	 * La detección NO cuenta caracteres: parsea la primera línea con cada
	 * delimitador candidato y se queda con el que reconoce más cabeceras de
	 * las esperadas. Contar caracteres falla cuando el contenido trae comas
	 * dentro de campos entrecomillados.
	 *
	 * @param string   $ruta                Ruta del archivo.
	 * @param string[] $cabeceras_esperadas Nombres de columna ya normalizados.
	 * @return array|WP_Error array con 'handle' y 'delimitador'.
	 */
	public static function abrir( $ruta, $cabeceras_esperadas = array() ) {
		$handle = fopen( $ruta, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( ! $handle ) {
			return new WP_Error( 'lectura', __( 'No se pudo leer el archivo.', 'navidad-tvs' ) );
		}

		/*
		 * Saltar el BOM UTF-8. Excel lo escribe al guardar como "CSV UTF-8" y,
		 * si además la primera celda va entrecomillada, fgetcsv ve los bytes
		 * del BOM antes de la comilla, trata el campo como no entrecomillado y
		 * devuelve «"telefono"» con las comillas incluidas.
		 */
		$inicio = ( fread( $handle, 3 ) === "\xEF\xBB\xBF" ) ? 3 : 0;
		fseek( $handle, $inicio );

		// Saltar líneas vacías y la directiva "sep=;" que agrega Excel.
		$delim_declarado = null;
		$cabecera_cruda  = '';

		while ( true ) {
			$pos   = ftell( $handle );
			$linea = fgets( $handle );

			if ( false === $linea ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new WP_Error( 'vacio', __( 'El archivo está vacío.', 'navidad-tvs' ) );
			}

			$limpia = trim( $linea );

			if ( '' === $limpia ) {
				$inicio = ftell( $handle );
				continue;
			}

			if ( preg_match( '/^sep=(.)$/i', $limpia, $m ) ) {
				$delim_declarado = $m[1];
				$inicio          = ftell( $handle );
				continue;
			}

			// Esta es la fila de cabeceras.
			fseek( $handle, $pos );
			$inicio         = $pos;
			$cabecera_cruda = $limpia;
			break;
		}

		// Si el archivo declara su separador, se respeta.
		if ( null !== $delim_declarado ) {
			fseek( $handle, $inicio );
			return array(
				'handle'      => $handle,
				'delimitador' => $delim_declarado,
			);
		}

		// Probar cada candidato y quedarse con el que reconoce más cabeceras.
		$mejor          = ';';
		$mejor_puntaje  = -1;
		$mejor_columnas = 0;

		foreach ( self::DELIMITADORES as $delim ) {
			$campos = str_getcsv( $cabecera_cruda, $delim, '"', '\\' );

			if ( ! is_array( $campos ) ) {
				continue;
			}

			$columnas = count( $campos );
			$puntaje  = 0;

			foreach ( $campos as $campo ) {
				$clave = self::normalizar( $campo );

				foreach ( $cabeceras_esperadas as $esperada ) {
					if ( $clave === $esperada ) {
						$puntaje++;
						break;
					}
				}
			}

			// Gana el que reconoce más cabeceras; a igualdad, el que parte en
			// más columnas (un delimitador ausente deja todo en una sola).
			if ( $puntaje > $mejor_puntaje || ( $puntaje === $mejor_puntaje && $columnas > $mejor_columnas ) ) {
				$mejor          = $delim;
				$mejor_puntaje  = $puntaje;
				$mejor_columnas = $columnas;
			}
		}

		fseek( $handle, $inicio );

		return array(
			'handle'      => $handle,
			'delimitador' => $mejor,
		);
	}
}
