<?php
/**
 * Reejecución de la carrera en el servidor.
 *
 * Espejo de game/src/sim/simulacion.ts. Entra un estado y una entrada, sale el
 * estado siguiente; no sabe nada de WordPress ni de la petición HTTP.
 *
 * Reglas que sostienen todo:
 *
 * - Solo enteros. Las divisiones pasan por intdiv(), que trunca hacia cero
 *   igual que Math.trunc() de JavaScript.
 * - Paso fijo de 60 ticks por segundo, siempre 5400 ticks.
 * - Nada de aleatoriedad libre: la pista viene del seed que emitió el
 *   servidor.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-constantes.php';
require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-entradas.php';
require_once NAVIDAD_TVS_PATH . 'includes/sim/class-sim-pista.php';

/**
 * El motor del juego, en PHP.
 */
class NavidadTVS_Sim_Simulacion {

	/**
	 * Estado inicial.
	 *
	 * Las claves llevan el mismo nombre que en TypeScript para que la suite de
	 * paridad pueda compararlas una a una sin traducir nada.
	 *
	 * @return array
	 */
	public static function crear_estado() {
		return array(
			'tick'                => 0,
			'pos'                 => 0,
			'vel'                 => 0,
			'carril'              => 1,
			'esperaCarril'        => 0,
			'temp'                => 0,
			'sobrecalentado'      => 0,
			'caido'               => 0,
			'items'               => 0,
			'impulsores'          => 0,
			'caidas'              => 0,
			'sobrecalentamientos' => 0,
			'aceiteHasta'         => 0,
			'idxObstaculo'        => 0,
			'idxItem'             => 0,
		);
	}

	/**
	 * Cuánto ha subido el techo de velocidad en este tick, en mm/s.
	 *
	 * Solo depende del tick: dos participantes en el mismo segundo tienen
	 * exactamente el mismo techo, juegue como juegue cada uno.
	 *
	 * @param int $tick Tick actual.
	 * @return int
	 */
	public static function subida_escalon( $tick ) {
		$paso = intdiv( $tick, NavidadTVS_Sim_Constantes::TICKS_POR_ESCALON );

		if ( $paso > NavidadTVS_Sim_Constantes::ESCALONES_MAX ) {
			$paso = NavidadTVS_Sim_Constantes::ESCALONES_MAX;
		}

		return $paso * NavidadTVS_Sim_Constantes::SUBIDA_POR_ESCALON;
	}

	/**
	 * Distancia que se le muestra al participante y que decide el ranking.
	 *
	 * @param array $e Estado.
	 * @return int
	 */
	public static function distancia_metros( array $e ) {
		return intdiv( $e['pos'], 1000 )
			+ $e['items'] * NavidadTVS_Sim_Constantes::ITEM_METROS
			+ $e['impulsores'] * NavidadTVS_Sim_Constantes::IMPULSOR_METROS;
	}

	/**
	 * Metros recorridos, sin contar llaves ni impulsores.
	 *
	 * @param array $e Estado.
	 * @return int
	 */
	public static function distancia_base_metros( array $e ) {
		return intdiv( $e['pos'], 1000 );
	}

	/**
	 * Avanza un tick.
	 *
	 * @param array                $e       Estado, por referencia.
	 * @param int                  $entrada Byte de botones.
	 * @param NavidadTVS_Sim_Pista $pista   Pista del seed.
	 * @return void
	 */
	public static function paso( array &$e, $entrada, NavidadTVS_Sim_Pista $pista ) {
		$e['tick']++;

		// --- Caído: no responde a nada ------------------------------------
		if ( $e['caido'] > 0 ) {
			$e['caido']--;
			$e['vel'] = 0;
			return;
		}

		// --- Motor sobrecalentado: pierde potencia y se enfría -------------
		if ( $e['sobrecalentado'] > 0 ) {
			$e['sobrecalentado']--;
			$e['vel']  = max(
				0,
				$e['vel'] - intdiv( NavidadTVS_Sim_Constantes::DECEL_SOBRECALENTADO, NavidadTVS_Sim_Constantes::TPS )
			);
			$e['temp'] = 0;
			self::avanzar( $e, $pista );
			return;
		}

		// --- Cambio de carril ---------------------------------------------
		if ( $e['esperaCarril'] > 0 ) {
			$e['esperaCarril']--;
		}

		if ( 0 === $e['esperaCarril'] ) {
			if ( ( $entrada & NavidadTVS_Sim_Entradas::BIT_ARRIBA ) !== 0 && $e['carril'] > 0 ) {
				$e['carril']--;
				$e['esperaCarril'] = NavidadTVS_Sim_Constantes::TICKS_CAMBIO_CARRIL;
			} elseif ( ( $entrada & NavidadTVS_Sim_Entradas::BIT_ABAJO ) !== 0
				&& $e['carril'] < NavidadTVS_Sim_Constantes::CARRILES - 1 ) {
				$e['carril']++;
				$e['esperaCarril'] = NavidadTVS_Sim_Constantes::TICKS_CAMBIO_CARRIL;
			}
		}

		// --- Motor ----------------------------------------------------------
		// El techo sube por escalones con el reloj; la aceleración no cambia.
		$subida = self::subida_escalon( $e['tick'] );

		if ( ( $entrada & NavidadTVS_Sim_Entradas::BIT_TURBO ) !== 0 ) {
			$vmax       = NavidadTVS_Sim_Constantes::V_MAX_TURBO + $subida;
			$acel       = NavidadTVS_Sim_Constantes::ACEL_TURBO;
			$e['temp'] += NavidadTVS_Sim_Constantes::TEMP_TURBO;
		} elseif ( ( $entrada & NavidadTVS_Sim_Entradas::BIT_ACELERA ) !== 0 ) {
			$vmax       = NavidadTVS_Sim_Constantes::V_MAX_NORMAL + $subida;
			$acel       = NavidadTVS_Sim_Constantes::ACEL_NORMAL;
			$e['temp'] += NavidadTVS_Sim_Constantes::TEMP_NORMAL;
		} else {
			$vmax       = 0;
			$acel       = 0;
			$e['temp'] += NavidadTVS_Sim_Constantes::TEMP_SUELTO;
		}

		if ( $e['temp'] < 0 ) {
			$e['temp'] = 0;
		}

		if ( $e['temp'] >= NavidadTVS_Sim_Constantes::TEMP_MAX ) {
			$e['temp']           = 0;
			$e['sobrecalentado'] = NavidadTVS_Sim_Constantes::TICKS_SOBRECALENTADO;
			$e['sobrecalentamientos']++;
		}

		/*
		 * Si se venía de turbo y ahora se acelera normal, la velocidad no cae
		 * de golpe al nuevo techo: baja por fricción.
		 */
		if ( $e['vel'] > $vmax ) {
			$e['vel'] = max( $vmax, $e['vel'] - intdiv( NavidadTVS_Sim_Constantes::FRICCION, NavidadTVS_Sim_Constantes::TPS ) );
		} elseif ( $acel > 0 ) {
			$e['vel'] = min( $vmax, $e['vel'] + intdiv( $acel, NavidadTVS_Sim_Constantes::TPS ) );
		}

		self::avanzar( $e, $pista );
	}

	/**
	 * Mueve la moto y resuelve lo que se encuentra por el camino.
	 *
	 * @param array                $e     Estado, por referencia.
	 * @param NavidadTVS_Sim_Pista $pista Pista.
	 * @return void
	 */
	private static function avanzar( array &$e, NavidadTVS_Sim_Pista $pista ) {
		// El aceite frena mientras se está encima, sin tocar la del motor.
		$en_aceite = $e['pos'] < $e['aceiteHasta'];
		$efectiva  = $en_aceite
			? intdiv( $e['vel'] * NavidadTVS_Sim_Constantes::ACEITE_POR_MIL, 1000 )
			: $e['vel'];

		$e['pos'] += intdiv( $efectiva, NavidadTVS_Sim_Constantes::TPS );

		// --- Obstáculos ------------------------------------------------------
		$n_obs = count( $pista->obstaculos );

		while ( $e['idxObstaculo'] < $n_obs && $pista->obstaculos[ $e['idxObstaculo'] ]['pos'] <= $e['pos'] ) {
			$obs = $pista->obstaculos[ $e['idxObstaculo'] ];
			$e['idxObstaculo']++;

			if ( $obs['carril'] !== $e['carril'] ) {
				continue;
			}

			if ( NavidadTVS_Sim_Constantes::TIPO_IMPULSOR === $obs['tipo'] ) {
				$e['impulsores']++;
				// El tope del impulsor sube con el techo, como todo lo demás.
				$e['vel'] = min(
					NavidadTVS_Sim_Constantes::V_MAX_TURBO + self::subida_escalon( $e['tick'] ),
					$e['vel'] + NavidadTVS_Sim_Constantes::IMPULSOR_MMS
				);
			} elseif ( NavidadTVS_Sim_Constantes::TIPO_ACEITE === $obs['tipo'] ) {
				$e['aceiteHasta'] = $obs['pos'] + $obs['largo'];
			} elseif ( NavidadTVS_Sim_Constantes::TIPO_CONO === $obs['tipo'] ) {
				$e['caido'] = NavidadTVS_Sim_Constantes::TICKS_CAIDA;
				$e['caidas']++;
				$e['vel'] = 0;
			}
		}

		// --- Llaves -----------------------------------------------------------
		$n_items = count( $pista->items );

		while ( $e['idxItem'] < $n_items && $pista->items[ $e['idxItem'] ]['pos'] <= $e['pos'] ) {
			$item = $pista->items[ $e['idxItem'] ];
			$e['idxItem']++;

			if ( $item['carril'] === $e['carril'] ) {
				$e['items']++;
			}
		}
	}

	/**
	 * Corre una carrera entera a partir del registro de entradas.
	 *
	 * Esto es lo que decide el score del concurso. Lo que el participante vio
	 * en su pantalla es cosmético hasta que sale de aquí.
	 *
	 * @param int   $seed        Semilla de la pista.
	 * @param array $entradas    Un byte por tick.
	 * @param int   $total_ticks Ticks de la carrera.
	 * @return array Estado final.
	 */
	public static function simular( $seed, array $entradas, $total_ticks ) {
		$pista  = NavidadTVS_Sim_Pista::generar( $seed );
		$estado = self::crear_estado();

		for ( $t = 0; $t < $total_ticks; $t++ ) {
			self::paso( $estado, isset( $entradas[ $t ] ) ? $entradas[ $t ] : 0, $pista );
		}

		return $estado;
	}
}
