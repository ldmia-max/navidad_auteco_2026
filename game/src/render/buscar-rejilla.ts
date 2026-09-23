/**
 * Encuentra la rejilla de un diseño ampliado.
 *
 *   cd game && npm run arte:rejilla -- diseño.png
 *   cd game && npm run arte:rejilla -- diseño.png --colores ff9f00,ffffff,3f48cc,c3c3c3,d02a16,000000
 *
 * Un diseño casi nunca llega a tamaño real: llega ampliado, con cada píxel
 * convertido en un bloque de 8 o 10, y a menudo comprimido de una forma que
 * emborrona los bordes.
 *
 * Para importarlo bien hay que saber tres cosas: cuántas celdas tiene, de qué
 * tamaño y dónde empieza la primera. Acertar el tamaño no basta: si la rejilla
 * se desplaza medio píxel, las líneas de un solo píxel —los radios de una
 * rueda, una horquilla— caen entre dos celdas y el remuestreo las borra.
 *
 * El método: la rejilla correcta produce celdas de un solo color, porque cada
 * celda es un píxel del dibujo original. Se prueban tamaños y desplazamientos
 * y gana el que deja las celdas más uniformes.
 */

import { inflateSync } from 'node:zlib';
import { readFileSync } from 'node:fs';

// ---------------------------------------------------------------------------

function leerPng(ruta: string): { ancho: number; alto: number; px: Uint8Array } {
  const datos = readFileSync(ruta);

  if (datos.readUInt32BE(0) !== 0x89504e47) {
    throw new Error(`"${ruta}" no es un PNG.`);
  }

  let pos = 8;
  let ancho = 0;
  let alto = 0;
  let canales = 0;
  const trozos: Buffer[] = [];

  while (pos < datos.length) {
    const largo = datos.readUInt32BE(pos);
    const tipo = datos.toString('ascii', pos + 4, pos + 8);
    const cuerpo = datos.subarray(pos + 8, pos + 8 + largo);

    if (tipo === 'IHDR') {
      ancho = cuerpo.readUInt32BE(0);
      alto = cuerpo.readUInt32BE(4);
      canales = cuerpo[9] === 6 ? 4 : 3;
    } else if (tipo === 'IDAT') {
      trozos.push(Buffer.from(cuerpo));
    } else if (tipo === 'IEND') {
      break;
    }

    pos += 12 + largo;
  }

  const crudo = inflateSync(Buffer.concat(trozos));
  const px = new Uint8Array(ancho * alto * 4);
  const bytesPorFila = ancho * canales;
  const previa = new Uint8Array(bytesPorFila);
  const actual = new Uint8Array(bytesPorFila);
  let origen = 0;

  for (let y = 0; y < alto; y++) {
    const filtro = crudo[origen++];

    for (let i = 0; i < bytesPorFila; i++) {
      const bruto = crudo[origen + i];
      const izq = i >= canales ? actual[i - canales] : 0;
      const arr = previa[i];
      const diag = i >= canales ? previa[i - canales] : 0;

      let valor: number;
      switch (filtro) {
        case 0: valor = bruto; break;
        case 1: valor = bruto + izq; break;
        case 2: valor = bruto + arr; break;
        case 3: valor = bruto + ((izq + arr) >> 1); break;
        default: {
          const p = izq + arr - diag;
          const pa = Math.abs(p - izq);
          const pb = Math.abs(p - arr);
          const pc = Math.abs(p - diag);
          valor = bruto + (pa <= pb && pa <= pc ? izq : pb <= pc ? arr : diag);
        }
      }

      actual[i] = valor & 0xff;
    }

    origen += bytesPorFila;

    for (let x = 0; x < ancho; x++) {
      const d = (y * ancho + x) * 4;
      const s = x * canales;
      px[d] = actual[s];
      px[d + 1] = actual[s + 1];
      px[d + 2] = actual[s + 2];
      px[d + 3] = canales === 4 ? actual[s + 3] : 255;
    }

    previa.set(actual);
  }

  return { ancho, alto, px };
}

/** Distancia entre colores, ponderada como la ve el ojo. */
function distancia(r: number, g: number, b: number, color: number): number {
  const dr = r - ((color >> 16) & 0xff);
  const dg = g - ((color >> 8) & 0xff);
  const db = b - (color & 0xff);
  return 2 * dr * dr + 4 * dg * dg + 3 * db * db;
}

// ---------------------------------------------------------------------------

const argv = process.argv.slice(2);
const ruta = argv.find((a) => !a.startsWith('--'));
const opcion = (n: string) => {
  const i = argv.indexOf('--' + n);
  return i >= 0 ? argv[i + 1] : undefined;
};

if (!ruta) {
  console.error('Uso: npm run arte:rejilla -- diseño.png [--colores hex,hex,...]');
  process.exit(1);
}

const img = leerPng(ruta);
console.log(`imagen: ${img.ancho}×${img.alto}`);

// --- Colores del diseño ------------------------------------------------------
let colores: number[];
const listado = opcion('colores');

if (listado) {
  colores = listado.split(',').map((h) => parseInt(h.replace(/^#/, ''), 16));
} else {
  // Sin lista, se toman los más frecuentes: en pixel art bastan unos pocos, y
  // el resto son los tonos intermedios que mete la compresión.
  const cuenta = new Map<number, number>();
  for (let i = 0; i < img.px.length; i += 4) {
    const c = (img.px[i] << 16) | (img.px[i + 1] << 8) | img.px[i + 2];
    cuenta.set(c, (cuenta.get(c) ?? 0) + 1);
  }
  colores = [...cuenta].sort((a, b) => b[1] - a[1]).slice(0, 8).map(([c]) => c);
  console.log('colores dominantes (sin --colores se deducen):');
  for (const c of colores) {
    console.log(`  #${c.toString(16).padStart(6, '0')}`);
  }
}

// Cada píxel se reduce al índice de su color más cercano.
const q = new Uint8Array(img.ancho * img.alto);
for (let i = 0, p = 0; i < img.px.length; i += 4, p++) {
  let mejor = 0;
  let dmin = Infinity;
  for (let k = 0; k < colores.length; k++) {
    const d = distancia(img.px[i], img.px[i + 1], img.px[i + 2], colores[k]);
    if (d < dmin) {
      dmin = d;
      mejor = k;
    }
  }
  q[p] = mejor;
}

/**
 * Qué fracción de píxeles coincide con el color mayoritario de su celda.
 *
 * Se descarta un cuarto de cada borde: los píxeles de transición entre dos
 * celdas no deben decidir, o toda rejilla parecería mala por igual.
 */
function puntuar(ox: number, oy: number, W: number, H: number, cw: number, ch: number): number {
  let aciertos = 0;
  let total = 0;

  for (let cy = 0; cy < H; cy++) {
    for (let cx = 0; cx < W; cx++) {
      const x0 = Math.round(ox + cx * cw);
      const x1 = Math.round(ox + (cx + 1) * cw);
      const y0 = Math.round(oy + cy * ch);
      const y1 = Math.round(oy + (cy + 1) * ch);

      const mx = Math.max(1, Math.floor((x1 - x0) * 0.25));
      const my = Math.max(1, Math.floor((y1 - y0) * 0.25));

      const c = new Int32Array(colores.length);
      let n = 0;

      for (let y = y0 + my; y < y1 - my; y++) {
        for (let x = x0 + mx; x < x1 - mx; x++) {
          if (x < 0 || y < 0 || x >= img.ancho || y >= img.alto) continue;
          c[q[y * img.ancho + x]]++;
          n++;
        }
      }

      if (n === 0) continue;
      aciertos += Math.max(...c);
      total += n;
    }
  }

  return total === 0 ? 0 : aciertos / total;
}

const resultados: Array<{ s: number; W: number; H: number; ox: number; oy: number; cw: number; ch: number }> = [];

for (let W = 16; W <= 32; W++) {
  for (let H = 16; H <= 32; H++) {
    let mejor = { s: -1, ox: 0, oy: 0, cw: 0, ch: 0 };

    for (let ox = -4; ox <= 10; ox++) {
      for (let oy = -4; oy <= 10; oy++) {
        const cw = (img.ancho - ox) / W;
        const ch = (img.alto - oy) / H;
        if (cw < 5 || cw > 16 || ch < 5 || ch > 16) continue;

        const s = puntuar(ox, oy, W, H, cw, ch);
        if (s > mejor.s) {
          mejor = { s, ox, oy, cw, ch };
        }
      }
    }

    if (mejor.s > 0) {
      resultados.push({ ...mejor, W, H });
    }
  }
}

resultados.sort((a, b) => b.s - a.s);

console.log('\nmejores encajes:');
for (const r of resultados.slice(0, 8)) {
  console.log(
    `  ${String(r.W).padStart(2)}×${String(r.H).padStart(2)}  offset (${r.ox},${r.oy})  ` +
      `celda ${r.cw.toFixed(2)}×${r.ch.toFixed(2)}  uniformidad ${(r.s * 100).toFixed(2)}%`
  );
}

const g = resultados[0];
console.log('\nComando sugerido:');
console.log(
  `  npm run arte:importar -- ${ruta} NOMBRE --tamano ${g.W}x${g.H} --fondo auto --offset ${g.ox},${g.oy}`
);
