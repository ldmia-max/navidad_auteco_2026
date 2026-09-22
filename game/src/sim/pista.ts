/**
 * Generación de la pista a partir del seed.
 *
 * La pista se calcula entera de una vez, antes de arrancar. Como la moto solo
 * avanza, la simulación la recorre con un índice que nunca retrocede: no hace
 * falta buscar nada en cada tick.
 *
 * Regla que no se puede romper: en ninguna posición pueden quedar los cuatro
 * carriles bloqueados. Si el jugador no tiene salida, la caída no es culpa
 * suya y el concurso deja de medir habilidad.
 */

import {
  ARRANQUE_LIMPIO_MM,
  CARRILES,
  LARGO_LODO,
  PISTA_MM,
  TIPO_LODO,
  TIPO_RAMPA,
  TIPO_VALLA,
  div,
} from './constantes';
import { Prng } from './prng';

export interface Obstaculo {
  pos: number;
  carril: number;
  tipo: number;
  largo: number;
}

export interface ItemPista {
  pos: number;
  carril: number;
}

export interface Pista {
  obstaculos: Obstaculo[];
  items: ItemPista[];
}

/** Separación mínima y máxima entre grupos de obstáculos, en mm. */
const GAP_MIN = 18_000;
const GAP_MAX = 45_000;

/** Separación entre logos TVS. Promedio ~200 m, como dice el balanceo. */
const GAP_ITEM_MIN = 150_000;
const GAP_ITEM_MAX = 250_000;

/**
 * Construye la pista de un seed.
 *
 * Determinista: el mismo seed da siempre exactamente la misma pista, en
 * TypeScript y en PHP.
 */
export function generarPista(seed: number): Pista {
  const prng = new Prng(seed);
  const obstaculos: Obstaculo[] = [];
  const items: ItemPista[] = [];

  // --- Obstáculos ---------------------------------------------------------
  let cursor = ARRANQUE_LIMPIO_MM;

  while (cursor < PISTA_MM) {
    cursor += prng.rango(GAP_MIN, GAP_MAX);

    if (cursor >= PISTA_MM) {
      break;
    }

    /*
     * La dificultad sube con la distancia: al principio casi todo son rampas
     * y lodo, y las vallas aparecen más adelante. Así los primeros segundos
     * no castigan a quien nunca ha jugado.
     */
    const progresoPorMil = div(cursor * 1000, PISTA_MM);
    const pesoValla = 100 + div(progresoPorMil * 250, 1000); // 10 % → 35 %
    const pesoLodo = 350;

    const dado = prng.rango(0, 999);
    let tipo: number;

    if (dado < pesoValla) {
      tipo = TIPO_VALLA;
    } else if (dado < pesoValla + pesoLodo) {
      tipo = TIPO_LODO;
    } else {
      tipo = TIPO_RAMPA;
    }

    const largo = tipo === TIPO_LODO ? LARGO_LODO : 0;

    /*
     * Un grupo ocupa uno o dos carriles. Nunca más: con cuatro carriles, dos
     * bloqueados dejan siempre dos salidas.
     */
    const cuantos = prng.probabilidad(300) ? 2 : 1;
    const primero = prng.rango(0, CARRILES - 1);

    obstaculos.push({ pos: cursor, carril: primero, tipo, largo });

    if (cuantos === 2) {
      // Se desplaza entre 1 y 3 carriles para no repetir el mismo.
      const segundo = (primero + prng.rango(1, CARRILES - 1)) % CARRILES;
      obstaculos.push({ pos: cursor, carril: segundo, tipo, largo });
    }
  }

  // --- Logos TVS -----------------------------------------------------------
  let cursorItem = div(ARRANQUE_LIMPIO_MM, 2);

  while (cursorItem < PISTA_MM) {
    cursorItem += prng.rango(GAP_ITEM_MIN, GAP_ITEM_MAX);

    if (cursorItem >= PISTA_MM) {
      break;
    }

    items.push({ pos: cursorItem, carril: prng.rango(0, CARRILES - 1) });
  }

  return { obstaculos, items };
}
