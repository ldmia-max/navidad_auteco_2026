/**
 * Tipografía de píxeles, 5×7.
 *
 * El juego no usa fuentes del sistema. Una fuente del sistema se reescala con
 * suavizado y rompe el aspecto de 16 bits; además cada dispositivo tiene la
 * suya, así que el mismo texto ocuparía distinto ancho en cada teléfono y el
 * panel se descuadraría.
 *
 * Solo mayúsculas, dígitos y unos pocos signos: es todo lo que aparece dentro
 * del lienzo. Los textos con tildes viven en el HTML, donde sí hay tipografía
 * de verdad.
 */

import Phaser from 'phaser';
import type { Paleta } from './tema';

export const ANCHO_GLIFO = 5;
export const ALTO_GLIFO = 7;
/** Separación entre caracteres. */
export const AVANCE = ANCHO_GLIFO + 1;

type Glifo = readonly string[];

const GLIFOS: Record<string, Glifo> = {
  ' ': ['.....', '.....', '.....', '.....', '.....', '.....', '.....'],

  '0': ['.###.', '#...#', '#..##', '#.#.#', '##..#', '#...#', '.###.'],
  '1': ['..#..', '.##..', '..#..', '..#..', '..#..', '..#..', '.###.'],
  '2': ['.###.', '#...#', '....#', '...#.', '..#..', '.#...', '#####'],
  '3': ['#####', '...#.', '..#..', '...#.', '....#', '#...#', '.###.'],
  '4': ['...#.', '..##.', '.#.#.', '#..#.', '#####', '...#.', '...#.'],
  '5': ['#####', '#....', '####.', '....#', '....#', '#...#', '.###.'],
  '6': ['..##.', '.#...', '#....', '####.', '#...#', '#...#', '.###.'],
  '7': ['#####', '....#', '...#.', '..#..', '.#...', '.#...', '.#...'],
  '8': ['.###.', '#...#', '#...#', '.###.', '#...#', '#...#', '.###.'],
  '9': ['.###.', '#...#', '#...#', '.####', '....#', '...#.', '.##..'],

  A: ['.###.', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
  B: ['####.', '#...#', '#...#', '####.', '#...#', '#...#', '####.'],
  C: ['.###.', '#...#', '#....', '#....', '#....', '#...#', '.###.'],
  D: ['###..', '#..#.', '#...#', '#...#', '#...#', '#..#.', '###..'],
  E: ['#####', '#....', '#....', '####.', '#....', '#....', '#####'],
  F: ['#####', '#....', '#....', '####.', '#....', '#....', '#....'],
  G: ['.###.', '#...#', '#....', '#.###', '#...#', '#...#', '.###.'],
  H: ['#...#', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
  I: ['.###.', '..#..', '..#..', '..#..', '..#..', '..#..', '.###.'],
  J: ['..###', '...#.', '...#.', '...#.', '...#.', '#..#.', '.##..'],
  K: ['#...#', '#..#.', '#.#..', '##...', '#.#..', '#..#.', '#...#'],
  L: ['#....', '#....', '#....', '#....', '#....', '#....', '#####'],
  M: ['#...#', '##.##', '#.#.#', '#.#.#', '#...#', '#...#', '#...#'],
  N: ['#...#', '##..#', '#.#.#', '#..##', '#...#', '#...#', '#...#'],
  O: ['.###.', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
  P: ['####.', '#...#', '#...#', '####.', '#....', '#....', '#....'],
  Q: ['.###.', '#...#', '#...#', '#...#', '#.#.#', '#..#.', '.##.#'],
  R: ['####.', '#...#', '#...#', '####.', '#.#..', '#..#.', '#...#'],
  S: ['.####', '#....', '#....', '.###.', '....#', '....#', '####.'],
  T: ['#####', '..#..', '..#..', '..#..', '..#..', '..#..', '..#..'],
  U: ['#...#', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
  V: ['#...#', '#...#', '#...#', '#...#', '#...#', '.#.#.', '..#..'],
  W: ['#...#', '#...#', '#...#', '#.#.#', '#.#.#', '##.##', '#...#'],
  X: ['#...#', '#...#', '.#.#.', '..#..', '.#.#.', '#...#', '#...#'],
  Y: ['#...#', '#...#', '.#.#.', '..#..', '..#..', '..#..', '..#..'],
  Z: ['#####', '....#', '...#.', '..#..', '.#...', '#....', '#####'],

  Ñ: ['.###.', '.....', '#...#', '##..#', '#.#.#', '#..##', '#...#'],

  ':': ['.....', '..#..', '..#..', '.....', '..#..', '..#..', '.....'],
  '.': ['.....', '.....', '.....', '.....', '.....', '..#..', '..#..'],
  ',': ['.....', '.....', '.....', '.....', '..#..', '..#..', '.#...'],
  '!': ['..#..', '..#..', '..#..', '..#..', '..#..', '.....', '..#..'],
  '?': ['.###.', '#...#', '....#', '...#.', '..#..', '.....', '..#..'],
  '-': ['.....', '.....', '.....', '#####', '.....', '.....', '.....'],
  '/': ['....#', '....#', '...#.', '..#..', '.#...', '#....', '#....'],
  '+': ['.....', '..#..', '..#..', '#####', '..#..', '..#..', '.....'],
  '%': ['##..#', '##.#.', '..#..', '.#...', '#..##', '...##', '.....'],
  "'": ['..#..', '..#..', '.....', '.....', '.....', '.....', '.....'],
};

/** Glifo de reemplazo cuando llega un carácter que no existe. */
const DESCONOCIDO: Glifo = ['#####', '#...#', '#...#', '#...#', '#...#', '#...#', '#####'];

/**
 * Normaliza un texto a lo que la fuente sabe pintar.
 *
 * Las tildes se quitan en vez de dibujar un cuadro: "PARTICIPACIÓN" se lee
 * perfectamente como "PARTICIPACION", y un cuadro en medio de una palabra
 * parece un error.
 */
export function normalizar(texto: string): string {
  return texto
    .toUpperCase()
    .replace(/[ÁÀÄÂ]/g, 'A')
    .replace(/[ÉÈËÊ]/g, 'E')
    .replace(/[ÍÌÏÎ]/g, 'I')
    .replace(/[ÓÒÖÔ]/g, 'O')
    .replace(/[ÚÙÜÛ]/g, 'U')
    .replace(/[¡¿]/g, '');
}

/** Ancho en píxeles que ocupará un texto. */
export function anchoTexto(texto: string): number {
  const limpio = normalizar(texto);
  return limpio.length === 0 ? 0 : limpio.length * AVANCE - 1;
}

/**
 * Pinta un texto en un Graphics.
 *
 * @param g      Destino.
 * @param texto  Texto a pintar.
 * @param x      Posición izquierda.
 * @param y      Posición superior.
 * @param color  Color ya resuelto.
 */
export function pintarTexto(
  g: Phaser.GameObjects.Graphics,
  texto: string,
  x: number,
  y: number,
  color: number
): void {
  const limpio = normalizar(texto);
  g.fillStyle(color, 1);

  for (let i = 0; i < limpio.length; i++) {
    const glifo = GLIFOS[limpio[i]] ?? DESCONOCIDO;
    const gx = x + i * AVANCE;

    for (let fy = 0; fy < glifo.length; fy++) {
      const fila = glifo[fy];
      for (let fx = 0; fx < fila.length; fx++) {
        if (fila[fx] === '#') {
          g.fillRect(gx + fx, y + fy, 1, 1);
        }
      }
    }
  }
}

/**
 * Objeto de texto de píxeles que se puede actualizar en cada cuadro.
 *
 * Los contadores del panel cambian sesenta veces por segundo. Repintar un
 * Graphics es barato; crear objetos de texto de Phaser cada vez, no.
 */
export class TextoPixel {
  private g: Phaser.GameObjects.Graphics;
  private ultimo = '\u0000';

  constructor(
    escena: Phaser.Scene,
    private x: number,
    private y: number,
    private color: number,
    private alineacion: 'izquierda' | 'centro' | 'derecha' = 'izquierda'
  ) {
    this.g = escena.add.graphics();
  }

  set(texto: string): void {
    if (texto === this.ultimo) {
      return;
    }
    this.ultimo = texto;

    this.g.clear();

    const ancho = anchoTexto(texto);
    const x =
      this.alineacion === 'centro'
        ? this.x - Math.floor(ancho / 2)
        : this.alineacion === 'derecha'
          ? this.x - ancho
          : this.x;

    pintarTexto(this.g, texto, x, this.y, this.color);
  }

  setVisible(visible: boolean): void {
    this.g.setVisible(visible);
  }

  setDepth(profundidad: number): void {
    this.g.setDepth(profundidad);
  }
}

/**
 * Crea una textura con un texto ya pintado.
 *
 * Para rótulos que no cambian nunca, como DIST o TEMP.
 */
export function texturaTexto(
  escena: Phaser.Scene,
  nombre: string,
  texto: string,
  paleta: Paleta,
  color: keyof Paleta
): { ancho: number; alto: number } {
  const ancho = Math.max(1, anchoTexto(texto));
  const g = escena.make.graphics({ x: 0, y: 0 }, false);

  pintarTexto(g, texto, 0, 0, paleta[color]);
  g.generateTexture(nombre, ancho, ALTO_GLIFO);
  g.destroy();

  return { ancho, alto: ALTO_GLIFO };
}
