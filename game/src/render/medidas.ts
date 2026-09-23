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
