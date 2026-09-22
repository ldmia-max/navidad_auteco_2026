/**
 * Paleta del juego.
 *
 * Los tres primeros valores son los del manual de marca TVS. El resto se
 * deriva de ellos y se mantiene corto a propósito: parte de lo que hace que
 * algo se vea de 16 bits es que haya pocos colores, no muchos.
 *
 * En E5 esto pasa a theme.json para poder ajustarlo sin recompilar.
 */
export const PAL = {
  azul: 0x1f3a72,
  rojo: 0xe01d2d,
  blanco: 0xffffff,

  azulOscuro: 0x12234a,
  azulProfundo: 0x0b1730,
  cielo: 0x4a90d9,
  cieloAlto: 0x2f6fb5,

  verde: 0x3aa93a,
  verdeOscuro: 0x227722,
  verdeClaro: 0x5fc75f,

  pista: 0xd98a4a,
  pistaAlt: 0xc97a3d,
  pistaBorde: 0x8a5228,

  lodo: 0x5a3a20,
  lodoClaro: 0x7a5230,

  crema: 0xf2e9d8,
  gris: 0x6b7f9e,
  negro: 0x000000,

  tempFria: 0x3aa93a,
  tempCaliente: 0xe01d2d,
} as const;

/** Convierte un color de Phaser a la cadena que usan los objetos de texto. */
export function css(color: number): string {
  return '#' + color.toString(16).padStart(6, '0');
}
