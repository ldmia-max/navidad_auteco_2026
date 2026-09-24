<?php
/**
 * Generación de la pista a partir del seed.
 *
 * Espejo de game/src/sim/pista.ts. El mismo seed tiene que dar exactamente la
 * misma pista aquí y en el navegador, obstáculo por obstáculo; si no, la
 * reejecución calcula otra carrera.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-constantes.php';
require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-prng.php';

/**
 * Pista determinista.
 */
class NavidadTVS_Sim_Pista {

	/** @var array Lista de obstáculos: pos, carril, tipo, largo. */
	public $obstaculos = array();

	/** @var array Lista de llaves: pos, carril. */
	public $items = array();

	/**
	 * Separación mínima entre conos en un punto de la pista.
	 *
	 * Crece con la pista porque el techo de velocidad sube cada 20 s: lo que
	 * se protege no son los metros sino los segundos que el jugador tiene para
	 * ver un cono y cambiar de carril.
	 *
	 * @param int $pos Posición en mm.
	 * @return int Separación en mm.
	 */
	private static function separacion_conos( $pos ) {
		$c      = 'NavidadTVS_Sim_Constantes';
		$avance = $pos < $c::ALCANCE_CARRERA_MM ? $pos : $c::ALCANCE_CARRERA_MM;

		return $c::SEPARACION_CONOS_MIN
			+ intdiv( $avance * ( $c::SEPARACION_CONOS_MAX - $c::SEPARACION_CONOS_MIN ), $c::ALCANCE_CARRERA_MM );
	}

	/**
	 * Construye la pista de un seed.
	 *
	 * @param int $seed Semilla.
	 * @return self
	 */
	public static function generar( $seed ) {
		$c    = 'NavidadTVS_Sim_Constantes';
		$prng = new NavidadTVS_Sim_Prng( $seed );
		$p    = new self();

		// --- Obstáculos ---------------------------------------------------
		$cursor          = $c::ARRANQUE_LIMPIO_MM;
		$pos_ultimo_cono = -$c::SEPARACION_CONOS_MAX;

		while ( $cursor < $c::PISTA_MM ) {
			$cursor += $prng->rango( $c::GAP_MIN, $c::GAP_MAX );

			if ( $cursor >= $c::PISTA_MM ) {
				break;
			}

			$progreso_por_mil = intdiv( $cursor * 1000, $c::PISTA_MM );
			$peso_cono        = 100 + intdiv( $progreso_por_mil * 250, 1000 );
			$peso_aceite      = 350;

			$dado = $prng->rango( 0, 999 );

			if ( $dado < $peso_cono ) {
				$tipo = $c::TIPO_CONO;
			} elseif ( $dado < $peso_cono + $peso_aceite ) {
				$tipo = $c::TIPO_ACEITE;
			} else {
				$tipo = $c::TIPO_IMPULSOR;
			}

			// Conos demasiado seguidos: se convierten en impulsor.
			if ( $c::TIPO_CONO === $tipo && $cursor - $pos_ultimo_cono < self::separacion_conos( $cursor ) ) {
				$tipo = $c::TIPO_IMPULSOR;
			}

			if ( $c::TIPO_CONO === $tipo ) {
				$pos_ultimo_cono = $cursor;
			}

			$largo = $c::TIPO_ACEITE === $tipo ? $c::LARGO_ACEITE : 0;

			// Un grupo ocupa uno o dos carriles. Nunca más: con cuatro
			// carriles, dos bloqueados dejan siempre dos salidas.
			$cuantos = $prng->probabilidad( 300 ) ? 2 : 1;
			$primero = $prng->rango( 0, $c::CARRILES - 1 );

			$p->obstaculos[] = array(
				'pos'    => $cursor,
				'carril' => $primero,
				'tipo'   => $tipo,
				'largo'  => $largo,
			);

			if ( 2 === $cuantos ) {
				$segundo         = ( $primero + $prng->rango( 1, $c::CARRILES - 1 ) ) % $c::CARRILES;
				$p->obstaculos[] = array(
					'pos'    => $cursor,
					'carril' => $segundo,
					'tipo'   => $tipo,
					'largo'  => $largo,
				);
			}
		}

		// --- Llaves ----------------------------------------------------------
		$cursor_item = intdiv( $c::ARRANQUE_LIMPIO_MM, 2 );

		while ( $cursor_item < $c::PISTA_MM ) {
			$cursor_item += $prng->rango( $c::GAP_ITEM_MIN, $c::GAP_ITEM_MAX );

			if ( $cursor_item >= $c::PISTA_MM ) {
				break;
			}

			$p->items[] = array(
				'pos'    => $cursor_item,
				'carril' => $prng->rango( 0, $c::CARRILES - 1 ),
			);
		}

		return $p;
	}
}
