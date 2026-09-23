/**
 * Convierte un PNG en un sprite de texto.
 *
 *   cd game && npm run arte:importar -- diseño.png MOTO
 *   cd game && npm run arte:importar -- diseño.png MOTO --tamano 25x22
 *   cd game && npm run arte:importar -- diseño.png MOTO --tamano 25x22 --fondo auto
 *
 * Existe para que un diseño se reproduzca EXACTAMENTE, píxel por píxel, en vez
 * de transcribirlo a ojo.
 *
 * Los diseños casi nunca llegan a tamaño real: llegan ampliados, y a menudo
 * pasados por una compresión que los llena de colores intermedios. Por eso hay
 * dos pasos:
 *
 * 1. **Remuestreo por voto mayoritario.** Cada celda del sprite se decide por
 *    el color que más veces aparece en su zona de la imagen. Tomar el píxel
 *    del centro sería más simple, pero basta un píxel de ruido justo ahí para
 *    arruinar la celda.
 * 2. **Emparejado con la paleta**, con una distancia ponderada como la ve el
 *    ojo, y aviso de los colores que no tienen equivalente cercano.
 *
 * Decodifica el PNG con zlib, que ya trae Node. Admite RGB y RGBA de 8 bits,
 * que es lo que exporta cualquier editor.
 */

import { inflateSync } from 'node:zlib';
import { readFileSync } from 'node:fs';
import { LEYENDA } from './pixeles';
import { TEMA_POR_DEFECTO, type Paleta } from './tema';

interface Imagen {
  ancho: number;
  alto: number;
  /** Cada píxel como [r, g, b, a]. */
  px: Uint8Array;
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

      if (cuerpo[8] !== 8) {
        throw new Error(`El PNG usa ${cuerpo[8]} bits por canal; se esperan 8.`);
      }
      if (cuerpo[9] !== 2 && cuerpo[9] !== 6) {
        throw new Error('El PNG usa paleta indexada o escala de grises. Expórtalo como RGB o RGBA.');
      }

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
      px[d] = actual[s];
      px[d + 1] = actual[s + 1];
      px[d + 2] = actual[s + 2];
      px[d + 3] = canales === 4 ? actual[s + 3] : 255;
    }

    previa.set(actual);
  }

  return { ancho, alto, px };
}

// ---------------------------------------------------------------------------
// Emparejado con la paleta
// ---------------------------------------------------------------------------

/**
 * Distancia entre dos colores, ponderada como la ve el ojo.
 *
 * El verde pesa más que el rojo y el rojo más que el azul. Una distancia
 * euclidiana plana emparejaría mal los grises con los azules.
 */
function distancia(r1: number, g1: number, b1: number, color: number): number {
  const dr = r1 - ((color >> 16) & 0xff);
  const dg = g1 - ((color >> 8) & 0xff);
  const db = b1 - (color & 0xff);
  return 2 * dr * dr + 4 * dg * dg + 3 * db * db;
}

function masCercano(
  r: number,
  g: number,
  b: number,
  paleta: Paleta,
  mapa: Array<{ color: number; caracter: string }>
): { caracter: string; clave: keyof Paleta; distancia: number } {
  let mejor = { caracter: '?', clave: 'negro' as keyof Paleta, distancia: Infinity };

  if (mapa.length > 0) {
    for (const { color, caracter } of mapa) {
      const d = distancia(r, g, b, color);
      if (d < mejor.distancia) {
        mejor = { caracter, clave: (LEYENDA[caracter] ?? 'negro') as keyof Paleta, distancia: d };
      }
    }
    return mejor;
  }

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
// Argumentos
// ---------------------------------------------------------------------------

const argv = process.argv.slice(2);
const posicionales = argv.filter((a) => !a.startsWith('--'));
const opcion = (nombre: string): string | undefined => {
  const i = argv.indexOf('--' + nombre);
  return i >= 0 ? argv[i + 1] : undefined;
};

const [rutaPng, nombre = 'SPRITE'] = posicionales;

if (!rutaPng) {
  console.error('Uso: npm run arte:importar -- diseño.png NOMBRE [--tamano 25x22] [--fondo auto|#RRGGBB]');
  process.exit(1);
}

const img = leerPng(rutaPng);
const paleta = TEMA_POR_DEFECTO.paleta;

/*
 * Mapa explícito de colores.
 *
 *   --mapa ffffff=w,3f48cc=B,d02a16=r,000000=n,c3c3c3=t
 *
 * Cuando se sabe exactamente con qué colores se dibujó, conviene decirlo: el
 * emparejado se limita a esa lista y desaparecen los tonos espurios que salen
 * de los píxeles intermedios de la compresión. Sin él, un borde entre naranja
 * y negro produce un marrón que la paleta empareja con "pista" o "piel", y el
 * sprite aparece salpicado de colores que nadie dibujó.
 */
const mapaPedido = opcion('mapa');
const mapaExplicito: Array<{ color: number; caracter: string }> = [];

if (mapaPedido) {
  for (const par of mapaPedido.split(',')) {
    const [hex, caracter] = par.split('=');
    if (!hex || !caracter) {
      console.error(`Entrada de --mapa mal formada: "${par}". Va como ffffff=w`);
      process.exit(1);
    }
    if (caracter !== '.' && !(caracter in LEYENDA)) {
      console.error(`El carácter "${caracter}" no está en la leyenda de pixeles.ts.`);
      process.exit(1);
    }
    mapaExplicito.push({ color: parseInt(hex.replace(/^#/, ''), 16), caracter });
  }
}

// --- Tamaño de salida --------------------------------------------------------
const pedido = opcion('tamano');
let anchoSalida = img.ancho;
let altoSalida = img.alto;

if (pedido) {
  const m = /^(\d+)x(\d+)$/.exec(pedido);
  if (!m) {
    console.error('El tamaño va como ANCHOxALTO, por ejemplo 25x22.');
    process.exit(1);
  }
  anchoSalida = parseInt(m[1], 10);
  altoSalida = parseInt(m[2], 10);
}

// --- Color de fondo ----------------------------------------------------------
let fondo: { r: number; g: number; b: number } | null = null;
const fondoPedido = opcion('fondo');

if (fondoPedido === 'auto') {
  // El color más repetido en el borde de la imagen: si el diseño viene sobre
  // un lienzo de color en vez de transparencia, el borde es ese color.
  const cuenta = new Map<string, number>();
  const anotar = (x: number, y: number) => {
    const i = (y * img.ancho + x) * 4;
    const k = `${img.px[i]},${img.px[i + 1]},${img.px[i + 2]}`;
    cuenta.set(k, (cuenta.get(k) ?? 0) + 1);
  };
  for (let x = 0; x < img.ancho; x++) {
    anotar(x, 0);
    anotar(x, img.alto - 1);
  }
  for (let y = 0; y < img.alto; y++) {
    anotar(0, y);
    anotar(img.ancho - 1, y);
  }
  const [clave] = [...cuenta].sort((a, b) => b[1] - a[1])[0];
  const [r, g, b] = clave.split(',').map(Number);
  fondo = { r, g, b };
  console.log(`Fondo detectado: #${((r << 16) | (g << 8) | b).toString(16).padStart(6, '0')}`);
} else if (fondoPedido) {
  const hex = fondoPedido.replace(/^#/, '');
  fondo = {
    r: parseInt(hex.slice(0, 2), 16),
    g: parseInt(hex.slice(2, 4), 16),
    b: parseInt(hex.slice(4, 6), 16),
  };
}

/** Un color cuenta como fondo si está razonablemente cerca del elegido. */
function esFondo(r: number, g: number, b: number, a: number): boolean {
  if (a < 128) {
    return true;
  }
  if (!fondo) {
    return false;
  }
  return distancia(r, g, b, (fondo.r << 16) | (fondo.g << 8) | fondo.b) < 3000;
}

// ---------------------------------------------------------------------------
// Recorte del fondo
// ---------------------------------------------------------------------------

/*
 * Se recorta el marco de fondo antes de remuestrear.
 *
 * Un diseño suele venir con margen alrededor, y a veces con un borde de otro
 * color. Si se remuestrea la imagen entera, la rejilla se desplaza y el
 * resultado sale con las formas rotas: ruedas ovaladas, líneas de un píxel que
 * desaparecen.
 */
let recX0 = 0;
let recY0 = 0;
let recX1 = img.ancho - 1;
let recY1 = img.alto - 1;

if (fondo) {
  const esDelFondo = (x: number, y: number) => {
    const i = (y * img.ancho + x) * 4;
    return esFondo(img.px[i], img.px[i + 1], img.px[i + 2], img.px[i + 3]);
  };

  const medioY = Math.floor(img.alto / 2);
  const medioX = Math.floor(img.ancho / 2);

  // Primero se salta lo que no sea fondo (un marco exterior de otro color).
  while (recX0 < recX1 && !esDelFondo(recX0, medioY)) recX0++;
  while (recX1 > recX0 && !esDelFondo(recX1, medioY)) recX1--;
  while (recY0 < recY1 && !esDelFondo(medioX, recY0)) recY0++;
  while (recY1 > recY0 && !esDelFondo(medioX, recY1)) recY1--;

  console.log(`Campo útil tras recortar el marco: ${recX1 - recX0 + 1}×${recY1 - recY0 + 1} px`);
}

/*
 * Desplazamiento manual de la rejilla.
 *
 *   --offset 6,2
 *
 * Un diseño ampliado rara vez empieza justo en el borde de la imagen. Si la
 * rejilla se desplaza medio píxel, las líneas de un solo píxel —los radios de
 * una rueda, la horquilla— caen entre dos celdas y el voto mayoritario las
 * borra. La herramienta de búsqueda de rejilla dice qué offset usar.
 */
const offsetPedido = opcion('offset');

if (offsetPedido) {
  const m = /^(-?\d+),(-?\d+)$/.exec(offsetPedido);
  if (!m) {
    console.error('El offset va como X,Y, por ejemplo 6,2.');
    process.exit(1);
  }
  /*
   * El offset es absoluto desde el origen de la imagen, no relativo al
   * recorte: es lo que devuelve la búsqueda de rejilla, que trabaja sobre la
   * imagen completa. Sumarlo al recorte desplazaba la rejilla dos veces y las
   * líneas de un píxel se perdían igual.
   */
  recX0 = parseInt(m[1], 10);
  recY0 = parseInt(m[2], 10);
  recX1 = img.ancho - 1;
  recY1 = img.alto - 1;
  console.log(`Rejilla fijada en (${recX0}, ${recY0}) sobre la imagen completa`);
}

const anchoUtil = recX1 - recX0 + 1;
const altoUtil = recY1 - recY0 + 1;

// ---------------------------------------------------------------------------
// Remuestreo por voto mayoritario
// ---------------------------------------------------------------------------

const filas: string[] = [];
const usados = new Map<string, { clave: keyof Paleta; veces: number; peor: number; hex: string }>();
const distintos = new Set<string>();

for (let cy = 0; cy < altoSalida; cy++) {
  let fila = '';

  for (let cx = 0; cx < anchoSalida; cx++) {
    // Zona de la imagen que corresponde a esta celda.
    const x0 = recX0 + Math.floor((cx * anchoUtil) / anchoSalida);
    const x1 = Math.max(x0 + 1, recX0 + Math.floor(((cx + 1) * anchoUtil) / anchoSalida));
    const y0 = recY0 + Math.floor((cy * altoUtil) / altoSalida);
    const y1 = Math.max(y0 + 1, recY0 + Math.floor(((cy + 1) * altoUtil) / altoSalida));

    /*
     * Se vota sobre el carácter de la paleta, no sobre el color crudo: así el
     * ruido de compresión, que produce cientos de tonos casi iguales, se
     * colapsa antes de contar y no dispersa los votos.
     */
    const votos = new Map<string, { n: number; r: number; g: number; b: number; d: number }>();
    let votosFondo = 0;
    let total = 0;

    for (let y = y0; y < y1; y++) {
      for (let x = x0; x < x1; x++) {
        // Con un offset negativo la primera celda empieza fuera de la imagen.
        // Leer ahí devolvía basura y la celda salía con un color inventado.
        if (x < 0 || y < 0 || x >= img.ancho || y >= img.alto) {
          continue;
        }

        const i = (y * img.ancho + x) * 4;
        const [r, g, b, a] = [img.px[i], img.px[i + 1], img.px[i + 2], img.px[i + 3]];
        total++;

        if (esFondo(r, g, b, a)) {
          votosFondo++;
          continue;
        }

        const m = masCercano(r, g, b, paleta, mapaExplicito);

        if (m.caracter === '.') {
          votosFondo++;
          continue;
        }
        const v = votos.get(m.caracter);
        if (v) {
          v.n++;
        } else {
          votos.set(m.caracter, { n: 1, r, g, b, d: m.distancia });
        }
      }
    }

    // El fondo solo gana si es mayoría clara; si no, los bordes del dibujo se
    // comerían un píxel por cada lado.
    if (votos.size === 0 || votosFondo > total / 2) {
      fila += '.';
      continue;
    }

    const [caracter, info] = [...votos].sort((a, b) => b[1].n - a[1].n)[0];
    fila += caracter;

    const hex = '#' + ((info.r << 16) | (info.g << 8) | info.b).toString(16).padStart(6, '0');
    distintos.add(hex);

    const clave = LEYENDA[caracter] as keyof Paleta;
    const previo = usados.get(caracter);
    if (previo) {
      previo.veces++;
      if (info.d > previo.peor) {
        previo.peor = info.d;
        previo.hex = hex;
      }
    } else {
      usados.set(caracter, { clave, veces: 1, peor: info.d, hex });
    }
  }

  filas.push(fila);
}

// ---------------------------------------------------------------------------
// Salida
// ---------------------------------------------------------------------------

console.log(`\n// ${anchoSalida}×${altoSalida}, importado de ${rutaPng}`);
console.log(`export const ${nombre}: Sprite = [`);
for (const fila of filas) {
  console.log(`  '${fila}',`);
}
console.log('];\n');

console.log('--- Colores emparejados ---');
for (const [caracter, info] of [...usados].sort((a, b) => b[1].veces - a[1].veces)) {
  const alerta = info.peor > 3000 ? `  <-- ${info.hex} no tiene equivalente cercano` : '';
  console.log(`  '${caracter}' ${String(info.clave).padEnd(12)} ${String(info.veces).padStart(4)} px${alerta}`);
}

const lejanos = [...usados.values()].filter((i) => i.peor > 3000);

if (lejanos.length > 0) {
  console.log('\nAVISO: hay colores del diseño que la paleta no reproduce bien.');
  console.log('       O se agregan al tema y a la leyenda, o el sprite saldrá');
  console.log('       con otros tonos de los que se dibujaron.');
}
