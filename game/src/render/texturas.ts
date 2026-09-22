/**
 * Arte provisional, dibujado por código.
 *
 * E4 se ocupa de que el juego funcione; E5 lo reemplaza por pixel art de
 * verdad. Generar las texturas aquí evita meter binarios al repositorio antes
 * de tiempo y deja el bundle pequeño mientras se afina la jugabilidad.
 *
 * Los nombres de textura son el contrato con E5: al sustituir el arte, se
 * conservan y no hay que tocar la escena.
 */

import Phaser from 'phaser';
import { PAL } from './paleta';

export const TEX = {
  moto: 'moto',
  motoCaida: 'moto-caida',
  rampa: 'rampa',
  lodo: 'lodo',
  valla: 'valla',
  item: 'item-tvs',
  tribuna: 'tribuna',
  cerros: 'cerros',
  pista: 'pista',
  sombra: 'sombra',
} as const;

/** Pinta un rectángulo de un color plano. */
function rect(g: Phaser.GameObjects.Graphics, color: number, x: number, y: number, w: number, h: number): void {
  g.fillStyle(color, 1);
  g.fillRect(x, y, w, h);
}

export function crearTexturas(escena: Phaser.Scene): void {
  const g = escena.make.graphics({ x: 0, y: 0 }, false);

  // --- Moto: 16×12, mirando a la derecha ----------------------------------
  g.clear();
  rect(g, PAL.negro, 1, 8, 4, 4); // rueda trasera
  rect(g, PAL.negro, 11, 8, 4, 4); // rueda delantera
  rect(g, PAL.rojo, 3, 5, 10, 4); // chasis
  rect(g, PAL.azul, 6, 2, 5, 4); // piloto
  rect(g, PAL.crema, 7, 1, 3, 2); // casco
  rect(g, PAL.blanco, 12, 4, 2, 2); // manillar
  g.generateTexture(TEX.moto, 16, 12);

  // --- Moto caída ----------------------------------------------------------
  g.clear();
  rect(g, PAL.negro, 2, 9, 4, 3);
  rect(g, PAL.negro, 9, 9, 4, 3);
  rect(g, PAL.rojo, 3, 7, 10, 3);
  rect(g, PAL.azul, 1, 9, 5, 3); // piloto en el suelo, detrás
  rect(g, PAL.crema, 0, 8, 3, 2);
  g.generateTexture(TEX.motoCaida, 16, 12);

  // --- Rampa: 20×14 --------------------------------------------------------
  g.clear();
  g.fillStyle(PAL.pistaBorde, 1);
  g.fillTriangle(0, 14, 20, 0, 20, 14);
  g.fillStyle(PAL.pista, 1);
  g.fillTriangle(2, 14, 18, 2, 18, 14);
  g.generateTexture(TEX.rampa, 20, 14);

  // --- Lodo: 24×10 ---------------------------------------------------------
  g.clear();
  rect(g, PAL.lodo, 0, 0, 24, 10);
  for (let i = 0; i < 24; i += 6) {
    rect(g, PAL.lodoClaro, i + 1, 2, 3, 2);
    rect(g, PAL.lodoClaro, i + 3, 6, 2, 2);
  }
  g.generateTexture(TEX.lodo, 24, 10);

  // --- Valla: 12×14 --------------------------------------------------------
  g.clear();
  rect(g, PAL.blanco, 0, 0, 12, 14);
  rect(g, PAL.rojo, 0, 0, 4, 14);
  rect(g, PAL.rojo, 8, 0, 4, 14);
  rect(g, PAL.negro, 0, 12, 12, 2);
  g.generateTexture(TEX.valla, 12, 14);

  // --- Logo TVS: 12×12 -----------------------------------------------------
  // Provisional: la T del logo en azul sobre blanco. En E5 entra el SVG real.
  g.clear();
  rect(g, PAL.blanco, 0, 0, 12, 12);
  rect(g, PAL.azul, 0, 0, 12, 2);
  rect(g, PAL.azul, 0, 10, 12, 2);
  rect(g, PAL.azul, 2, 3, 8, 2); // travesaño de la T
  rect(g, PAL.azul, 5, 3, 2, 6); // asta de la T
  g.generateTexture(TEX.item, 12, 12);

  // --- Sombra bajo la moto en el aire --------------------------------------
  g.clear();
  g.fillStyle(PAL.negro, 0.35);
  g.fillEllipse(6, 2, 12, 4);
  g.generateTexture(TEX.sombra, 12, 4);

  // --- Tribuna: franja de 320×34, se repite en horizontal ------------------
  g.clear();
  rect(g, PAL.verdeOscuro, 0, 0, 320, 34);
  // Público: filas de puntos de colores.
  const coloresPublico = [PAL.rojo, PAL.crema, PAL.azul, PAL.verdeClaro, PAL.blanco];
  for (let fila = 0; fila < 3; fila++) {
    for (let x = 0; x < 320; x += 6) {
      const color = coloresPublico[(x / 6 + fila * 2) % coloresPublico.length];
      rect(g, color, x + (fila % 2), 4 + fila * 7, 3, 4);
    }
  }
  rect(g, PAL.verde, 0, 30, 320, 4);
  g.generateTexture(TEX.tribuna, 320, 34);

  // --- Cerros del fondo: 320×24 -------------------------------------------
  g.clear();
  rect(g, PAL.cielo, 0, 0, 320, 24);
  g.fillStyle(PAL.verde, 1);
  for (let x = 0; x < 340; x += 40) {
    g.fillTriangle(x, 24, x + 20, 6, x + 40, 24);
  }
  g.generateTexture(TEX.cerros, 320, 24);

  // --- Pista: 48×52, se repite en horizontal -------------------------------
  // Cuatro carriles de 13 px, con las líneas discontinuas entre ellos.
  g.clear();
  for (let carril = 0; carril < 4; carril++) {
    const color = carril % 2 === 0 ? PAL.pista : PAL.pistaAlt;
    rect(g, color, 0, carril * 13, 48, 13);
  }
  g.fillStyle(PAL.crema, 0.55);
  for (let carril = 1; carril < 4; carril++) {
    g.fillRect(0, carril * 13 - 1, 24, 1);
  }
  g.generateTexture(TEX.pista, 48, 52);

  g.destroy();
}
