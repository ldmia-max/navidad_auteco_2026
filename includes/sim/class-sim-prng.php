<?php
/**
 * Generador pseudoaleatorio mulberry32.
 *
 * Espejo de game/src/sim/prng.ts. La pista sale de aquí, sembrada con el seed
 * que emitió el servidor al arrancar la carrera.
 *
 * Toda la aritmética es de 32 bits SIN SIGNO. En JavaScript eso se consigue
 * cerrando cada operación con >>> 0; aquí, enmascarando con 0xFFFFFFFF y
 * multiplicando por mitades de 16 bits.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * mulberry32, idéntico bit a bit al de TypeScript.
 */
class NavidadTVS_Sim_Prng {

	/** @var int Estado interno, uint32. */
	private $estado;

	/**
	 * @param int $seed Semilla de 32 bits.
	 */
	public function __construct( $seed ) {
		$this->estado = ( (int) $seed ) & 0xFFFFFFFF;
	}

	/**
	 * Multiplicación de 32 bits, equivalente a Math.imul() de JavaScript.
	 *
	 * No vale con ($a * $b) & 0xFFFFFFFF: dos factores de 32 bits dan hasta 64,
	 * y en PHP el entero con signo de 64 bits se desborda y pasa a float, con
	 * lo que se pierden bits bajos y el resultado deja de coincidir con el del
	 * navegador. Multiplicando por mitades de 16 bits ningún producto
	 * intermedio pasa de 2^33 y todo se mantiene exacto.
	 *
	 * Devuelve los 32 bits bajos sin signo. Math.imul los devuelve con signo,
	 * pero TypeScript los cierra siempre con >>> 0, así que coinciden.
	 *
	 * @param int $a Factor.
	 * @param int $b Factor.
	 * @return int uint32.
	 */
	private static function imul( $a, $b ) {
		$a &= 0xFFFFFFFF;
		$b &= 0xFFFFFFFF;

		$ah = ( $a >> 16 ) & 0xFFFF;
		$al = $a & 0xFFFF;
		$bh = ( $b >> 16 ) & 0xFFFF;
		$bl = $b & 0xFFFF;

		return ( ( $al * $bl ) + ( ( ( ( $ah * $bl ) + ( $al * $bh ) ) & 0xFFFF ) << 16 ) ) & 0xFFFFFFFF;
	}

	/**
	 * Siguiente entero sin signo de 32 bits.
	 *
	 * @return int
	 */
	public function siguiente() {
		$this->estado = ( $this->estado + 0x6D2B79F5 ) & 0xFFFFFFFF;

		$t = $this->estado;
		$t = self::imul( $t ^ ( $t >> 15 ), $t | 1 );
		$t = ( $t ^ ( ( $t + self::imul( $t ^ ( $t >> 7 ), $t | 61 ) ) & 0xFFFFFFFF ) ) & 0xFFFFFFFF;

		return ( $t ^ ( $t >> 14 ) ) & 0xFFFFFFFF;
	}

	/**
	 * Entero entre min y max, ambos incluidos.
	 *
	 * @param int $min Mínimo.
	 * @param int $max Máximo.
	 * @return int
	 */
	public function rango( $min, $max ) {
		return $min + ( $this->siguiente() % ( $max - $min + 1 ) );
	}

	/**
	 * True con probabilidad porMil/1000.
	 *
	 * @param int $por_mil Probabilidad en milésimas.
	 * @return bool
	 */
	public function probabilidad( $por_mil ) {
		return ( $this->siguiente() % 1000 ) < $por_mil;
	}
}
