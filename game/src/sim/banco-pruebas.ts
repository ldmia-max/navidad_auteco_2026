/**
 * Banco de pruebas de la simulación.
 *
 *   cd game && npm run sim
 *
 * Corre carreras completas con estrategias fijas y comprueba dos cosas:
 *
 * 1. Que la simulación es determinista y solo usa enteros.
 * 2. Que el balanceo de docs/mecanica-y-balanceo.md se cumple de verdad, en
 *    particular que abusar del turbo rinde MENOS que no usarlo.
 *
 * No depende de Phaser ni del navegador.
 */

import {
  ARRANQUE_LIMPIO_MM,
  DISTANCIA_MAXIMA_M,
  ESCALONES_MAX,
  IMPULSOR_METROS,
  ITEM_METROS,
  SUBIDA_POR_ESCALON,
  TICKS_POR_ESCALON,
  TIPO_CONO,
  TOTAL_TICKS,
  TPS,
  V_MAX_TURBO,
} from './constantes';
import { VISTA_MM } from '../render/medidas';
import { BIT_ACELERA, BIT_TURBO, codificar, decodificar, registroVacio } from './entradas';
import { generarPista } from './pista';
import { Prng } from './prng';
import { crearEstado, distanciaMetros, paso, subidaEscalon, type Estado } from './simulacion';

let fallos = 0;

function comprobar(etiqueta: string, condicion: boolean, detalle = ''): void {
  if (!condicion) {
    fallos++;
  }
  console.log(`${condicion ? 'OK   ' : 'FALLA'} ${etiqueta}${detalle ? '  ' + detalle : ''}`);
}

/** Genera el registro de entradas de una estrategia. */
function registroDe(estrategia: (tick: number) => number): Uint8Array {
  const registro = registroVacio();
  for (let t = 0; t < TOTAL_TICKS; t++) {
    registro[t] = estrategia(t);
  }
  return registro;
}

interface Resultado {
  distancia: number;
  items: number;
  impulsores: number;
  caidas: number;
  sobrecalentamientos: number;
  estado: Estado;
}

function correr(seed: number, registro: Uint8Array): Resultado {
  const pista = generarPista(seed);
  const estado = crearEstado();

  for (let t = 0; t < TOTAL_TICKS; t++) {
    paso(estado, registro[t], pista);
  }

  return {
    distancia: distanciaMetros(estado),
    items: estado.items,
    impulsores: estado.impulsores,
    caidas: estado.caidas,
    sobrecalentamientos: estado.sobrecalentamientos,
    estado,
  };
}

/** Comprueba que ningún campo del estado quedó en flotante. */
function todoEntero(estado: Estado): boolean {
  return Object.entries(estado).every(([, v]) => typeof v !== 'number' || Number.isInteger(v));
}

// ===========================================================================
console.log('=== PRNG ===');

const a = new Prng(12345);
const b = new Prng(12345);
const c = new Prng(12346);

const serieA = Array.from({ length: 5 }, () => a.siguiente());
const serieB = Array.from({ length: 5 }, () => b.siguiente());
const serieC = Array.from({ length: 5 }, () => c.siguiente());

comprobar('el mismo seed da la misma serie', JSON.stringify(serieA) === JSON.stringify(serieB));
comprobar('otro seed da otra serie', JSON.stringify(serieA) !== JSON.stringify(serieC));
comprobar(
  'todos los valores son uint32',
  serieA.every((n) => Number.isInteger(n) && n >= 0 && n <= 0xffffffff),
  serieA.join(', ')
);

// ===========================================================================
console.log('\n=== Pista ===');

const pista1 = generarPista(999);
const pista2 = generarPista(999);
const pista3 = generarPista(1000);

comprobar('el mismo seed da la misma pista', JSON.stringify(pista1) === JSON.stringify(pista2));
comprobar('otro seed da otra pista', JSON.stringify(pista1) !== JSON.stringify(pista3));
comprobar('hay obstáculos', pista1.obstaculos.length > 0, `${pista1.obstaculos.length} obstáculos`);
comprobar('hay llaves', pista1.items.length > 0, `${pista1.items.length} llaves`);
comprobar(
  'los obstáculos van en orden de posición',
  pista1.obstaculos.every((o, i) => i === 0 || o.pos >= pista1.obstaculos[i - 1].pos)
);
comprobar(
  `los primeros ${ARRANQUE_LIMPIO_MM / 1000} m van limpios`,
  pista1.obstaculos.every((o) => o.pos >= ARRANQUE_LIMPIO_MM),
  `primer obstáculo en ${Math.min(...pista1.obstaculos.map((o) => o.pos)) / 1000} m`
);

/*
 * La regla que no se puede romper: nunca los cuatro carriles bloqueados a la
 * vez. Si el jugador no tiene salida, la caída no mide habilidad.
 */
const porPosicion = new Map<number, Set<number>>();
for (const o of pista1.obstaculos) {
  if (!porPosicion.has(o.pos)) {
    porPosicion.set(o.pos, new Set());
  }
  porPosicion.get(o.pos)!.add(o.carril);
}
const maxBloqueados = Math.max(...Array.from(porPosicion.values(), (s) => s.size));
comprobar('nunca se bloquean los 4 carriles', maxBloqueados <= 2, `máximo bloqueados: ${maxBloqueados}`);

/*
 * La pista va poblada, pero el único que tumba es el cono: el margen de
 * reacción se mide entre conos, no entre obstáculos. Dos grupos de conos
 * demasiado juntos dejarían al jugador sin tiempo de cambiar de carril.
 */
const posConos = Array.from(new Set(pista1.obstaculos.filter((o) => o.tipo === TIPO_CONO).map((o) => o.pos))).sort(
  (x, y) => x - y
);
const huecoMinConos = posConos.length < 2 ? Infinity : Math.min(...posConos.slice(1).map((v, i) => v - posConos[i]));
comprobar(
  'entre dos conos siempre hay margen para esquivar',
  huecoMinConos >= 30_000,
  `${posConos.length} conos, el más cercano a ${huecoMinConos / 1000} m del anterior`
);

// ===========================================================================
console.log('\n=== Determinismo de la carrera ===');

const seedPrueba = 4242;
const registroNormal = registroDe(() => BIT_ACELERA);

const r1 = correr(seedPrueba, registroNormal);
const r2 = correr(seedPrueba, registroNormal);

comprobar('dos corridas iguales dan el mismo resultado', r1.distancia === r2.distancia, `${r1.distancia} m`);
comprobar('el estado final es idéntico', JSON.stringify(r1.estado) === JSON.stringify(r2.estado));
comprobar('no quedó ningún flotante en el estado', todoEntero(r1.estado));

// ===========================================================================
console.log('\n=== Registro de entradas ===');

const mezcla = registroDe((t) => (t % 180 < 120 ? BIT_ACELERA : BIT_ACELERA | BIT_TURBO));
const codificado = codificar(mezcla);
const recuperado = decodificar(codificado);

comprobar('el registro sobrevive ida y vuelta', JSON.stringify(Array.from(mezcla)) === JSON.stringify(Array.from(recuperado)));
comprobar('el registro comprimido cabe de sobra', codificado.length < 1024, `${codificado.length} bytes en base64`);

// ===========================================================================
console.log('\n=== Escalada de velocidad ===');

comprobar('el techo no sube antes del primer escalón', subidaEscalon(TICKS_POR_ESCALON - 1) === 0);
comprobar('sube al cumplirse el escalón', subidaEscalon(TICKS_POR_ESCALON) === SUBIDA_POR_ESCALON);
comprobar(
  'deja de subir tras el último escalón',
  subidaEscalon(TOTAL_TICKS - 1) === ESCALONES_MAX * SUBIDA_POR_ESCALON,
  `${(V_MAX_TURBO + subidaEscalon(TOTAL_TICKS - 1)) / 1000} m/s de turbo al final`
);

for (let e = 0; e <= ESCALONES_MAX; e++) {
  const t = e * TICKS_POR_ESCALON;
  const turbo = V_MAX_TURBO + subidaEscalon(t);
  console.log(
    `     desde el segundo ${String(e * (TICKS_POR_ESCALON / TPS)).padStart(2)}: ` +
      `turbo ${(turbo / 1000).toFixed(0)} m/s (${((turbo * 36) / 10000).toFixed(0)} km/h)`
  );
}

/*
 * Lo que de verdad limita la velocidad no es la física sino la cámara: el
 * jugador ve VISTA_MM de pista por delante, y cuanto más rápido va, menos
 * tiempo pasa entre que un cono aparece y le llega encima. Esto lo mide en una
 * carrera de verdad, corriendo a tope de turbo todo el rato, que es el caso
 * peor.
 *
 * No se calcula con fórmulas: se simula, se anota en qué tick aparece cada
 * cono por el borde de la pantalla y en qué tick se pisa, y se toma el peor.
 */
function ventanaDeReaccion(seed: number, registro: Uint8Array): { ticks: number; metro: number } {
  const pistaV = generarPista(seed);
  const estadoV = crearEstado();

  const conos = Array.from(
    new Set(pistaV.obstaculos.filter((o) => o.tipo === TIPO_CONO).map((o) => o.pos))
  ).sort((x, y) => x - y);

  const aparece = new Map<number, number>();
  let iVe = 0;
  let iLlega = 0;
  let peor = Number.POSITIVE_INFINITY;
  let metro = 0;

  for (let t = 1; t <= TOTAL_TICKS; t++) {
    paso(estadoV, registro[t - 1], pistaV);

    while (iVe < conos.length && conos[iVe] - VISTA_MM <= estadoV.pos) {
      aparece.set(conos[iVe], t);
      iVe++;
    }

    while (iLlega < conos.length && conos[iLlega] <= estadoV.pos) {
      const ticks = t - (aparece.get(conos[iLlega]) ?? t);
      if (ticks < peor) {
        peor = ticks;
        metro = Math.trunc(conos[iLlega] / 1000);
      }
      iLlega++;
    }
  }

  return { ticks: peor, metro };
}

let peorVentana = { ticks: Number.POSITIVE_INFINITY, metro: 0, seed: 0 };

for (const s of [101, 202, 303, 404, 505, 606, 707, 808]) {
  const v = ventanaDeReaccion(s, registroDe(() => BIT_ACELERA | BIT_TURBO));
  if (v.ticks < peorVentana.ticks) {
    peorVentana = { ...v, seed: s };
  }
}

const segVentana = peorVentana.ticks / TPS;
console.log(
  `     el cono peor colocado se ve ${segVentana.toFixed(2)} s antes de llegar ` +
    `(pista ${peorVentana.seed}, metro ${peorVentana.metro})`
);

/*
 * 0,70 s es el suelo. Reaccionar a algo que se ve cuesta del orden de 0,3 s y
 * un cambio de carril son 8 ticks, 0,13 s. Por debajo de eso la caída deja de
 * medir habilidad, y con un solo intento por persona eso no se puede permitir.
 */
comprobar('siempre da tiempo de ver un cono y esquivarlo', segVentana >= 0.7, `${segVentana.toFixed(2)} s`);

// ===========================================================================
console.log('\n=== Balanceo (docs/mecanica-y-balanceo.md) ===\n');

const estrategias: Array<{ nombre: string; fn: (t: number) => number }> = [
  { nombre: 'sin tocar nada', fn: () => 0 },
  { nombre: 'solo acelerador', fn: () => BIT_ACELERA },
  { nombre: 'turbo continuo', fn: () => BIT_ACELERA | BIT_TURBO },
  {
    nombre: 'pulsos 2 s turbo / 3 s normal',
    fn: (t) => (t % (5 * TPS) < 2 * TPS ? BIT_ACELERA | BIT_TURBO : BIT_ACELERA),
  },
  {
    nombre: 'pulsos 2 s turbo / 2 s suelto',
    fn: (t) => (t % (4 * TPS) < 2 * TPS ? BIT_ACELERA | BIT_TURBO : 0),
  },
  {
    nombre: 'pulsos 1 s turbo / 2 s normal',
    fn: (t) => (t % (3 * TPS) < 1 * TPS ? BIT_ACELERA | BIT_TURBO : BIT_ACELERA),
  },
];

// Se promedian varias pistas: una sola podría ser inusualmente fácil o dura.
const seeds = [101, 202, 303, 404, 505, 606, 707, 808];
const resumen = new Map<string, number>();

console.log('estrategia                        distancia  llaves  impuls.  caídas  sobrecal.');
console.log('------------------------------------------------------------------------------');

for (const e of estrategias) {
  const registro = registroDe(e.fn);
  let suma = 0;
  let items = 0;
  let impulsores = 0;
  let caidas = 0;
  let sobrecalentamientos = 0;

  for (const s of seeds) {
    const r = correr(s, registro);
    suma += r.distancia;
    items += r.items;
    impulsores += r.impulsores;
    caidas += r.caidas;
    sobrecalentamientos += r.sobrecalentamientos;
  }

  const n = seeds.length;
  const media = Math.round(suma / n);
  resumen.set(e.nombre, media);

  console.log(
    `${e.nombre.padEnd(32)} ${String(media).padStart(6)} m  ${(items / n).toFixed(1).padStart(5)}  ` +
      `${(impulsores / n).toFixed(1).padStart(7)}  ${(caidas / n).toFixed(1).padStart(6)}  ` +
      `${(sobrecalentamientos / n).toFixed(1).padStart(7)}`
  );
}

console.log('');

const soloAcelerador = resumen.get('solo acelerador')!;
const turboContinuo = resumen.get('turbo continuo')!;
const pulsos23 = resumen.get('pulsos 2 s turbo / 3 s normal')!;

comprobar(
  'abusar del turbo rinde menos que no usarlo',
  turboContinuo < soloAcelerador,
  `${turboContinuo} m frente a ${soloAcelerador} m`
);
comprobar(
  'dosificar el turbo rinde más que el acelerador solo',
  pulsos23 > soloAcelerador,
  `${pulsos23} m frente a ${soloAcelerador} m`
);
comprobar(
  'quien no juega se queda muy atrás',
  resumen.get('sin tocar nada')! < soloAcelerador / 2,
  `${resumen.get('sin tocar nada')!} m`
);

const mejor = Math.max(...resumen.values());
comprobar(
  `nadie se acerca al tope de plausibilidad de ${DISTANCIA_MAXIMA_M} m`,
  mejor < DISTANCIA_MAXIMA_M,
  `mejor estrategia: ${mejor} m`
);

const peorJugando = soloAcelerador;
comprobar(
  'el rango entre estrategias da margen para ordenar el ranking',
  mejor - peorJugando >= 150,
  `diferencia de ${mejor - peorJugando} m entre acelerar y dosificar`
);

// ===========================================================================
console.log('\n=== Aporte de las llaves y de los impulsores ===');

const conLogos = correr(101, registroDe(() => BIT_ACELERA));
comprobar(
  'el contador es recorrido + llaves + impulsores',
  conLogos.distancia ===
    Math.trunc(conLogos.estado.pos / 1000) + conLogos.items * ITEM_METROS + conLogos.impulsores * IMPULSOR_METROS,
  `${conLogos.items} llaves = ${conLogos.items * ITEM_METROS} m y ${conLogos.impulsores} impulsores = ` +
    `${conLogos.impulsores * IMPULSOR_METROS} m, de ${conLogos.distancia} m`
);
comprobar(
  'los impulsores aportan, pero no deciden la carrera',
  conLogos.impulsores > 0 && conLogos.impulsores * IMPULSOR_METROS < conLogos.distancia / 20,
  `${conLogos.impulsores * IMPULSOR_METROS} m de ${conLogos.distancia} m`
);

// ===========================================================================
console.log('\n=== Rendimiento ===');

/*
 * Importa por dos motivos: el navegador tiene que sostener 60 ticks por
 * segundo en un Android modesto, y en E6 el servidor va a reejecutar una
 * carrera entera por participante. Si una carrera costara décimas de segundo,
 * validar las 150 del día se volvería un problema.
 */
const registroPerf = registroDe((t) => (t % 300 < 120 ? BIT_ACELERA | BIT_TURBO : BIT_ACELERA));
const repeticiones = 50;
const inicio = Date.now();

for (let i = 0; i < repeticiones; i++) {
  correr(1000 + i, registroPerf);
}

const msPorCarrera = (Date.now() - inicio) / repeticiones;

console.log(`   ${msPorCarrera.toFixed(2)} ms por carrera completa de ${TOTAL_TICKS} ticks`);
console.log(`   ${(msPorCarrera * 1000 / TOTAL_TICKS).toFixed(1)} µs por tick`);
console.log(`   validar 150 carreras costaría ${(msPorCarrera * 150 / 1000).toFixed(2)} s`);

comprobar('una carrera se simula en menos de 50 ms', msPorCarrera < 50, `${msPorCarrera.toFixed(2)} ms`);

console.log('');
console.log(fallos === 0 ? 'TODO OK' : `${fallos} FALLO(S)`);

if (fallos > 0) {
  process.exit(1);
}
