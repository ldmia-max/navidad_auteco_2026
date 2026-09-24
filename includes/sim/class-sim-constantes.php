<?php
/**
 * Constantes de la simulación.
 *
 * ESPEJO EXACTO de game/src/sim/constantes.ts. Cualquier número que cambie
 * allí tiene que cambiar aquí, y después hay que correr la suite de paridad:
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e6.php
 *
 * Si los dos lados se separan, el validador empieza a rechazar carreras
 * legítimas y nadie se entera hasta que un ganador reclama.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Números de la física. Todos enteros.
 */
class NavidadTVS_Sim_Constantes {

	const TPS         = 60;
	const TOTAL_TICKS = 5400;

	// Velocidad en mm/s, aceleración en mm/s por segundo.
	const V_MAX_NORMAL = 22000;
	const V_MAX_TURBO  = 32000;
	const ACEL_NORMAL  = 12000;
	const ACEL_TURBO   = 18000;
	const FRICCION     = 8000;

	// Escalada de velocidad: el techo sube cada 20 s, tres veces.
	const TICKS_POR_ESCALON  = 1200; // 20 * TPS
	const ESCALONES_MAX      = 3;
	const SUBIDA_POR_ESCALON = 3000;

	const DECEL_SOBRECALENTADO = 40000;

	// Temperatura del motor, escala 0..10000.
	const TEMP_MAX             = 10000;
	const TEMP_TURBO           = 42;
	const TEMP_NORMAL          = -28;
	const TEMP_SUELTO          = -56;
	const TICKS_SOBRECALENTADO = 150;

	const TICKS_CAIDA = 120;

	const IMPULSOR_MMS = 4000;

	const CARRILES            = 4;
	const TICKS_CAMBIO_CARRIL = 8;
	const ACEITE_POR_MIL      = 600;
	const ITEM_METROS         = 50;
	const IMPULSOR_METROS     = 1;

	const ARRANQUE_LIMPIO_MM = 30000;
	const PISTA_MM           = 4000000;

	/** Espejo de NAVIDAD_TVS_DISTANCIA_MAXIMA_M. La suite comprueba que coinciden. */
	const DISTANCIA_MAXIMA_M = 4200;

	// Tipos de obstáculo.
	const TIPO_IMPULSOR = 1;
	const TIPO_ACEITE   = 2;
	const TIPO_CONO     = 3;
	const LARGO_ACEITE  = 4000;

	// Generación de la pista.
	const GAP_MIN               = 11000;
	const GAP_MAX               = 26000;
	const SEPARACION_CONOS_MIN  = 30000;
	const SEPARACION_CONOS_MAX  = 42000;
	const ALCANCE_CARRERA_MM    = 2400000;
	const GAP_ITEM_MIN          = 150000;
	const GAP_ITEM_MAX          = 250000;
}
