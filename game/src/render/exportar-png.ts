/**
 * Exporta el arte a PNG para poder mirarlo.
 *
 *   cd game && npm run arte:png
 *
 * Genera dos archivos en game/salida-arte/: una hoja con todos los sprites y
 * una maqueta de la pantalla completa. Sirve para revisar el arte sin abrir el
 * navegador y para adjuntar capturas cuando se discute un cambio.
 *
 * El lienzo y la escritura del PNG están en lienzo.ts, que comparte con el
 * exportador de iconos.
 *
 * OJO: este archivo REDIBUJA la escena con su propia copia de la disposición.
 * Cada vez que cambie texturas.ts hay que repasarlo, que ya se ha quedado
 * atrás dos veces.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
import { Lienzo, aPng } from './lienzo';
import * as S from './sprites';
import { TEMA_POR_DEFECTO } from './tema';

// ---------------------------------------------------------------------------
// Hoja de sprites
// ---------------------------------------------------------------------------

const P = TEMA_POR_DEFECTO.paleta;

function hojaSprites(): Lienzo {
  const items: Array<[string, readonly string[]]> = [
    ['MOTO', S.MOTO],
    ['CAIDA', S.MOTO_CAIDA],
    ['IMPULSOR', S.IMPULSOR],
    ['CONO', S.CONO],
    ['ACEITE', S.ACEITE],
    ['LLAVE', S.ITEM_LLAVE],
    ['HUMO', S.HUMO],
    ['VALLA', S.VALLA_TRIBUNA],
    ['BUSTO', S.ESPECTADOR_BUSTO],
    ['PUBLICO', S.ESPECTADOR],
    ['ANIMANDO', S.ESPECTADOR_ANIMANDO],
    ['LUZ', S.BOMBILLA],
    ['PINO', S.PINO],
    ['NUBE', S.NUBE],
    ['PILOTO', S.PILOTO_PODIO],
    ['COPA', S.COPA],
    ['ESTRELLA', S.ESTRELLA],
  ];

  const celda = 56;
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

/*
 * Las medidas se leen de medidas.ts, no se copian. Este archivo duplica a mano
 * el dibujo de la escena para poder verla sin navegador, y si además duplicara
 * los números, cambiar el formato del lienzo dejaría la maqueta mintiendo sin
 * que nada fallara.
 */
import {
  ALTO,
  ANCHO,
  CARRIL_ALTO,
  CERROS_ALTO,
  CERROS_Y,
  CESPED_ALTO,
  CESPED_Y,
  MOTO_X,
  PANEL_ALTO,
  PANEL_Y,
  PISTA_Y,
  TRIBUNA_ALTO,
  TRIBUNA_Y,
} from './medidas';

function maquetaPantalla(): Lienzo {
  const l = new Lienzo(ANCHO, ALTO, P.cieloAlto);

  // Cielo en bandas.
  l.rect(P.cielo, 0, TRIBUNA_Y - 2, ANCHO, PISTA_Y - TRIBUNA_Y + 2);

  // Nubes.
  for (const [x, y] of [
    [10, 4],
    [120, 0],
    [230, 8],
  ]) {
    l.sprite(S.NUBE, P, x, y);
  }

  // Cerros y pinos.
  for (let x = -20; x < ANCHO + 40; x += 64) {
    for (let i = 0; i < CERROS_ALTO; i++) {
      const mitad = Math.round((i / CERROS_ALTO) * 32);
      l.rect(P.verde, x + 32 - mitad, CERROS_Y + i, mitad * 2, 1);
    }
  }
  for (const x of [30, 118, 200, 272]) {
    l.sprite(S.PINO, P, x, 26);
  }

  // Tribuna.
  const T0 = TRIBUNA_Y;
  l.rect(P.verdeOscuro, 0, T0, ANCHO, TRIBUNA_ALTO);
  l.rect(P.negro, 0, T0, ANCHO, 1);

  const coloresLuz = [P.rojo, P.verdeClaro, P.crema, P.azulClaro];
  for (let i = 0, x = 4; x < ANCHO; x += 12, i++) {
    l.sprite(S.BOMBILLA, P, x, T0, ['r', coloresLuz[i % coloresLuz.length]]);
  }

  // Mismas dos filas que crearTribuna() en texturas.ts.
  const coloresRopa = [
    P.rojo,
    P.azul,
    P.crema,
    P.verdeClaro,
    P.amarillo,
    P.blanco,
    P.azulProfundo,
    P.rojoOscuro,
    P.grisClaro,
  ];

  // La fila de atrás va recortada para no asomar por las esquinas
  // transparentes de las cabezas de delante. Ver crearTribuna().
  for (let i = 0, x = -4; x < ANCHO; x += 9, i++) {
    l.sprite(S.ESPECTADOR_BUSTO.slice(0, 7), P, x, T0 + 5, ['r', coloresRopa[(i * 4) % coloresRopa.length]]);
  }

  for (let i = 0, x = 0; x < ANCHO; x += 9, i++) {
    const anima = i % 3 === 0;
    l.sprite(
      anima ? S.ESPECTADOR_ANIMANDO : S.ESPECTADOR,
      P,
      x,
      T0 + (anima ? 12 : 13),
      ['r', coloresRopa[(i * 2 + 1) % coloresRopa.length]]
    );
  }
  // Vallas cada 16 px, igual que crearTribuna().
  for (let x = 0; x < ANCHO; x += 16) {
    l.sprite(S.VALLA_TRIBUNA, P, x, T0 + TRIBUNA_ALTO - S.VALLA_TRIBUNA.length);
  }

  // Cartel de la tribuna.
  const textoCartel = TEMA_POR_DEFECTO.textos.cartelTribuna;
  const anchoCartel = textoCartel.length * 6 - 1 + 14;
  const xCartel = Math.floor((ANCHO - anchoCartel) / 2);
  l.rect(P.blanco, xCartel, T0 + 2, anchoCartel, 17);
  l.rect(P.azul, xCartel + 2, T0 + 4, anchoCartel - 4, 13);
  l.texto(textoCartel, xCartel + 7, T0 + 7, P.blanco);

  // Césped.
  l.rect(P.verde, 0, CESPED_Y, ANCHO, CESPED_ALTO);
  for (let x = 0; x < ANCHO; x += 16) {
    l.rect(P.verdeClaro, x + 3, 76, 2, 2);
    l.rect(P.verdeOscuro, x + 9, 78, 3, 2);
  }
  l.rect(P.pistaBorde, 0, PISTA_Y - 2, ANCHO, 2);

  // Pista de asfalto. Mismo dibujo que crearPista() en texturas.ts.
  for (let carril = 0; carril < 4; carril++) {
    const y = PISTA_Y + carril * CARRIL_ALTO;
    l.rect(carril % 2 === 0 ? P.pista : P.pistaAlt, 0, y, ANCHO, CARRIL_ALTO);

    for (let x = (carril * 11) % 32; x < ANCHO; x += 32) {
      l.rect(P.pistaAlt, x, y + 5, 5, 2);
      l.rect(P.pistaBorde, x + 17, y + 10, 4, 1);
    }

    l.rect(P.pistaBorde, 0, y + CARRIL_ALTO - 1, ANCHO, 1);

    if (carril > 0) {
      for (let x = 0; x < ANCHO; x += 16) {
        l.rect(P.blanco, x, y, 9, 1);
      }
    }
  }

  /*
   * Todo se apoya en el centro del carril, igual que en la escena: los que se
   * pisan (moto, cono) con su base ahí, y los planos (impulsor, aceite, llave)
   * centrados sobre esa línea.
   */
  const apoyo = (carril: number) => PISTA_Y + carril * CARRIL_ALTO + CARRIL_ALTO / 2;
  const centrado = (sprite: readonly string[], carril: number) =>
    apoyo(carril) - Math.floor(sprite.length / 2);

  l.sprite(S.IMPULSOR, P, 104, centrado(S.IMPULSOR, 2));
  l.sprite(S.CONO, P, 140, apoyo(1) - S.CONO.length);
  l.sprite(S.ACEITE, P, 150, centrado(S.ACEITE, 3));
  l.sprite(S.ITEM_LLAVE, P, 160, centrado(S.ITEM_LLAVE, 0));

  // Moto en el tercer carril, donde la pone la escena.
  l.sprite(S.MOTO, P, MOTO_X - Math.floor(S.MOTO[0].length / 2), apoyo(2) - S.MOTO.length);

  /*
   * Aviso del motor, con su caja negra. Se dibuja aquí para poder comprobar
   * que queda centrado sin abrir el navegador: el fondo se dimensiona en
   * tiempo de ejecución y ya se descolocó una vez.
   */
  const aviso = TEMA_POR_DEFECTO.textos.avisoSobrecalentado;
  const anchoAviso = aviso.length * 6 - 1;
  const cajaAviso = anchoAviso + 6;
  l.rect(P.negro, Math.floor(ANCHO / 2 - cajaAviso / 2), PISTA_Y - 13, cajaAviso, 11);
  l.texto(aviso, Math.floor(ANCHO / 2 - anchoAviso / 2), PISTA_Y - 11, P.rojo);

  // Panel inferior. Mismo reparto en tres columnas que crearPanel().
  const panelY = PANEL_Y;
  const cx = Math.floor(ANCHO / 2);
  const columna = Math.floor(ANCHO / 3);
  const cajaAncho = columna - 8;
  const izquierda = Math.floor((columna - cajaAncho) / 2);
  const derecha = ANCHO - izquierda - cajaAncho;
  const centroIzq = izquierda + Math.floor(cajaAncho / 2);
  const centroDer = derecha + Math.floor(cajaAncho / 2);

  l.rect(P.negro, 0, panelY, ANCHO, PANEL_ALTO);
  l.texto('DIST', centroIzq - 11, panelY + 4, P.rojo);
  l.texto('TEMP', cx - 11, panelY + 4, P.rojo);
  l.texto('TIME', centroDer - 11, panelY + 4, P.rojo);

  const marco = (x: number, y: number, w: number, h: number) => {
    l.rect(P.azulClaro, x, y, w, 1);
    l.rect(P.azulClaro, x, y + h - 1, w, 1);
    l.rect(P.azulClaro, x, y, 1, h);
    l.rect(P.azulClaro, x + w - 1, y, 1, h);
  };

  marco(izquierda, panelY + 13, cajaAncho, 15);
  marco(derecha, panelY + 13, cajaAncho, 15);
  marco(cx - Math.floor(cajaAncho / 2), panelY + 13, cajaAncho, 14);
  l.rect(P.azulClaro, cx - 7, panelY + 28, 14, 3);
  l.rect(P.azulClaro, cx - 11, panelY + 31, 22, 3);

  const anchoBarra = cajaAncho - 4;
  const barraX = cx - Math.floor(anchoBarra / 2);
  l.rect(P.tempFria, barraX, panelY + 16, anchoBarra, 8);
  l.rect(P.tempCaliente, barraX, panelY + 16, Math.round(anchoBarra * 0.62), 8);

  l.texto('1284M', centroIzq - 14, panelY + 17, P.blanco);
  l.texto('0:47', centroDer - 11, panelY + 17, P.blanco);

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
  const base = 106;
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

  // Panel con marco de cuadros. Mismas medidas que Podio.ts.
  const x = 16;
  const y = 116;
  const ancho = ANCHO - x * 2;
  const alto = 56;

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
  l.texto(gracias, cx - (gracias.length * 6 - 1) / 2, ALTO - 12, P.crema);

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
