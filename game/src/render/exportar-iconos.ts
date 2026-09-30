/**
 * Exporta los iconos de la pantalla de instrucciones.
 *
 *   cd game && npm run arte:iconos
 *
 * Escribe PNG con transparencia en assets/img/instrucciones/, que sí se
 * empaqueta con el plugin (a diferencia de game/salida-arte/, que son maquetas
 * de revisión).
 *
 * Salen de los MISMOS sprites que dibuja el juego. Es la razón de que esto sea
 * un script y no unos PNG sueltos que alguien deje en una carpeta: el día que
 * se rediseñe el cono, las instrucciones enseñan el cono nuevo sin que nadie
 * se acuerde de actualizarlas.
 *
 * El factor de escala se elige por icono para que todos salgan con un ancho
 * parecido en pantalla, porque los sprites nativos van de 9 a 24 píxeles. Es
 * un entero a propósito: escalar un píxel a 5,5 lo deja de un ancho y a 5 el
 * de al lado, y el dibujo pierde el ritmo.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
import { anchoTexto } from './fuente';
import { Lienzo, aPng } from './lienzo';
import * as S from './sprites';
import { TEMA_POR_DEFECTO } from './tema';

const P = TEMA_POR_DEFECTO.paleta;

/** Un sprite suelto, recortado a su tamaño real y con el fondo transparente. */
function deSprite(filas: readonly string[]): Lienzo {
  const alto = filas.length;
  const ancho = Math.max(...filas.map((f) => f.length));
  const l = new Lienzo(ancho, alto);
  l.sprite(filas, P, 0, 0);
  return l;
}

/**
 * El medidor de temperatura del panel.
 *
 * No es un sprite: el juego lo arma con rectángulos en crearPanel() de
 * texturas.ts y en construirPanel() de Carrera.ts. Aquí se reproduce esa misma
 * geometría, recortada a la columna del centro del panel, con el marco azul y
 * el soporte de abajo que le dan su silueta.
 *
 * Las medidas se derivan del ancho del panel del juego en vez de escribirse a
 * mano, para que sigan cuadrando si algún día cambia el lienzo.
 *
 * La barra va a media carga: es lo que explica el texto de al lado —el rojo
 * sube y el verde es lo que queda— y se lee mejor que una vacía o una llena.
 */
function medidorTemp(): Lienzo {
  // Las mismas tres columnas que reparte crearPanel() sobre el lienzo de 200.
  const anchoPanel = 200;
  const columna = Math.floor(anchoPanel / 3);
  const cajaAncho = columna - 8;
  const cx = Math.floor(anchoPanel / 2);

  // El recorte va de la caja del medidor al final del soporte.
  const x0 = cx - Math.floor(cajaAncho / 2);
  const alto = 34;

  const l = new Lienzo(cajaAncho, alto, P.negro);
  const centro = cx - x0;

  const rotulo = TEMA_POR_DEFECTO.textos.rotuloTemperatura;
  l.texto(rotulo, centro - Math.floor(anchoTexto(rotulo) / 2), 4, P.rojo);

  // Marco de la caja: crearPanel() lo dibuja de un píxel.
  const marcoY = 13;
  const marcoAlto = 14;
  l.rect(P.azulClaro, 0, marcoY, cajaAncho, 1);
  l.rect(P.azulClaro, 0, marcoY + marcoAlto - 1, cajaAncho, 1);
  l.rect(P.azulClaro, 0, marcoY, 1, marcoAlto);
  l.rect(P.azulClaro, cajaAncho - 1, marcoY, 1, marcoAlto);

  // Barra: verde de fondo y rojo encima, como en construirPanel().
  const barraAncho = cajaAncho - 4;
  const barraX = centro - Math.floor(barraAncho / 2);
  l.rect(P.tempFria, barraX, 16, barraAncho, 8);
  l.rect(P.tempCaliente, barraX, 16, Math.floor(barraAncho * 0.45), 8);

  // Soporte, que es lo que hace que el medidor se lea como un instrumento.
  l.rect(P.azulClaro, centro - 7, 28, 14, 3);
  l.rect(P.azulClaro, centro - 11, 31, 22, 3);

  return l;
}

const destino = new URL('../../../assets/img/instrucciones/', import.meta.url).pathname.replace(
  /^\/([A-Za-z]:)/,
  '$1'
);
mkdirSync(destino, { recursive: true });

/*
 * El factor se elige para que el PNG salga ya del tamaño al que se va a ver,
 * o muy cerca. La ficha da como mucho 96×64 px en escritorio y 76×52 en el
 * teléfono; exportar más grande y dejar que el navegador reduzca estropea el
 * dibujo, porque al encoger arte de píxeles se pierden filas enteras y las
 * que quedan dejan de tener el mismo grosor.
 *
 * El medidor es el único que no cabe: es el más ancho y con factor 1 su
 * rótulo mediría cinco píxeles de alto. Se queda en 2 y se conforma con
 * reducirse un poco.
 */
const iconos: Array<[string, Lienzo, number]> = [
  ['temp.png', medidorTemp(), 2],
  ['llave.png', deSprite(S.ITEM_LLAVE), 4],
  ['impulsor.png', deSprite(S.IMPULSOR), 5],
  ['cono.png', deSprite(S.CONO), 4],
  ['aceite.png', deSprite(S.ACEITE), 3],
];

for (const [nombre, lienzo, factor] of iconos) {
  const escalado = lienzo.escalar(factor);
  writeFileSync(destino + nombre, aPng(escalado));
  console.log(`${nombre.padEnd(14)} ${lienzo.ancho}×${lienzo.alto} ×${factor} = ${escalado.ancho}×${escalado.alto}`);
}

console.log(`\nIconos exportados a ${destino}`);
