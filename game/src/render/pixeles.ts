/**
 * Pixel art escrito como texto.
 *
 * Cada sprite se declara como un arreglo de filas donde cada carácter es un
 * color de la paleta. Se ve lo que se dibuja al leer el código, se versiona
 * como texto (un diff muestra qué píxel cambió) y no mete binarios al
 * repositorio.
 *
 * Los colores se nombran, no se escriben en hexadecimal: así el tema puede
 * recolorear todo el juego sin tocar un solo sprite.
 */

import Phaser from 'phaser';
import type { Paleta } from './tema';

/** Un sprite: filas de caracteres, todas del mismo largo. */
export type Sprite = readonly string[];

/**
 * Qué color representa cada carácter.
 *
 * Se eligieron letras que recuerdan al color para poder leer los sprites de un
 * vistazo. El punto es transparente.
 */
export const LEYENDA: Record<string, keyof Paleta | null> = {
  '.': null,
  n: 'negro',
  g: 'gris',
  G: 'grisClaro',
  w: 'blanco',
  c: 'crema',
  r: 'rojo',
  R: 'rojoOscuro',
  a: 'azul',
  A: 'azulClaro',
  d: 'azulProfundo',
  v: 'verde',
  V: 'verdeOscuro',
  L: 'verdeClaro',
  p: 'pista',
  P: 'pistaAlt',
  b: 'pistaBorde',
  m: 'lodo',
  M: 'lodoClaro',
  y: 'amarillo',
  k: 'piel',
  B: 'azulMoto',
  t: 'plata',
  l: 'azulLogo',
  s: 'cielo',
  S: 'cieloAlto',
};

/**
 * Comprueba que un sprite está bien formado.
 *
 * Una fila con un carácter de más descoloca todo el dibujo y el fallo es
 * difícil de ver leyendo. Más vale que salte aquí.
 *
 * @param nombre Nombre del sprite, para el mensaje de error.
 * @param filas  Filas del sprite.
 * @returns Ancho y alto, o lanza si algo no cuadra.
 */
export function validar(nombre: string, filas: Sprite): { ancho: number; alto: number } {
  if (filas.length === 0) {
    throw new Error(`Sprite "${nombre}": no tiene filas.`);
  }

  const ancho = filas[0].length;

  filas.forEach((fila, i) => {
    if (fila.length !== ancho) {
      throw new Error(
        `Sprite "${nombre}": la fila ${i} mide ${fila.length} y la primera mide ${ancho}.`
      );
    }

    for (const ch of fila) {
      if (!(ch in LEYENDA)) {
        throw new Error(`Sprite "${nombre}": el carácter "${ch}" no está en la leyenda.`);
      }
    }
  });

  return { ancho, alto: filas.length };
}

/**
 * Pinta un sprite en un Graphics, píxel a píxel.
 *
 * @param g       Destino.
 * @param filas   Sprite.
 * @param paleta  Colores del tema.
 * @param dx      Desplazamiento horizontal.
 * @param dy      Desplazamiento vertical.
 * @param escala  Tamaño de cada píxel del sprite.
 */
export function pintar(
  g: Phaser.GameObjects.Graphics,
  filas: Sprite,
  paleta: Paleta,
  dx = 0,
  dy = 0,
  escala = 1
): void {
  for (let y = 0; y < filas.length; y++) {
    const fila = filas[y];

    for (let x = 0; x < fila.length; x++) {
      const clave = LEYENDA[fila[x]];

      if (clave === null || clave === undefined) {
        continue;
      }

      g.fillStyle(paleta[clave], 1);
      g.fillRect(dx + x * escala, dy + y * escala, escala, escala);
    }
  }
}

/**
 * Convierte un sprite en una textura de Phaser.
 *
 * @param escena Escena donde registrar la textura.
 * @param nombre Clave de la textura.
 * @param filas  Sprite.
 * @param paleta Colores del tema.
 */
export function crearTextura(
  escena: Phaser.Scene,
  nombre: string,
  filas: Sprite,
  paleta: Paleta
): void {
  const { ancho, alto } = validar(nombre, filas);
  const g = escena.make.graphics({ x: 0, y: 0 }, false);

  pintar(g, filas, paleta);
  g.generateTexture(nombre, ancho, alto);
  g.destroy();
}
