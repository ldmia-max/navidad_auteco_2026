/**
 * Exporta los vectores de paridad TypeScript → PHP.
 *
 *   cd game && npm run sim:vectores
 *
 * Escribe dev/vectores-paridad.json con carreras completas ya resueltas por
 * esta implementación: las entradas que se pulsaron y el estado final que
 * salió. El puerto PHP corre las mismas entradas y compara.
 *
 * HAY QUE REGENERARLO cada vez que cambie la física, y correr después la suite
 * de PHP. Si los dos lados se separan, el validador de E6 empieza a rechazar
 * carreras legítimas y nadie se entera hasta que un ganador reclama.
 *
 * El archivo lleva también los valores de las constantes. No es redundante: es
 * la única forma de que el lado PHP detecte que alguien cambió un número aquí
 * y olvidó copiarlo, que es la manera realista de que esto se rompa.
 */

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

import * as C from './constantes';
import { BIT_ABAJO, BIT_ACELERA, BIT_ARRIBA, BIT_TURBO, codificar, registroVacio } from './entradas';
import { generarPista, type Pista } from './pista';
import { crearEstado, distanciaMetros, paso, type Estado } from './simulacion';

/**
 * Huella de una pista.
 *
 * Comparar 271 obstáculos campo por campo en el archivo lo haría enorme y
 * difícil de leer. Con una huella de 32 bits basta: cualquier diferencia en
 * cualquier campo la cambia, y portarla a PHP es una línea.
 *
 * Aritmética de 32 bits sin signo, igual que el PRNG, para que el puerto PHP
 * pueda reproducirla exactamente.
 */
function huellaPista(pista: Pista): number {
  let h = 2166136261 >>> 0;

  const mezclar = (v: number) => {
    h = (Math.imul(h, 31) + v) >>> 0;
  };

  mezclar(pista.obstaculos.length);
  for (const o of pista.obstaculos) {
    mezclar(o.pos);
    mezclar(o.carril);
    mezclar(o.tipo);
    mezclar(o.largo);
  }

  mezclar(pista.items.length);
  for (const i of pista.items) {
    mezclar(i.pos);
    mezclar(i.carril);
  }

  return h;
}

/** Cada cuántos ticks se anota la posición. */
const PASO_MUESTRA = 300;

interface Estrategia {
  nombre: string;
  fn: (tick: number) => number;
}

/*
 * Las estrategias tienen que tocar todas las ramas de la simulación, no solo
 * las bonitas. Las cuatro primeras son las del balanceo; las dos últimas
 * existen porque ninguna de esas cambia de carril, y sin cambiar de carril no
 * se recogen llaves, no se pisan la mitad de los obstáculos y la espera entre
 * cambios no se ejercita nunca.
 */
const ESTRATEGIAS: Estrategia[] = [
  { nombre: 'sin tocar nada', fn: () => 0 },
  { nombre: 'solo acelerador', fn: () => BIT_ACELERA },
  { nombre: 'turbo continuo', fn: () => BIT_ACELERA | BIT_TURBO },
  {
    nombre: 'pulsos 2 s turbo / 3 s normal',
    fn: (t) => (t % (5 * C.TPS) < 2 * C.TPS ? BIT_ACELERA | BIT_TURBO : BIT_ACELERA),
  },
  {
    nombre: 'zigzag acelerando',
    fn: (t) => {
      const fase = Math.floor(t / 40) % 2;
      return BIT_ACELERA | (fase === 0 ? BIT_ARRIBA : BIT_ABAJO);
    },
  },
  {
    /*
     * Patrón que enciende y apaga los cuatro bits en ciclos de distinto largo,
     * así que pasa por combinaciones que ninguna persona pulsaría —arriba y
     * abajo a la vez, turbo sin acelerador— y comprueba que las dos
     * implementaciones las resuelven igual.
     */
    nombre: 'errático',
    fn: (t) =>
      (t % 7 < 4 ? BIT_ACELERA : 0) |
      (t % 11 < 5 ? BIT_TURBO : 0) |
      (t % 53 < 9 ? BIT_ARRIBA : 0) |
      (t % 37 < 9 ? BIT_ABAJO : 0),
  },
];

const SEEDS = [0, 1, 999, 4242, 123456789, 4294967295];

function registroDe(fn: (t: number) => number): Uint8Array {
  const r = registroVacio();
  for (let t = 0; t < C.TOTAL_TICKS; t++) {
    r[t] = fn(t);
  }
  return r;
}

const carreras: unknown[] = [];

for (const seed of SEEDS) {
  const pista = generarPista(seed);

  for (const e of ESTRATEGIAS) {
    const registro = registroDe(e.fn);
    const estado: Estado = crearEstado();
    const muestras: number[] = [];

    for (let t = 0; t < C.TOTAL_TICKS; t++) {
      paso(estado, registro[t], pista);

      if (estado.tick % PASO_MUESTRA === 0) {
        muestras.push(estado.pos);
      }
    }

    carreras.push({
      seed,
      estrategia: e.nombre,
      entradas: codificar(registro),
      distancia: distanciaMetros(estado),
      estado: { ...estado },
      muestras,
    });
  }
}

const vectores = {
  _ayuda:
    'Vectores de paridad TypeScript -> PHP. Generado por npm run sim:vectores. ' +
    'No se edita a mano: se regenera cuando cambia la fisica y se corre dev/verificar-e6.php.',
  generadoEn: new Date().toISOString(),
  pasoMuestra: PASO_MUESTRA,

  /*
   * Los números que las dos implementaciones tienen que compartir. El lado PHP
   * los compara contra los suyos antes de correr nada: si difieren, el fallo
   * es un número descuadrado y no un error de lógica, y conviene que el
   * mensaje lo diga.
   */
  constantes: {
    TPS: C.TPS,
    TOTAL_TICKS: C.TOTAL_TICKS,
    V_MAX_NORMAL: C.V_MAX_NORMAL,
    V_MAX_TURBO: C.V_MAX_TURBO,
    ACEL_NORMAL: C.ACEL_NORMAL,
    ACEL_TURBO: C.ACEL_TURBO,
    FRICCION: C.FRICCION,
    TICKS_POR_ESCALON: C.TICKS_POR_ESCALON,
    ESCALONES_MAX: C.ESCALONES_MAX,
    SUBIDA_POR_ESCALON: C.SUBIDA_POR_ESCALON,
    DECEL_SOBRECALENTADO: C.DECEL_SOBRECALENTADO,
    TEMP_MAX: C.TEMP_MAX,
    TEMP_TURBO: C.TEMP_TURBO,
    TEMP_NORMAL: C.TEMP_NORMAL,
    TEMP_SUELTO: C.TEMP_SUELTO,
    TICKS_SOBRECALENTADO: C.TICKS_SOBRECALENTADO,
    TICKS_CAIDA: C.TICKS_CAIDA,
    IMPULSOR_MMS: C.IMPULSOR_MMS,
    CARRILES: C.CARRILES,
    TICKS_CAMBIO_CARRIL: C.TICKS_CAMBIO_CARRIL,
    ACEITE_POR_MIL: C.ACEITE_POR_MIL,
    ITEM_METROS: C.ITEM_METROS,
    IMPULSOR_METROS: C.IMPULSOR_METROS,
    ARRANQUE_LIMPIO_MM: C.ARRANQUE_LIMPIO_MM,
    PISTA_MM: C.PISTA_MM,
    DISTANCIA_MAXIMA_M: C.DISTANCIA_MAXIMA_M,
    TIPO_IMPULSOR: C.TIPO_IMPULSOR,
    TIPO_ACEITE: C.TIPO_ACEITE,
    TIPO_CONO: C.TIPO_CONO,
    LARGO_ACEITE: C.LARGO_ACEITE,
  },

  pistas: SEEDS.map((seed) => {
    const p = generarPista(seed);
    return {
      seed,
      obstaculos: p.obstaculos.length,
      items: p.items.length,
      huella: huellaPista(p),
      primerObstaculo: p.obstaculos.length > 0 ? p.obstaculos[0] : null,
      ultimoObstaculo: p.obstaculos.length > 0 ? p.obstaculos[p.obstaculos.length - 1] : null,
    };
  }),

  carreras,
};

const destino = resolve(import.meta.dirname, '../../../dev/vectores-paridad.json');
mkdirSync(dirname(destino), { recursive: true });
writeFileSync(destino, JSON.stringify(vectores, null, 1) + '\n', 'utf8');

console.log(`${carreras.length} carreras (${SEEDS.length} pistas × ${ESTRATEGIAS.length} estrategias)`);
console.log(`escritas en ${destino}`);
