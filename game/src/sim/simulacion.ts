/**
 * Simulación de la carrera.
 *
 * Es el corazón del concurso y no sabe nada de Phaser ni del navegador: entra
 * un estado y una entrada, sale el estado siguiente. Así puede correr igual en
 * el cliente para dibujar y en el servidor para validar.
 *
 * Reglas que sostienen todo:
 *
 * - Solo enteros. Las divisiones pasan por div(), que trunca hacia cero igual
 *   que intdiv() de PHP.
 * - Paso fijo de 60 ticks por segundo, siempre 5400 ticks. Un equipo a 144 Hz
 *   y un celular a 30 fps producen exactamente la misma carrera.
 * - Nada de aleatoriedad libre: la pista viene del seed del servidor.
 */

import {
  ACEITE_POR_MIL,
  ACEL_NORMAL,
  ACEL_TURBO,
  CARRILES,
  DECEL_SOBRECALENTADO,
  FRICCION,
  IMPULSOR_METROS,
  IMPULSOR_MMS,
  ITEM_METROS,
  TEMP_MAX,
  TEMP_NORMAL,
  TEMP_SUELTO,
  TEMP_TURBO,
  TICKS_CAIDA,
  TICKS_SOBRECALENTADO,
  TICKS_CAMBIO_CARRIL,
  TIPO_ACEITE,
  TIPO_CONO,
  TIPO_IMPULSOR,
  TPS,
  V_MAX_NORMAL,
  V_MAX_TURBO,
  div,
} from './constantes';
import { BIT_ABAJO, BIT_ACELERA, BIT_ARRIBA, BIT_TURBO, tiene } from './entradas';
import type { Pista } from './pista';

export interface Estado {
  tick: number;
  /** Posición recorrida, en mm. */
  pos: number;
  /** Velocidad actual, en mm/s. */
  vel: number;
  carril: number;
  /** Ticks que faltan para poder volver a cambiar de carril. */
  esperaCarril: number;
  /** Temperatura del motor, 0..10000. */
  temp: number;
  /** Ticks que faltan de motor sobrecalentado. */
  sobrecalentado: number;
  /** Ticks que faltan de caída. */
  caido: number;
  /** Llaves recogidas. */
  items: number;
  /** Impulsores pisados. */
  impulsores: number;
  /** Veces que se fue al suelo. */
  caidas: number;
  /** Veces que se caló el motor. */
  sobrecalentamientos: number;
  /** Hasta qué posición sigue habiendo aceite. */
  aceiteHasta: number;
  /** Índices de recorrido de la pista. Nunca retroceden. */
  idxObstaculo: number;
  idxItem: number;
}

export function crearEstado(): Estado {
  return {
    tick: 0,
    pos: 0,
    vel: 0,
    carril: 1,
    esperaCarril: 0,
    temp: 0,
    sobrecalentado: 0,
    caido: 0,
    items: 0,
    impulsores: 0,
    caidas: 0,
    sobrecalentamientos: 0,
    aceiteHasta: 0,
    idxObstaculo: 0,
    idxItem: 0,
  };
}

/** Distancia que se le muestra al participante y que decide el ranking. */
export function distanciaMetros(estado: Estado): number {
  return div(estado.pos, 1000) + estado.items * ITEM_METROS + estado.impulsores * IMPULSOR_METROS;
}

/** Segundos que quedan de carrera, redondeados hacia arriba. */
export function segundosRestantes(estado: Estado, totalTicks: number): number {
  const faltan = totalTicks - estado.tick;
  return faltan > 0 ? div(faltan + TPS - 1, TPS) : 0;
}

/**
 * Avanza la simulación un tick.
 *
 * Muta el estado a propósito: crear un objeto nuevo 5400 veces por carrera, y
 * otras tantas al validar en el servidor, no aporta nada.
 */
export function paso(estado: Estado, entrada: number, pista: Pista): void {
  estado.tick++;

  // --- Caído: no responde a nada ------------------------------------------
  if (estado.caido > 0) {
    estado.caido--;
    estado.vel = 0;
    return;
  }

  // --- Motor sobrecalentado: pierde potencia y se enfría ---------------------------
  if (estado.sobrecalentado > 0) {
    estado.sobrecalentado--;
    estado.vel = Math.max(0, estado.vel - div(DECEL_SOBRECALENTADO, TPS));
    estado.temp = 0;
    avanzar(estado, pista);
    return;
  }

  // --- Cambio de carril -----------------------------------------------------
  if (estado.esperaCarril > 0) {
    estado.esperaCarril--;
  }

  if (estado.esperaCarril === 0) {
    if (tiene(entrada, BIT_ARRIBA) && estado.carril > 0) {
      estado.carril--;
      estado.esperaCarril = TICKS_CAMBIO_CARRIL;
    } else if (tiene(entrada, BIT_ABAJO) && estado.carril < CARRILES - 1) {
      estado.carril++;
      estado.esperaCarril = TICKS_CAMBIO_CARRIL;
    }
  }

  // --- Motor ----------------------------------------------------------------
  let vmax: number;
  let acel: number;

  if (tiene(entrada, BIT_TURBO)) {
    vmax = V_MAX_TURBO;
    acel = ACEL_TURBO;
    estado.temp += TEMP_TURBO;
  } else if (tiene(entrada, BIT_ACELERA)) {
    vmax = V_MAX_NORMAL;
    acel = ACEL_NORMAL;
    estado.temp += TEMP_NORMAL;
  } else {
    vmax = 0;
    acel = 0;
    estado.temp += TEMP_SUELTO;
  }

  if (estado.temp < 0) {
    estado.temp = 0;
  }

  if (estado.temp >= TEMP_MAX) {
    estado.temp = 0;
    estado.sobrecalentado = TICKS_SOBRECALENTADO;
    estado.sobrecalentamientos++;
  }

  /*
   * Si se venía de turbo y ahora se acelera normal, la velocidad no cae de
   * golpe al nuevo techo: baja por fricción. Sin esto, soltar el turbo sería
   * un frenazo instantáneo y el juego se sentiría roto.
   */
  if (estado.vel > vmax) {
    estado.vel = Math.max(vmax, estado.vel - div(FRICCION, TPS));
  } else if (acel > 0) {
    estado.vel = Math.min(vmax, estado.vel + div(acel, TPS));
  }

  avanzar(estado, pista);
}

/**
 * Mueve la moto y resuelve lo que se encuentra por el camino.
 *
 * @param estado Estado a mutar.
 * @param pista  Pista generada del seed.
 */
function avanzar(estado: Estado, pista: Pista): void {
  // El aceite frena mientras se está encima, sin tocar la velocidad del motor.
  const enAceite = estado.pos < estado.aceiteHasta;
  const efectiva = enAceite ? div(estado.vel * ACEITE_POR_MIL, 1000) : estado.vel;

  estado.pos += div(efectiva, TPS);

  // --- Obstáculos -----------------------------------------------------------
  const obstaculos = pista.obstaculos;

  while (estado.idxObstaculo < obstaculos.length && obstaculos[estado.idxObstaculo].pos <= estado.pos) {
    const obs = obstaculos[estado.idxObstaculo];
    estado.idxObstaculo++;

    if (obs.carril !== estado.carril) {
      continue;
    }

    if (obs.tipo === TIPO_IMPULSOR) {
      estado.impulsores++;
      estado.vel = Math.min(V_MAX_TURBO, estado.vel + IMPULSOR_MMS);
    } else if (obs.tipo === TIPO_ACEITE) {
      estado.aceiteHasta = obs.pos + obs.largo;
    } else if (obs.tipo === TIPO_CONO) {
      estado.caido = TICKS_CAIDA;
      estado.caidas++;
      estado.vel = 0;
    }
  }

  // --- Llaves ---------------------------------------------------------------
  const items = pista.items;

  while (estado.idxItem < items.length && items[estado.idxItem].pos <= estado.pos) {
    const item = items[estado.idxItem];
    estado.idxItem++;

    if (item.carril === estado.carril) {
      estado.items++;
    }
  }
}

/**
 * Corre una carrera entera a partir del registro de entradas.
 *
 * Es lo que hará el servidor en E6. Tenerlo aquí permite comprobar en el banco
 * de pruebas que el resultado no depende de cómo se dibuje.
 */
export function simularCarrera(seed: number, entradas: Uint8Array, pista: Pista, totalTicks: number): Estado {
  const estado = crearEstado();

  for (let t = 0; t < totalTicks; t++) {
    paso(estado, entradas[t] ?? 0, pista);
  }

  void seed;
  return estado;
}
