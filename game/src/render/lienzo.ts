/**
 * Lienzo RGBA en memoria y escritura de PNG.
 *
 * Vive aparte porque lo usan dos exportadores: las maquetas de revisión que
 * genera exportar-png.ts y los iconos de la pantalla de instrucciones que
 * genera exportar-iconos.ts. Tenerlo duplicado era pedir que uno de los dos
 * se quedara atrás.
 *
 * El PNG se escribe a mano porque no hace falta más: cabecera, un bloque de
 * píxeles comprimido con zlib (que ya trae Node) y el CRC de cada trozo. Meter
 * una librería de imágenes para esto sería desproporcionado.
 */

import { deflateSync } from 'node:zlib';
import { pintarTexto as pintarTextoFuente } from './fuente';
import { LEYENDA } from './pixeles';
import type { Paleta } from './tema';

// ---------------------------------------------------------------------------
// Lienzo RGBA
// ---------------------------------------------------------------------------

export class Lienzo {
  readonly datos: Uint8Array;

  constructor(
    readonly ancho: number,
    readonly alto: number,
    fondo?: number
  ) {
    this.datos = new Uint8Array(ancho * alto * 4);
    if (fondo !== undefined) {
      this.rect(fondo, 0, 0, ancho, alto);
    }
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

  /**
   * Rota el contenido en grados, con vecino más cercano.
   *
   * Sirve para ver cómo se verá una pose que la escena gira en tiempo real,
   * sin tener que abrir el navegador.
   */
  rotar(grados: number, fondo = 0x303030): Lienzo {
    const salida = new Lienzo(this.ancho, this.alto, fondo);
    const rad = (grados * Math.PI) / 180;
    const cos = Math.cos(-rad);
    const sen = Math.sin(-rad);
    const cx = this.ancho / 2;
    const cy = this.alto / 2;

    for (let y = 0; y < this.alto; y++) {
      for (let x = 0; x < this.ancho; x++) {
        // Se recorre el destino y se busca en el origen: así no quedan huecos.
        const dx = x - cx;
        const dy = y - cy;
        const ox = Math.round(cx + dx * cos - dy * sen);
        const oy = Math.round(cy + dx * sen + dy * cos);

        if (ox < 0 || oy < 0 || ox >= this.ancho || oy >= this.alto) {
          continue;
        }

        const i = (oy * this.ancho + ox) * 4;
        if (this.datos[i + 3] === 0) {
          continue;
        }

        salida.punto((this.datos[i] << 16) | (this.datos[i + 1] << 8) | this.datos[i + 2], x, y);
      }
    }

    return salida;
  }

  /**
   * Devuelve un lienzo escalado por un entero, sin suavizado.
   *
   * Los píxeles transparentes se saltan en vez de copiarse. Antes se copiaban
   * y salían negros opacos, porque punto() siempre fija alfa 255. En las
   * maquetas daba igual —pintan su propio fondo— pero un icono suelto acababa
   * con un recuadro negro alrededor.
   */
  escalar(factor: number): Lienzo {
    const salida = new Lienzo(this.ancho * factor, this.alto * factor);

    for (let y = 0; y < this.alto; y++) {
      for (let x = 0; x < this.ancho; x++) {
        const i = (y * this.ancho + x) * 4;
        if (this.datos[i + 3] === 0) {
          continue;
        }
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

export function aPng(lienzo: Lienzo): Buffer {
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
