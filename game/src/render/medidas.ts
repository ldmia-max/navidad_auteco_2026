/**
 * Medidas de la pantalla.
 *
 * Viven aparte de texturas.ts porque ese módulo importa Phaser, y el banco de
 * pruebas del arte corre en Node, donde Phaser no arranca por falta de DOM.
 * Las comprobaciones necesitan estos números para no inventarse umbrales.
 */

export const ANCHO = 320;
export const ALTO = 180;

/** Dónde empieza la pista en vertical. */
export const PISTA_Y = 82;

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
export const MOTO_X = 74;

/** Píxeles por metro al dibujar la pista. */
export const PX_POR_METRO = 8;

/**
 * Milímetros de pista visibles por delante de la moto.
 *
 * Es el presupuesto de reacción del jugador: todo lo que aparece, aparece a
 * esta distancia. Subir la velocidad sin subir esto recorta el tiempo que hay
 * para ver un cono y esquivarlo, así que cualquier cambio de velocidad se
 * comprueba contra este número.
 */
export const VISTA_MM = ((ANCHO - MOTO_X) * 1000) / PX_POR_METRO;
