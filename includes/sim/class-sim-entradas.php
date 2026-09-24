<?php
/**
 * Registro de entradas del jugador.
 *
 * Espejo de game/src/sim/entradas.ts. El cliente no envía el score: envía
 * esto, y el servidor reejecuta la carrera con el mismo seed.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bits de los botones y descompresión del registro.
 */
class NavidadTVS_Sim_Entradas {

	const BIT_ACELERA = 1;
	const BIT_TURBO   = 2;
	const BIT_ARRIBA  = 4;
	const BIT_ABAJO   = 8;

	/**
	 * Tope de tamaño del texto que se acepta.
	 *
	 * Una carrera comprimida ronda los 160 bytes en base64 y el peor caso
	 * imaginable —un botón distinto en cada uno de los 5400 ticks— son 14 400.
	 * 64 KB deja margen de sobra y evita que alguien mande un megabyte para
	 * hacer trabajar al servidor.
	 */
	const MAX_BASE64 = 65536;

	/**
	 * Deshace la compresión por repeticiones.
	 *
	 * Formato: pares (valor, repeticiones) en binario, codificados en base64.
	 *
	 * Es la primera cosa que toca datos que vienen del navegador, así que
	 * valida todo: que sea base64 de verdad, que tenga un número par de bytes,
	 * que no declare más ticks de los que dura una carrera y que al final sume
	 * exactamente los que tiene que sumar.
	 *
	 * @param string $texto Registro en base64.
	 * @param int    $ticks Cuántos ticks tiene que haber al terminar.
	 * @return array|WP_Error Arreglo de enteros 0..255, o el motivo del rechazo.
	 */
	public static function decodificar( $texto, $ticks ) {
		$texto = (string) $texto;

		if ( '' === $texto || strlen( $texto ) > self::MAX_BASE64 ) {
			return new WP_Error( 'registro_tamano', __( 'El registro de la carrera no tiene un tamaño válido.', 'navidad-tvs' ) );
		}

		// strict: base64_decode() sin él se traga cualquier basura en silencio.
		$binario = base64_decode( $texto, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $binario ) {
			return new WP_Error( 'registro_base64', __( 'El registro de la carrera está mal codificado.', 'navidad-tvs' ) );
		}

		$largo = strlen( $binario );

		if ( 0 === $largo || 0 !== $largo % 2 ) {
			return new WP_Error( 'registro_impar', __( 'El registro de la carrera está incompleto.', 'navidad-tvs' ) );
		}

		$salida = array();

		for ( $i = 0; $i < $largo; $i += 2 ) {
			$valor = ord( $binario[ $i ] );
			$repes = ord( $binario[ $i + 1 ] );

			if ( 0 === $repes ) {
				return new WP_Error( 'registro_repeticion', __( 'El registro de la carrera está corrupto.', 'navidad-tvs' ) );
			}

			if ( count( $salida ) + $repes > $ticks ) {
				return new WP_Error( 'registro_largo', __( 'El registro de la carrera es más largo de lo que dura una carrera.', 'navidad-tvs' ) );
			}

			for ( $r = 0; $r < $repes; $r++ ) {
				$salida[] = $valor;
			}
		}

		if ( count( $salida ) !== $ticks ) {
			return new WP_Error(
				'registro_ticks',
				sprintf(
					/* translators: 1: ticks recibidos, 2: ticks esperados. */
					__( 'El registro tiene %1$d ticks y la carrera dura %2$d.', 'navidad-tvs' ),
					count( $salida ),
					$ticks
				)
			);
		}

		return $salida;
	}

	/**
	 * Comprime y codifica, igual que el cliente. Solo lo usa la paridad.
	 *
	 * @param array $registro Enteros 0..255.
	 * @return string
	 */
	public static function codificar( array $registro ) {
		$binario = '';
		$i       = 0;
		$n       = count( $registro );

		while ( $i < $n ) {
			$valor = $registro[ $i ];
			$repes = 1;

			while ( $i + $repes < $n && $registro[ $i + $repes ] === $valor && $repes < 255 ) {
				$repes++;
			}

			$binario .= chr( $valor ) . chr( $repes );
			$i       += $repes;
		}

		return base64_encode( $binario ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
