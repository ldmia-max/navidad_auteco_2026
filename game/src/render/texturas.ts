/**
 * Construcción de todas las texturas del juego.
 *
 * Los sprites sueltos salen de sprites.ts; aquí se componen las franjas
 * grandes que se repiten en horizontal (tribuna, pista, cerros) y los marcos
 * del panel inferior.
 *
 * Todo se genera al arrancar la escena. No hay imágenes que descargar: el
 * juego funciona en cuanto llega el bundle, que es lo que interesa en una
 * ventana de 90 minutos donde todo el tráfico del día llega junto.
 */

import Phaser from 'phaser';
import { anchoTexto, pintarTexto } from './fuente';
import { crearTextura, pintar, validar } from './pixeles';
import * as S from './sprites';
import {
  CARRILES_VISUALES,
  CARRIL_ALTO,
  CERROS_ALTO,
  CERROS_Y,
  CESPED_ALTO,
  NUBES_ALTO,
  PISTA_ALTO,
  PISTA_Y,
  TRIBUNA_ALTO,
  TRIBUNA_Y,
} from './medidas';
import type { Paleta, Tema } from './tema';

export const TEX = {
  moto: 'moto',
  motoCaida: 'moto-caida',
  humo: 'humo',

  impulsor: 'impulsor',
  aceite: 'aceite',
  cono: 'cono',
  item: 'item-llave',

  cielo: 'cielo',
  nubes: 'nubes',
  cerros: 'cerros',
  tribuna: 'tribuna',
  cartel: 'cartel',
  cesped: 'cesped',
  pista: 'pista',

  panel: 'panel',
  pilotoPodio: 'piloto-podio',
  copa: 'copa',
  estrella: 'estrella',
  podioBloque: 'podio-bloque',
  marcoCuadros: 'marco-cuadros',
} as const;

/** Ancho de las franjas que se repiten. Múltiplo del ancho de pantalla. */
const FRANJA = 320;

// Las medidas viven en medidas.ts, que no importa Phaser y por eso puede
// usarse desde el banco de pruebas.
export { CARRILES_VISUALES, CARRIL_ALTO, PISTA_ALTO } from './medidas';

function graficos(escena: Phaser.Scene): Phaser.GameObjects.Graphics {
  return escena.make.graphics({ x: 0, y: 0 }, false);
}

/**
 * Mezcla dos colores empaquetados.
 *
 * @param a      Color de origen.
 * @param b      Color de destino.
 * @param cuanto 0 devuelve a, 1 devuelve b.
 */
function mezclar(a: number, b: number, cuanto: number): number {
  const mez = (desp: number) => {
    const ca = (a >> desp) & 0xff;
    const cb = (b >> desp) & 0xff;
    return Math.round(ca + (cb - ca) * cuanto) & 0xff;
  };

  return (mez(16) << 16) | (mez(8) << 8) | mez(0);
}

function rect(
  g: Phaser.GameObjects.Graphics,
  color: number,
  x: number,
  y: number,
  w: number,
  h: number
): void {
  g.fillStyle(color, 1);
  g.fillRect(x, y, w, h);
}

/**
 * Crea todas las texturas.
 *
 * @param escena Escena donde registrarlas.
 * @param tema   Colores y textos.
 */
export function crearTexturas(escena: Phaser.Scene, tema: Tema): void {
  const p = tema.paleta;

  // --- Sprites sueltos -----------------------------------------------------
  crearTextura(escena, TEX.moto, S.MOTO, p);
  crearTextura(escena, TEX.motoCaida, S.MOTO_CAIDA, p);
  crearTextura(escena, TEX.humo, S.HUMO, p);
  crearTextura(escena, TEX.impulsor, S.IMPULSOR, p);
  crearTextura(escena, TEX.aceite, S.ACEITE, p);
  crearTextura(escena, TEX.cono, S.CONO, p);
  crearTextura(escena, TEX.item, S.ITEM_LLAVE, p);
  crearTextura(escena, TEX.pilotoPodio, S.PILOTO_PODIO, p);
  crearTextura(escena, TEX.copa, S.COPA, p);
  crearTextura(escena, TEX.estrella, S.ESTRELLA, p);

  // --- Capas del fondo -----------------------------------------------------
  crearCielo(escena, p);
  crearNubes(escena, p);
  crearCerros(escena, p);
  crearTribuna(escena, p);
  crearCartel(escena, tema);
  crearCesped(escena, p);
  crearPista(escena, p);

  // --- Podio ---------------------------------------------------------------
  crearPodioBloque(escena, p);
  crearMarcoCuadros(escena, p);
}

/** Degradado del cielo en bandas, como se hacía cuando no había degradados. */
function crearCielo(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const alto = PISTA_Y;

  rect(g, p.cieloAlto, 0, 0, FRANJA, alto);

  /*
   * Tres bandas que aclaran hacia el horizonte, como se hacía cuando no había
   * degradados. La mezcla se calcula a mano: Interpolate.ColorWithColor()
   * devuelve un objeto {r,g,b,a} y no un color empaquetado, así que pedirle
   * .color daba undefined y la banda salía negra.
   */
  const corteMedio = CERROS_Y - 2;
  const corteBajo = TRIBUNA_Y - 2;
  rect(g, mezclar(p.cieloAlto, p.cielo, 0.5), 0, corteMedio, FRANJA, alto - corteMedio);
  rect(g, p.cielo, 0, corteBajo, FRANJA, alto - corteBajo);

  g.generateTexture(TEX.cielo, FRANJA, alto);
  g.destroy();
}

/** Capa de nubes, la más lejana del parallax. */
function crearNubes(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const alto = NUBES_ALTO;

  // Posiciones fijas: el parallax las mueve, no hace falta aleatoriedad.
  for (const [x, y] of [
    [10, 2],
    [90, 8],
    [160, 0],
    [230, 7],
    [290, 3],
  ]) {
    pintar(g, S.NUBE, p, x, y);
  }

  g.generateTexture(TEX.nubes, FRANJA, alto);
  g.destroy();
}

/** Cerros y pinos nevados del fondo. */
function crearCerros(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const alto = CERROS_ALTO;

  /*
   * Cerros en verdes claros. Los pinos van en verde oscuro, así que si los
   * cerros fueran del mismo tono los pinos desaparecerían y solo se vería su
   * contorno de nieve, que es lo que pasaba en la primera versión.
   */
  g.fillStyle(p.verde, 1);
  for (let x = -20; x < FRANJA + 40; x += 64) {
    g.fillTriangle(x, alto, x + 32, 4, x + 64, alto);
  }
  g.fillStyle(p.verdeClaro, 1);
  for (let x = 12; x < FRANJA + 40; x += 64) {
    g.fillTriangle(x, alto, x + 26, 12, x + 52, alto);
  }

  // Pinos: los de navidad del escenario.
  const { alto: altoPino } = validar('pino', S.PINO);
  for (const x of [30, 118, 200, 272]) {
    pintar(g, S.PINO, p, x, alto - altoPino);
  }

  g.generateTexture(TEX.cerros, FRANJA, alto);
  g.destroy();
}

/**
 * Tribuna con público y luces navideñas.
 *
 * En la referencia del original, la tribuna lleva banderines y el cartel del
 * patrocinador. Aquí los banderines son luces de navidad, que es de lo que va
 * la campaña.
 */
function crearTribuna(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const alto = TRIBUNA_ALTO;

  rect(g, p.verdeOscuro, 0, 0, FRANJA, alto);

  // Guirnalda: el cable y las bombillas alternando color.
  rect(g, p.negro, 0, 6, FRANJA, 1);
  const coloresLuz: Array<keyof Paleta> = ['rojo', 'verdeClaro', 'crema', 'azulClaro'];

  for (let i = 0, x = 4; x < FRANJA; x += 12, i++) {
    const color = coloresLuz[i % coloresLuz.length];
    // La bombilla se pinta recoloreada: se reutiliza el sprite cambiando la
    // 'r' de la leyenda por el color que toca.
    const recoloreada = S.BOMBILLA.map((fila) => fila.replace(/r/g, '§'));
    pintarConReemplazo(g, recoloreada, p, x, 6, '§', p[color]);
  }

  // Dos filas de público, desfasadas. La de atrás asoma por encima.
  const coloresRopa: Array<keyof Paleta> = ['rojo', 'azul', 'crema', 'verdeClaro', 'amarillo', 'blanco'];

  for (let fila = 0; fila < 2; fila++) {
    const y = 9 + fila * 7;

    for (let i = 0, x = fila * 4; x < FRANJA; x += 9, i++) {
      const color = coloresRopa[(i + fila * 3) % coloresRopa.length];
      const anima = (i + fila) % 3 === 0;
      const base = anima ? S.ESPECTADOR_ANIMANDO : S.ESPECTADOR;
      const recoloreada = base.map((f) => f.replace(/r/g, '§'));
      pintarConReemplazo(g, recoloreada, p, x, y, '§', p[color]);
    }
  }

  // Barandilla delante del público.
  rect(g, p.blanco, 0, alto - 5, FRANJA, 2);
  rect(g, p.verde, 0, alto - 3, FRANJA, 3);

  g.generateTexture(TEX.tribuna, FRANJA, alto);
  g.destroy();
}

/**
 * Pinta un sprite sustituyendo un carácter por un color concreto.
 *
 * Sirve para repetir una figura (un espectador, una bombilla) en varios
 * colores sin declarar una copia del sprite por cada color.
 */
function pintarConReemplazo(
  g: Phaser.GameObjects.Graphics,
  filas: readonly string[],
  paleta: Paleta,
  dx: number,
  dy: number,
  marcador: string,
  color: number
): void {
  const sinMarcador = filas.map((f) => f.replace(new RegExp(marcador, 'g'), '.'));
  pintar(g, sinMarcador, paleta, dx, dy);

  g.fillStyle(color, 1);
  for (let y = 0; y < filas.length; y++) {
    for (let x = 0; x < filas[y].length; x++) {
      if (filas[y][x] === marcador) {
        g.fillRect(dx + x, dy + y, 1, 1);
      }
    }
  }
}

/** El cartel de la tribuna, donde el original decía NINTENDO. */
function crearCartel(escena: Phaser.Scene, tema: Tema): void {
  const p = tema.paleta;
  const texto = tema.textos.cartelTribuna;
  const anchoLetras = anchoTexto(texto);
  const ancho = anchoLetras + 14;
  const alto = 17;

  const g = graficos(escena);

  rect(g, p.blanco, 0, 0, ancho, alto);
  rect(g, p.azul, 2, 2, ancho - 4, alto - 4);
  pintarTexto(g, texto, 7, 5, p.blanco);

  g.generateTexture(TEX.cartel, ancho, alto);
  g.destroy();
}

/** Franja de césped entre la tribuna y la pista. */
function crearCesped(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const alto = CESPED_ALTO;

  rect(g, p.verde, 0, 0, FRANJA, alto);

  // Matas sueltas para que al desplazarse se note el movimiento.
  for (let x = 0; x < FRANJA; x += 16) {
    rect(g, p.verdeClaro, x + 3, 2, 2, 2);
    rect(g, p.verdeOscuro, x + 9, 4, 3, 2);
  }

  rect(g, p.pistaBorde, 0, alto - 2, FRANJA, 2);

  g.generateTexture(TEX.cesped, FRANJA, alto);
  g.destroy();
}

/**
 * La pista: cuatro carriles de asfalto con sus líneas.
 *
 * Es una calle de autódromo, no un camino de tierra. El asfalto es casi plano
 * a propósito: el grano se insinúa con parches apenas más claros y con la
 * junta oscura al pie de cada carril, porque un asfalto moteado a la manera de
 * la grava anterior se lee como ruido y compite con los obstáculos, que son lo
 * único que el jugador tiene que ver rápido.
 */
function crearPista(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const ancho = 64;

  for (let carril = 0; carril < CARRILES_VISUALES; carril++) {
    const y = carril * CARRIL_ALTO;
    rect(g, carril % 2 === 0 ? p.pista : p.pistaAlt, 0, y, ancho, CARRIL_ALTO);

    // Parches del asfalto: posiciones fijas, no aleatorias, para que la franja
    // repita sin costura.
    for (let x = (carril * 11) % 32; x < ancho; x += 32) {
      rect(g, p.pistaAlt, x, y + 5, 5, 2);
      rect(g, p.pistaBorde, x + 17, y + 10, 4, 1);
    }

    // Junta al pie del carril, que es lo que da la sensación de calzada.
    rect(g, p.pistaBorde, 0, y + CARRIL_ALTO - 1, ancho, 1);
  }

  // Líneas discontinuas de separación, blancas como las de la vía.
  g.fillStyle(p.blanco, 1);
  for (let carril = 1; carril < CARRILES_VISUALES; carril++) {
    for (let x = 0; x < ancho; x += 16) {
      g.fillRect(x, carril * CARRIL_ALTO, 9, 1);
    }
  }

  g.generateTexture(TEX.pista, ancho, PISTA_ALTO);
  g.destroy();
}

/**
 * Panel inferior completo, según la imagen de referencia.
 *
 * Tres recuadros de marco azul: distancia a la izquierda, temperatura al
 * centro con su soporte, y tiempo a la derecha. Los rótulos van en rojo y los
 * datos en blanco, como en el original.
 */
export function crearPanel(
  escena: Phaser.Scene,
  tema: Tema,
  ancho: number,
  alto: number
): void {
  const p = tema.paleta;
  const g = graficos(escena);

  rect(g, p.negro, 0, 0, ancho, alto);

  const cx = Math.floor(ancho / 2);

  /*
   * Tres columnas de un tercio del panel. Con el lienzo de 320 cabían dos
   * recuadros de 72 px y un medidor de 68 con sus topes laterales; en 200 esa
   * misma distribución sumaba 212 y se pisaban unos a otros.
   */
  const columna = Math.floor(ancho / 3);
  const cajaAncho = columna - 8;
  const izquierda = Math.floor((columna - cajaAncho) / 2);
  const derecha = ancho - izquierda - cajaAncho;

  // Rótulos.
  const rotuloDist = tema.textos.rotuloDistancia;
  const rotuloTemp = tema.textos.rotuloTemperatura;
  const rotuloTime = tema.textos.rotuloTiempo;

  const centroIzq = izquierda + Math.floor(cajaAncho / 2);
  const centroDer = derecha + Math.floor(cajaAncho / 2);

  pintarTexto(g, rotuloDist, centroIzq - Math.floor(anchoTexto(rotuloDist) / 2), 4, p.rojo);
  pintarTexto(g, rotuloTemp, cx - Math.floor(anchoTexto(rotuloTemp) / 2), 4, p.rojo);
  pintarTexto(g, rotuloTime, centroDer - Math.floor(anchoTexto(rotuloTime) / 2), 4, p.rojo);

  // Recuadros de dato.
  marco(g, p.azulClaro, izquierda, 13, cajaAncho, 15);
  marco(g, p.azulClaro, derecha, 13, cajaAncho, 15);

  // Medidor central con su soporte, como en la referencia.
  marco(g, p.azulClaro, cx - Math.floor(cajaAncho / 2), 13, cajaAncho, 14);
  rect(g, p.azulClaro, cx - 7, 28, 14, 3);
  rect(g, p.azulClaro, cx - 11, 31, 22, 3);

  g.generateTexture(TEX.panel, ancho, alto);
  g.destroy();
}

/** Marco de un píxel. */
function marco(
  g: Phaser.GameObjects.Graphics,
  color: number,
  x: number,
  y: number,
  w: number,
  h: number
): void {
  g.fillStyle(color, 1);
  g.fillRect(x, y, w, 1);
  g.fillRect(x, y + h - 1, w, 1);
  g.fillRect(x, y, 1, h);
  g.fillRect(x + w - 1, y, 1, h);
}

/** Bloque del podio, con su cara superior más clara. */
function crearPodioBloque(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const ancho = 26;
  const alto = 30;

  rect(g, p.azulProfundo, 0, 0, ancho, alto);
  rect(g, p.azulClaro, 0, 0, ancho, 3);
  rect(g, p.negro, 0, alto - 2, ancho, 2);
  rect(g, p.negro, ancho - 2, 0, 2, alto);

  g.generateTexture(TEX.podioBloque, ancho, alto);
  g.destroy();
}

/** Marco de cuadros de meta, como el de la pantalla final del original. */
function crearMarcoCuadros(escena: Phaser.Scene, p: Paleta): void {
  const g = graficos(escena);
  const lado = 8;
  const ancho = lado * 2;

  rect(g, p.blanco, 0, 0, lado, lado);
  rect(g, p.negro, lado, 0, lado, lado);
  rect(g, p.negro, 0, lado, lado, lado);
  rect(g, p.blanco, lado, lado, lado, lado);

  g.generateTexture(TEX.marcoCuadros, ancho, ancho);
  g.destroy();
}
