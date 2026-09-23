/**
 * Constantes de la simulación.
 *
 * Espejo exacto de docs/mecanica-y-balanceo.md. Cualquier número que cambie
 * aquí tiene que cambiar también en el puerto PHP de E6, y después hay que
 * correr la suite de paridad. Si los dos lados se separan, el validador
 * empieza a rechazar carreras legítimas.
 *
 * Todo en enteros. Ni un flotante en el bucle de simulación.
 */

/** Ticks por segundo. La física corre a paso fijo, desacoplada del render. */
export const TPS = 60;

/** Duración de la carrera. 90 s × 60 ticks. */
export const TOTAL_TICKS = 5400;

// ---------------------------------------------------------------------------
// Velocidad. Unidad: mm/s. Aceleración: mm/s por segundo.
// ---------------------------------------------------------------------------

export const V_MAX_NORMAL = 22000; //  22 m/s ≈  79 km/h
export const V_MAX_TURBO = 32000; //  32 m/s ≈ 115 km/h
export const ACEL_NORMAL = 12000;
export const ACEL_TURBO = 18000;
export const FRICCION = 8000;

/** Al calarse el motor la moto pierde potencia rápido. */
export const DECEL_SOBRECALENTADO = 40000;

// ---------------------------------------------------------------------------
// Temperatura del motor. Escala 0..10000.
// ---------------------------------------------------------------------------

export const TEMP_MAX = 10000;

/** Con turbo: 0 → 10000 en unos 4,0 s. */
export const TEMP_TURBO = 42;

/** Con acelerador normal: 10000 → 0 en unos 6,0 s. */
export const TEMP_NORMAL = -28;

/** Sin acelerar: 10000 → 0 en unos 3,0 s. */
export const TEMP_SUELTO = -56;

/** La parada por sobrecalentamiento dura 2,5 s. */
export const TICKS_SOBRECALENTADO = 150;

// ---------------------------------------------------------------------------
// Caídas
// ---------------------------------------------------------------------------

/** 2 s en el suelo. A 22 m/s son 44 metros perdidos, más volver a acelerar. */
export const TICKS_CAIDA = 120;

// ---------------------------------------------------------------------------
// Saltos. Altura en mm, velocidad vertical en mm/s, inclinación en decigrados.
// ---------------------------------------------------------------------------

export const GRAVEDAD = 25000;

/** Impulso vertical al pisar una rampa, en milésimas de la velocidad. */
export const IMPULSO_POR_MIL = 260;

/**
 * Premio por aterrizar.
 *
 * El salto no tiene control de inclinación: la moto sale de la rampa girada,
 * se mantiene así todo el vuelo y cae de pie. Hubo una versión con ángulo de
 * aterrizaje, y se quitó porque el dibujo del salto tiene que verse fijo
 * durante el vuelo: si el ángulo no se puede ver, castigar por él produce
 * caídas que el jugador no entiende, y con un solo intento eso no se puede
 * permitir.
 */
export const BOOST_ATERRIZAJE = 1500;

// ---------------------------------------------------------------------------
// Pista
// ---------------------------------------------------------------------------

export const CARRILES = 4;

/** Ticks mínimos entre dos cambios de carril. */
export const TICKS_CAMBIO_CARRIL = 8;

/** El lodo deja la moto al 60 % mientras se está encima. */
export const LODO_POR_MIL = 600;

/** Metros que suma cada logo TVS recogido. */
export const ITEM_METROS = 50;

/**
 * Metros que suma cada rampa saltada.
 *
 * Es un premio simbólico, no una fuente de distancia: con treinta y pico de
 * rampas en una carrera buena son unos 35 m sobre más de dos mil. Sirve para
 * que buscar la rampa se sienta recompensado además de por el impulso del
 * aterrizaje, y para que el "+1" que sale volando le diga al jugador que el
 * salto contó.
 */
export const RAMPA_METROS = 1;

/**
 * Tramo limpio del arranque.
 *
 * El primer obstáculo no cae aquí sino tras el primer hueco, que mide entre 18
 * y 45 m: con 30 m de arranque limpio, aparece entre los 48 y los 75 m. Y
 * arrancando de cero la moto está en 45 m al tercer segundo y en 67 al cuarto,
 * así que el primer obstáculo llega entre el segundo 3 y el 4.
 *
 * Los 300 m de la primera versión dejaban trece segundos sin que pasara nada,
 * que en una carrera de noventa es una eternidad.
 */
export const ARRANQUE_LIMPIO_MM = 30_000;

/**
 * Cuánta pista se genera. Por encima del tope de plausibilidad (3100 m) para
 * que nadie se quede sin pista aunque haga una carrera perfecta.
 */
export const PISTA_MM = 3_600_000;

// ---------------------------------------------------------------------------
// Tipos de obstáculo
// ---------------------------------------------------------------------------

export const TIPO_RAMPA = 1;
export const TIPO_LODO = 2;
export const TIPO_VALLA = 3;

/** Largo del tramo embarrado, en mm. */
export const LARGO_LODO = 4_000;

/**
 * División entera truncada hacia cero.
 *
 * El operador / de JavaScript devuelve flotante. En 5400 ticks el error se
 * acumula y la reejecución en PHP deja de coincidir. Math.trunc se comporta
 * igual que intdiv() de PHP, incluso con negativos.
 */
export function div(a: number, b: number): number {
  return Math.trunc(a / b);
}
