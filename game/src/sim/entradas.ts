/**
 * Entradas del jugador y su registro.
 *
 * El cliente no envía el score: envía esto. El servidor reejecuta la carrera
 * con el mismo seed y las mismas entradas, y calcula él la distancia.
 *
 * Un byte por tick, con un bit por botón. 5400 bytes por carrera que, tras
 * comprimir por repeticiones, se quedan muy por debajo de 1 KB: los botones
 * cambian pocas veces por segundo, no sesenta.
 */

import { TOTAL_TICKS } from './constantes';

export const BIT_ACELERA = 1 << 0;
export const BIT_TURBO = 1 << 1;

/*
 * Arriba y abajo valen para dos cosas según dónde esté la moto: en el suelo
 * cambian de carril, en el aire enderezan. El registro guarda lo que el
 * jugador pulsó, no lo que eso significaba en ese instante; interpretarlo es
 * trabajo de la simulación, y así el mismo registro vale para reejecutar.
 */
export const BIT_ARRIBA = 1 << 2;
export const BIT_ABAJO = 1 << 3;

/** Bits 4 a 7 reservados. Si se usan, hay que actualizar también el puerto PHP. */

export function tiene(entrada: number, bit: number): boolean {
  return (entrada & bit) !== 0;
}

/**
 * Comprime el registro por repeticiones y lo codifica en base64.
 *
 * Formato: pares (valor, repeticiones), con repeticiones entre 1 y 255.
 */
export function codificar(registro: Uint8Array): string {
  const pares: number[] = [];

  let i = 0;
  while (i < registro.length) {
    const valor = registro[i];
    let repes = 1;

    while (i + repes < registro.length && registro[i + repes] === valor && repes < 255) {
      repes++;
    }

    pares.push(valor, repes);
    i += repes;
  }

  let binario = '';
  for (const b of pares) {
    binario += String.fromCharCode(b);
  }

  return btoa(binario);
}

/**
 * Deshace codificar(). Existe para poder comprobar el viaje de ida y vuelta
 * antes de enviar: si el registro no se reconstruye igual, no tiene sentido
 * mandarlo.
 */
export function decodificar(texto: string): Uint8Array {
  const binario = atob(texto);
  const salida: number[] = [];

  for (let i = 0; i + 1 < binario.length; i += 2) {
    const valor = binario.charCodeAt(i);
    const repes = binario.charCodeAt(i + 1);

    for (let r = 0; r < repes; r++) {
      salida.push(valor);
    }
  }

  return Uint8Array.from(salida);
}

/** Registro vacío del largo exacto de una carrera. */
export function registroVacio(): Uint8Array {
  return new Uint8Array(TOTAL_TICKS);
}
