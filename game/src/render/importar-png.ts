/**
 * Convierte un PNG en un sprite de texto.
 *
 *   cd game && npm run arte:importar -- ruta/al/diseño.png MOTO
 *
 * Existe para que un diseño se reproduzca EXACTAMENTE, píxel por píxel, en vez
 * de transcribirlo a ojo. Lee la imagen, empareja cada color con el más cercano
 * de la paleta e imprime el bloque listo para pegar en sprites.ts.
 *
 * Avisa de dos cosas que suelen arruinar un sprite:
 *
 * - Antialias: si la imagen trae cientos de colores, no se dibujó a tamaño
 *   real sino grande y reducida, y los bordes salen sucios.
 * - Colores lejos de la paleta: o el diseño usa un tono nuevo que hay que
 *   agregar al tema, o se está forzando algo que no encaja.
 *
 * Decodifica el PNG con zlib, que ya trae Node. Solo admite los formatos que
 * exporta cualquier editor de pixel art: RGB y RGBA de 8 bits por canal.
 */

import { inflateSync } from 'node:zlib';
import { readFileSync } from 'node:fs';
import { LEYENDA } from './pixeles';
import { TEMA_POR_DEFECTO, type Paleta } from './tema';

interface Imagen {
  ancho: number;
  alto: number;
  /** Cada píxel como [r, g, b, a]. */
  pixeles: Uint8Array;
}

// ---------------------------------------------------------------------------
// Decodificación de PNG
// ---------------------------------------------------------------------------

function leerPng(ruta: string): Imagen {
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

      const bits = cuerpo[8];
      const tipoColor = cuerpo[9];

      if (bits !== 8) {
        throw new Error(`El PNG usa ${bits} bits por canal; se esperan 8. Vuelve a exportarlo.`);
      }
      if (tipoColor !== 2 && tipoColor !== 6) {
        throw new Error(
          'El PNG usa paleta indexada o escala de grises. Expórtalo como RGB o RGBA.'
        );
      }

      canales = tipoColor === 6 ? 4 : 3;
    } else if (tipo === 'IDAT') {
      trozos.push(Buffer.from(cuerpo));
    } else if (tipo === 'IEND') {
      break;
    }

    pos += 12 + largo;
  }

  const crudo = inflateSync(Buffer.concat(trozos));
  const pixeles = new Uint8Array(ancho * alto * 4);
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
        case 0:
          valor = bruto;
          break;
        case 1:
          valor = bruto + izq;
          break;
        case 2:
          valor = bruto + arr;
          break;
        case 3:
          valor = bruto + ((izq + arr) >> 1);
          break;
        case 4: {
          // Predictor de Paeth.
          const p = izq + arr - diag;
          const pa = Math.abs(p - izq);
          const pb = Math.abs(p - arr);
          const pc = Math.abs(p - diag);
          valor = bruto + (pa <= pb && pa <= pc ? izq : pb <= pc ? arr : diag);
          break;
        }
        default:
          throw new Error(`Filtro PNG desconocido: ${filtro}`);
      }

      actual[i] = valor & 0xff;
    }

    origen += bytesPorFila;

    for (let x = 0; x < ancho; x++) {
      const d = (y * ancho + x) * 4;
      const s = x * canales;
      pixeles[d] = actual[s];
      pixeles[d + 1] = actual[s + 1];
      pixeles[d + 2] = actual[s + 2];
      pixeles[d + 3] = canales === 4 ? actual[s + 3] : 255;
    }

    previa.set(actual);
  }

  return { ancho, alto, pixeles };
}

// ---------------------------------------------------------------------------
// Emparejado con la paleta
// ---------------------------------------------------------------------------

/** Distancia entre dos colores, ponderada como la ve el ojo. */
function distancia(r1: number, g1: number, b1: number, color: number): number {
  const r2 = (color >> 16) & 0xff;
  const g2 = (color >> 8) & 0xff;
  const b2 = color & 0xff;

  const dr = r1 - r2;
  const dg = g1 - g2;
  const db = b1 - b2;

  // El verde pesa más que el rojo, y el rojo más que el azul.
  return 2 * dr * dr + 4 * dg * dg + 3 * db * db;
}

interface Emparejado {
  caracter: string;
  clave: keyof Paleta;
  distancia: number;
}

function masCercano(r: number, g: number, b: number, paleta: Paleta): Emparejado {
  let mejor: Emparejado = { caracter: '?', clave: 'negro', distancia: Infinity };

  for (const [caracter, clave] of Object.entries(LEYENDA)) {
    if (clave === null) {
      continue;
    }

    const d = distancia(r, g, b, paleta[clave]);

    if (d < mejor.distancia) {
      mejor = { caracter, clave, distancia: d };
    }
  }

  return mejor;
}

// ---------------------------------------------------------------------------

const [rutaPng, nombre = 'SPRITE'] = process.argv.slice(2);

if (!rutaPng) {
  console.error('Uso: npm run arte:importar -- ruta/al/diseño.png NOMBRE_DEL_SPRITE');
  process.exit(1);
}

const img = leerPng(rutaPng);
const paleta = TEMA_POR_DEFECTO.paleta;

const filas: string[] = [];
const usados = new Map<string, { clave: keyof Paleta; veces: number; peor: number; hex: string }>();
const distintos = new Set<string>();

for (let y = 0; y < img.alto; y++) {
  let fila = '';

  for (let x = 0; x < img.ancho; x++) {
    const i = (y * img.ancho + x) * 4;
    const [r, g, b, a] = [img.pixeles[i], img.pixeles[i + 1], img.pixeles[i + 2], img.pixeles[i + 3]];

    if (a < 128) {
      fila += '.';
      continue;
    }

    const hex = '#' + ((r << 16) | (g << 8) | b).toString(16).padStart(6, '0');
    distintos.add(hex);

    const m = masCercano(r, g, b, paleta);
    fila += m.caracter;

    const previo = usados.get(m.caracter);
    if (previo) {
      previo.veces++;
      if (m.distancia > previo.peor) {
        previo.peor = m.distancia;
        previo.hex = hex;
      }
    } else {
      usados.set(m.caracter, { clave: m.clave, veces: 1, peor: m.distancia, hex });
    }
  }

  filas.push(fila);
}

console.log(`\n// ${img.ancho}×${img.alto}, importado de ${rutaPng}`);
console.log(`export const ${nombre}: Sprite = [`);
for (const fila of filas) {
  console.log(`  '${fila}',`);
}
console.log('];\n');

console.log('--- Colores emparejados ---');
for (const [caracter, info] of [...usados].sort((a, b) => b[1].veces - a[1].veces)) {
  const alerta = info.peor > 3000 ? '  <-- lejos de la paleta' : '';
  console.log(
    `  '${caracter}' ${String(info.clave).padEnd(12)} ${String(info.veces).padStart(5)} px${alerta}`
  );
}

console.log(`\n--- Comprobaciones ---`);
console.log(`  colores distintos en la imagen: ${distintos.size}`);

if (distintos.size > 16) {
  console.log(
    '  AVISO: son muchos colores para pixel art. Suele significar que la imagen'
  );
  console.log(
    '         se dibujó grande y se redujo, y los bordes quedaron con antialias.'
  );
  console.log('         Conviene dibujarla al tamaño real, con colores planos.');
}

const lejanos = [...usados.values()].filter((i) => i.peor > 3000);
if (lejanos.length > 0) {
  console.log('  AVISO: hay colores que no tienen equivalente cercano en la paleta:');
  for (const l of lejanos) {
    console.log(`         ${l.hex} se emparejó con ${String(l.clave)}`);
  }
  console.log('         Si son a propósito, hay que agregarlos al tema y a la leyenda.');
}
