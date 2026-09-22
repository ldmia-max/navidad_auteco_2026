/**
 * Exporta el arte a PNG para poder mirarlo.
 *
 *   cd game && npm run arte:png
 *
 * Genera dos archivos en game/salida-arte/: una hoja con todos los sprites y
 * una maqueta de la pantalla completa. Sirve para revisar el arte sin abrir el
 * navegador y para adjuntar capturas cuando se discute un cambio.
 *
 * El PNG se escribe a mano porque no hace falta más: cabecera, un bloque de
 * píxeles comprimido con zlib (que ya trae Node) y el CRC de cada trozo. Meter
 * una librería de imágenes para esto sería desproporcionado.
 */

import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { pintarTexto as pintarTextoFuente } from './fuente';
import { LEYENDA } from './pixeles';
import * as S from './sprites';
import { TEMA_POR_DEFECTO, type Paleta } from './tema';

// ---------------------------------------------------------------------------
// Lienzo RGBA
// ---------------------------------------------------------------------------

class Lienzo {
  readonly datos: Uint8Array;

  constructor(
    readonly ancho: number,
    readonly alto: number,
    fondo = 0x000000
  ) {
    this.datos = new Uint8Array(ancho * alto * 4);
    this.rect(fondo, 0, 0, ancho, alto);
  }

  punto(color: number, x: number, y: number): void {
    if (x < 0 || y < 0 || x >= this.ancho || y >= this.alto) {
      return;
    }
    const i = (y * this.ancho + x) * 4;
    this.datos[i] = (color >> 16) & 0xff;
    this.datos[i + 1] = (color >> 8) & 0xff;
    this.datos[i + 2] = color & 0xff;
    this.datos[i + 3] = 255;
  }

  rect(color: number, x: number, y: number, w: number, h: number): void {
    for (let dy = 0; dy < h; dy++) {
      for (let dx = 0; dx < w; dx++) {
        this.punto(color, x + dx, y + dy);
      }
    }
  }

  sprite(filas: readonly string[], paleta: Paleta, x: number, y: number, reemplazo?: [string, number]): void {
    for (let fy = 0; fy < filas.length; fy++) {
      for (let fx = 0; fx < filas[fy].length; fx++) {
        const ch = filas[fy][fx];

        if (reemplazo && ch === reemplazo[0]) {
          this.punto(reemplazo[1], x + fx, y + fy);
          continue;
        }

        const clave = LEYENDA[ch];
        if (clave) {
          this.punto(paleta[clave], x + fx, y + fy);
        }
      }
    }
  }

  /** Reutiliza la tipografía del juego a través de un Graphics falso. */
  texto(cadena: string, x: number, y: number, color: number): void {
    const falso = {
      fillStyle: () => {},
      fillRect: (rx: number, ry: number, rw: number, rh: number) => this.rect(color, rx, ry, rw, rh),
    };
    // El Graphics de Phaser solo se usa aquí para fillStyle y fillRect.
    pintarTextoFuente(falso as never, cadena, x, y, color);
  }

  /** Devuelve un lienzo escalado por un entero, sin suavizado. */
  escalar(factor: number): Lienzo {
    const salida = new Lienzo(this.ancho * factor, this.alto * factor);

    for (let y = 0; y < this.alto; y++) {
      for (let x = 0; x < this.ancho; x++) {
        const i = (y * this.ancho + x) * 4;
        const color = (this.datos[i] << 16) | (this.datos[i + 1] << 8) | this.datos[i + 2];
        salida.rect(color, x * factor, y * factor, factor, factor);
      }
    }

    return salida;
  }
}

// ---------------------------------------------------------------------------
// Escritura de PNG
// ---------------------------------------------------------------------------

const TABLA_CRC = (() => {
  const tabla = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) {
      c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    }
    tabla[n] = c >>> 0;
  }
  return tabla;
})();

function crc32(datos: Uint8Array): number {
  let c = 0xffffffff;
  for (const b of datos) {
    c = TABLA_CRC[(c ^ b) & 0xff] ^ (c >>> 8);
  }
  return (c ^ 0xffffffff) >>> 0;
}

function trozo(tipo: string, contenido: Uint8Array): Buffer {
  const nombre = Buffer.from(tipo, 'ascii');
  const cuerpo = Buffer.concat([nombre, Buffer.from(contenido)]);

  const largo = Buffer.alloc(4);
  largo.writeUInt32BE(contenido.length, 0);

  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(cuerpo), 0);

  return Buffer.concat([largo, cuerpo, crc]);
}

function aPng(lienzo: Lienzo): Buffer {
  const { ancho, alto, datos } = lienzo;

  // Cada scanline lleva delante un byte de filtro; 0 significa "sin filtro".
  const crudo = Buffer.alloc(alto * (ancho * 4 + 1));
  for (let y = 0; y < alto; y++) {
    crudo[y * (ancho * 4 + 1)] = 0;
    Buffer.from(datos.buffer, y * ancho * 4, ancho * 4).copy(crudo, y * (ancho * 4 + 1) + 1);
  }

  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(ancho, 0);
  ihdr.writeUInt32BE(alto, 4);
  ihdr[8] = 8; // bits por canal
  ihdr[9] = 6; // RGBA
  ihdr[10] = 0;
  ihdr[11] = 0;
  ihdr[12] = 0;

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    trozo('IHDR', ihdr),
    trozo('IDAT', deflateSync(crudo, { level: 9 })),
    trozo('IEND', new Uint8Array(0)),
  ]);
}

// ---------------------------------------------------------------------------
// Hoja de sprites
// ---------------------------------------------------------------------------

const P = TEMA_POR_DEFECTO.paleta;

function hojaSprites(): Lienzo {
  const items: Array<[string, readonly string[]]> = [
    ['MOTO', S.MOTO],
    ['WHEELIE', S.MOTO_WHEELIE],
    ['CAIDA', S.MOTO_CAIDA],
    ['RAMPA', S.RAMPA],
    ['VALLA', S.VALLA],
    ['LODO', S.LODO],
    ['ITEM TVS', S.ITEM_TVS],
    ['HUMO', S.HUMO],
    ['SOMBRA', S.SOMBRA],
    ['PUBLICO', S.ESPECTADOR],
    ['ANIMANDO', S.ESPECTADOR_ANIMANDO],
    ['LUZ', S.BOMBILLA],
    ['PINO', S.PINO],
    ['NUBE', S.NUBE],
    ['PILOTO', S.PILOTO_PODIO],
    ['COPA', S.COPA],
    ['ESTRELLA', S.ESTRELLA],
  ];

  const celda = 30;
  const columnas = 6;
  const filas = Math.ceil(items.length / columnas);
  const lienzo = new Lienzo(columnas * celda, filas * celda + 8, 0x303030);

  items.forEach(([nombre, sprite], i) => {
    const cx = (i % columnas) * celda;
    const cy = Math.floor(i / columnas) * celda;

    // Fondo a cuadros para distinguir lo transparente.
    for (let y = 0; y < celda - 10; y += 4) {
      for (let x = 0; x < celda; x += 4) {
        const claro = ((x / 4 + y / 4) | 0) % 2 === 0;
        lienzo.rect(claro ? 0x4a4a4a : 0x3a3a3a, cx + x, cy + y, 4, 4);
      }
    }

    const ancho = sprite[0].length;
    const alto = sprite.length;
    lienzo.sprite(sprite, P, cx + Math.floor((celda - ancho) / 2), cy + Math.floor((celda - 10 - alto) / 2));
    lienzo.texto(nombre, cx + 1, cy + celda - 9, 0xffffff);
  });

  return lienzo;
}

// ---------------------------------------------------------------------------
// Maqueta de la pantalla
// ---------------------------------------------------------------------------

const ANCHO = 320;
const ALTO = 180;
const PISTA_Y = 94;
const PISTA_ALTO = 52;
const CARRIL_ALTO = PISTA_ALTO / 4;

function maquetaPantalla(): Lienzo {
  const l = new Lienzo(ANCHO, ALTO, P.cieloAlto);

  // Cielo en bandas.
  l.rect(P.cielo, 0, 56, ANCHO, 38);

  // Nubes.
  for (const [x, y] of [
    [10, 8],
    [120, 4],
    [230, 12],
  ]) {
    l.sprite(S.NUBE, P, x, y);
  }

  // Cerros y pinos, banda 26-56.
  for (let x = -20; x < ANCHO + 40; x += 64) {
    for (let i = 0; i < 30; i++) {
      const mitad = Math.round((i / 30) * 32);
      l.rect(P.verde, x + 32 - mitad, 26 + i, mitad * 2, 1);
    }
  }
  for (const x of [30, 118, 200, 272]) {
    l.sprite(S.PINO, P, x, 36);
  }

  // Tribuna, banda 56-88.
  const T0 = 56;
  l.rect(P.verdeOscuro, 0, T0, ANCHO, 32);
  l.rect(P.negro, 0, T0 + 6, ANCHO, 1);

  const coloresLuz = [P.rojo, P.verdeClaro, P.crema, P.azulClaro];
  for (let i = 0, x = 4; x < ANCHO; x += 12, i++) {
    l.sprite(S.BOMBILLA, P, x, T0 + 6, ['r', coloresLuz[i % coloresLuz.length]]);
  }

  const coloresRopa = [P.rojo, P.azul, P.crema, P.verdeClaro, P.amarillo, P.blanco];
  for (let fila = 0; fila < 2; fila++) {
    const y = T0 + 12 + fila * 8;
    for (let i = 0, x = fila * 4; x < ANCHO; x += 9, i++) {
      const base = (i + fila) % 3 === 0 ? S.ESPECTADOR_ANIMANDO : S.ESPECTADOR;
      l.sprite(base, P, x, y, ['r', coloresRopa[(i + fila * 3) % coloresRopa.length]]);
    }
  }
  l.rect(P.blanco, 0, T0 + 27, ANCHO, 2);
  l.rect(P.verde, 0, T0 + 29, ANCHO, 3);

  // Cartel de la tribuna.
  const textoCartel = TEMA_POR_DEFECTO.textos.cartelTribuna;
  const anchoCartel = textoCartel.length * 6 - 1 + 14;
  const xCartel = Math.floor((ANCHO - anchoCartel) / 2);
  l.rect(P.blanco, xCartel, T0 + 2, anchoCartel, 17);
  l.rect(P.azul, xCartel + 2, T0 + 4, anchoCartel - 4, 13);
  l.texto(textoCartel, xCartel + 7, T0 + 7, P.blanco);

  // Césped, banda 86-94.
  l.rect(P.verde, 0, 86, ANCHO, 8);
  for (let x = 0; x < ANCHO; x += 16) {
    l.rect(P.verdeClaro, x + 3, 88, 2, 2);
    l.rect(P.verdeOscuro, x + 9, 90, 3, 2);
  }
  l.rect(P.pistaBorde, 0, 92, ANCHO, 2);

  // Pista.
  for (let carril = 0; carril < 4; carril++) {
    const y = PISTA_Y + carril * CARRIL_ALTO;
    l.rect(carril % 2 === 0 ? P.pista : P.pistaAlt, 0, y, ANCHO, CARRIL_ALTO);

    for (let x = (carril * 7) % 16; x < ANCHO; x += 16) {
      l.rect(P.pistaBorde, x, y + 3, 2, 1);
    }

    if (carril > 0) {
      for (let x = 0; x < ANCHO; x += 16) {
        l.rect(P.crema, x, y, 9, 1);
      }
    }
  }

  // Obstáculos de muestra, uno de cada tipo.
  l.sprite(S.RAMPA, P, 140, PISTA_Y + CARRIL_ALTO * 2 - 1);
  l.sprite(S.VALLA, P, 210, PISTA_Y + CARRIL_ALTO * 1 - 1);
  l.sprite(S.LODO, P, 250, PISTA_Y + CARRIL_ALTO * 3 + 1);
  l.sprite(S.ITEM_TVS, P, 180, PISTA_Y + CARRIL_ALTO * 0);

  // Moto en el segundo carril.
  l.sprite(S.MOTO, P, 63, PISTA_Y + CARRIL_ALTO * 2 + CARRIL_ALTO - S.MOTO.length);

  // Panel inferior.
  const panelY = PISTA_Y + PISTA_ALTO;
  const panelAlto = ALTO - panelY;
  const cx = Math.floor(ANCHO / 2);

  l.rect(P.negro, 0, panelY, ANCHO, panelAlto);
  l.texto('DIST', 30 - 11, panelY + 3, P.rojo);
  l.texto('TEMP', cx - 11, panelY + 3, P.rojo);
  l.texto('TIME', ANCHO - 30 - 11, panelY + 3, P.rojo);

  const marco = (x: number, y: number, w: number, h: number) => {
    l.rect(P.azulClaro, x, y, w, 1);
    l.rect(P.azulClaro, x, y + h - 1, w, 1);
    l.rect(P.azulClaro, x, y, 1, h);
    l.rect(P.azulClaro, x + w - 1, y, 1, h);
  };

  marco(4, panelY + 12, 72, 15);
  marco(ANCHO - 76, panelY + 12, 72, 15);
  marco(cx - 34, panelY + 11, 68, 14);
  l.rect(P.azulClaro, cx - 44, panelY + 16, 10, 2);
  l.rect(P.azulClaro, cx + 34, panelY + 16, 10, 2);
  l.rect(P.blanco, cx - 48, panelY + 14, 4, 5);
  l.rect(P.blanco, cx + 44, panelY + 14, 4, 5);
  l.rect(P.azulClaro, cx - 8, panelY + 25, 16, 3);
  l.rect(P.azulClaro, cx - 12, panelY + 28, 24, 3);

  l.rect(P.tempFria, cx - 31, panelY + 14, 62, 8);
  l.rect(P.tempCaliente, cx - 31, panelY + 14, 38, 8);

  l.texto('1284M', 40 - 14, panelY + 16, P.blanco);
  l.texto('0:47', ANCHO - 40 - 11, panelY + 16, P.blanco);

  return l;
}

// ---------------------------------------------------------------------------
// Maqueta del podio
// ---------------------------------------------------------------------------

function maquetaPodio(): Lienzo {
  const l = new Lienzo(ANCHO, ALTO, P.azul);
  const cx = ANCHO / 2;
  const t = TEMA_POR_DEFECTO.textos;

  const anchoTitulo = t.podioTitulo.length * 6 - 1;
  l.texto(t.podioTitulo, cx - anchoTitulo / 2, 12, P.verdeClaro);

  const margen = cx - anchoTitulo / 2;
  l.sprite(S.COPA, P, margen - 23, 9);
  l.sprite(S.COPA, P, ANCHO - margen + 13, 9);
  l.sprite(S.ESTRELLA, P, margen - 44, 9);
  l.sprite(S.ESTRELLA, P, ANCHO - margen + 36, 9);

  // Podio.
  const base = 82;
  const bloque = (x: number, alto: number) => {
    l.rect(P.azulProfundo, x, base - alto, 26, alto);
    l.rect(P.azulClaro, x, base - alto, 26, 3);
    l.rect(P.negro, x, base - 2, 26, 2);
    l.rect(P.negro, x + 24, base - alto, 2, alto);
  };

  bloque(cx - 13, 30);
  bloque(cx - 39, 21);
  bloque(cx + 13, 17);

  l.texto('1', cx - 2, base - 26, P.blanco);
  l.texto('2', cx - 28, base - 18, P.blanco);
  l.texto('3', cx + 24, base - 14, P.blanco);

  l.sprite(S.PILOTO_PODIO, P, cx - 7, base - 48);

  // Panel con marco de cuadros.
  const x = 26;
  const y = 92;
  const ancho = ANCHO - x * 2;
  const alto = 62;

  for (let dy = 0; dy < alto; dy += 8) {
    for (let dx = 0; dx < ancho; dx += 8) {
      const borde = dy === 0 || dy >= alto - 8 || dx === 0 || dx >= ancho - 8;
      if (!borde) continue;
      const claro = ((dx / 8 + dy / 8) | 0) % 2 === 0;
      l.rect(claro ? P.blanco : P.negro, x + dx, y + dy, 8, 8);
    }
  }
  l.rect(P.azul, x + 8, y + 8, ancho - 16, alto - 16);

  const nombre = 'JUAN CAMILO PEREZ';
  l.texto(nombre, cx - (nombre.length * 6 - 1) / 2, y + 18, P.crema);
  const dist = '2340 M';
  l.texto(dist, cx - (dist.length * 6 - 1) / 2, y + 34, P.blanco);

  const gracias = t.podioGracias;
  l.texto(gracias, cx - (gracias.length * 6 - 1) / 2, ALTO - 14, P.crema);

  return l;
}

// ---------------------------------------------------------------------------

const destino = new URL('../../salida-arte/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
mkdirSync(destino, { recursive: true });

const salidas: Array<[string, Lienzo, number]> = [
  ['sprites.png', hojaSprites(), 4],
  ['pantalla-carrera.png', maquetaPantalla(), 3],
  ['pantalla-podio.png', maquetaPodio(), 3],
];

for (const [nombre, lienzo, factor] of salidas) {
  const escalado = lienzo.escalar(factor);
  writeFileSync(destino + nombre, aPng(escalado));
  console.log(`${nombre}  ${escalado.ancho}×${escalado.alto}`);
}

console.log(`\nArte exportado a ${destino}`);
