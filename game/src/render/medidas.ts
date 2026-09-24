/**
 * Medidas de la pantalla.
 *
 * Viven aparte de texturas.ts porque ese módulo importa Phaser, y el banco de
 * pruebas del arte corre en Node, donde Phaser no arranca por falta de DOM.
 * Las comprobaciones necesitan estos números para no inventarse umbrales.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ EL LIENZO ES CUADRADO Y PEQUEÑO
 *
 * Antes era de 320×180. En un teléfono en vertical eso quedaba en una franja
 * de 320 píxeles CSS de ancho —el escalado es por múltiplos enteros y 320 no
 * cabe dos veces en una pantalla de 412— así que el juego se veía como una
 * rendija y la moto ocupaba el 7 % del ancho. Con 200×200 el mismo teléfono
 * escala a ×2: el doble de tamaño de píxel y cuatro veces el área.
 *
 * Al ser cuadrado, además, ya no hace falta girar el teléfono: el juego cabe
 * arriba y los controles debajo, que es como se juega de verdad en un móvil.
 *
 * Lo que NO cambió es cuánta pista se ve por delante. Es el presupuesto de
 * reacción del jugador y todo el balanceo cuelga de él, así que al estrechar
 * el lienzo se bajó PX_POR_METRO en la misma proporción y VISTA_MM quedó
 * donde estaba.
 * ---------------------------------------------------------------------------
 */

export const ANCHO = 200;
export const ALTO = 200;

// ---------------------------------------------------------------------------
// Bandas horizontales, de arriba abajo.
//
// Están aquí y no repartidas entre texturas.ts y la escena porque una banda
// mal colocada se come a la de al lado: en la primera versión la tribuna
// empezaba dentro de los cerros y se comía los pinos enteros.
// ---------------------------------------------------------------------------

export const NUBES_Y = 0;
export const NUBES_ALTO = 32;

export const CERROS_Y = NUBES_Y + NUBES_ALTO;
export const CERROS_ALTO = 28;

export const TRIBUNA_Y = CERROS_Y + CERROS_ALTO;
export const TRIBUNA_ALTO = 28;

export const CESPED_Y = TRIBUNA_Y + TRIBUNA_ALTO;
export const CESPED_ALTO = 8;

/** Dónde empieza la pista. Todo lo de arriba es decorado. */
export const PISTA_Y = CESPED_Y + CESPED_ALTO;

/**
 * Alto de la pista y de cada carril.
 *
 * Carriles de 16 px: la moto mide 24 y así ocupa poco más de un carril, como
 * en el original. Con los 13 de la primera versión costaba saber en cuál iba.
 */
export const PISTA_ALTO = 64;
export const CARRILES_VISUALES = 4;
export const CARRIL_ALTO = PISTA_ALTO / CARRILES_VISUALES;

/** Panel inferior. */
export const PANEL_Y = PISTA_Y + PISTA_ALTO;
export const PANEL_ALTO = ALTO - PANEL_Y;

// ---------------------------------------------------------------------------
// Cámara
// ---------------------------------------------------------------------------

/**
 * Dónde va la moto en pantalla. Se queda quieta y el mundo se mueve.
 *
 * Está aquí y no en la escena porque de este número depende cuánta pista ve el
 * jugador, y de eso depende a qué velocidad se puede jugar. El banco de
 * pruebas lo necesita para medirlo.
 */
export const MOTO_X = 46;

/** Píxeles por metro al dibujar la pista. */
export const PX_POR_METRO = 5;

/**
 * Milímetros de pista visibles por delante de la moto.
 *
 * Es el presupuesto de reacción del jugador: todo lo que aparece, aparece a
 * esta distancia. Subir la velocidad sin subir esto recorta el tiempo que hay
 * para ver un cono y esquivarlo, así que cualquier cambio de velocidad —o del
 * tamaño del lienzo— se comprueba contra este número.
 */
export const VISTA_MM = ((ANCHO - MOTO_X) * 1000) / PX_POR_METRO;
