/**
 * Los sprites del juego, dibujados píxel a píxel.
 *
 * Cada carácter es un color de la paleta; ver LEYENDA en pixeles.ts. Se lee lo
 * que se dibuja, un diff muestra qué píxel cambió y el tema puede recolorearlo
 * todo sin tocar nada de aquí.
 *
 * Los nombres de textura son el contrato con el resto del juego: se pueden
 * redibujar las formas, pero renombrar una clave obliga a tocar la escena.
 */

import type { Sprite } from './pixeles';

// ---------------------------------------------------------------------------
// Moto y piloto, vista lateral, mirando a la derecha. Lienzo de 24×24.
//
// Las dos primeras poses vienen de los diseños del cliente e importan a su
// TAMAÑO NATIVO: llegan dibujadas en la matriz de 24×24 del proyecto, con un
// encaje del 100 %, así que cada píxel dibujado es un píxel del juego y no se
// pierde ninguno. Ver docs/arte-y-sonido.md.
//
//   npm run arte:importar -- ../imagenes_apoyo/normal.png MOTO //     --tamano 24x24 --fondo auto --offset -4,-4 //     --mapa "ff9f00=.,ffffff=w,000000=n,0070c0=B,ff0000=e,a6a6a6=t,e0e0e0=T"
//
//   ... y lo mismo con Salto.png para MOTO_WHEELIE.
//
// No se retocan a mano: si hay que cambiar algo, se cambia el PNG y se vuelve
// a importar.
//
// Las tres van alineadas por abajo y tienen que medir lo mismo: la escena las
// dibuja con origen (0.5, 1) sobre la línea del carril, y un cambio de tamaño
// haría saltar la moto al cambiar de pose.
// ---------------------------------------------------------------------------

/** Rodando normal. Casco azul, para distinguirlo del traje blanco. */
export const MOTO: Sprite = [
  '........................',
  '........................',
  '..........BBBB..........',
  '.........BBBBBB.........',
  '.........BBBnnn.........',
  '.........BBBBnn.........',
  '........nnBBBBB.........',
  '.......wwweeBBB.........',
  '......wwwweee...........',
  '......wwwweew...........',
  '......wwwweeeee.n.......',
  '......wwwwweeenen.......',
  '......wwwww...ttn.......',
  '....BBBwwwww.BBtBBBB....',
  '.......nnwwwwBBtt.......',
  '....nnBBBnwwwBBBttnn....',
  '...nn.BBBnwwwBB.nt.nn...',
  '..nn..BB.teeen.nnt..nn..',
  '..n..n..tteeen.n.tn..n..',
  '..n..tttt.eeee.n..t..n..',
  '..nn...nn..nn..nn...nn..',
  '...nn.nn........nn.nn...',
  '....nnn..........nnn....',
  '........................',
];

/**
 * En el aire. Se muestra tal cual, sin girarla más: sube a la rampa girada,
 * se mantiene así todo el vuelo y vuelve a la normal al tocar el suelo.
 */
export const MOTO_WHEELIE: Sprite = [
  '...BBBB.................',
  '..BBBBBB................',
  '..BBBnnn................',
  '..BBBBnn................',
  '...BBBBB..n....B........',
  '..nwwBBB..en..B.........',
  '..wweew..entnB..nnn.....',
  '..wweeewee.Btttnn.nn....',
  '..wwweeee..BBBtt...nn...',
  '...wwweewwwwB.nttn..n...',
  '...wwwwwwwwww.n..t..n...',
  '....wwwwwwBww.nn...nn...',
  '.....wwwnnBee..nn.nn....',
  '.....BnnBBBee...nnn.....',
  '....B..BBtteee..........',
  '...B..BBtt..............',
  '.....nnBtn..............',
  '....nn..tnn.............',
  '....n..nt.n.............',
  '....n..tt.n.............',
  '....nn...nn.............',
  '.....nn.nn..............',
  '......nnn...............',
  '........................',
];

/** Piloto en el suelo y la moto tumbada. Derivada; falta su diseño. */
export const MOTO_CAIDA: Sprite = [
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '........................',
  '..wwwww.................',
  '.wwwnnnw................',
  '.wwwnnnw...BBBBBB.......',
  '..wwwww..BBBBttBBBB.....',
  '..eeeee.BBBtt....BB.....',
  '.eeeeeee................',
  '.eeeee..nnn.......nnn...',
  '..eee..nnnnn.....nnnnn..',
  '..w.w.nn.t.nn...nn.t.nn.',
  '......nn...nn...nn...nn.',
  '......nn.t.nn...nn.t.nn.',
  '.......nnnnn.....nnnnn..',
  '........nnn.......nnn...',
];

/** Humo del motor sobrecalentado. 8×8, se anima con alfa y escala. */
export const HUMO: Sprite = [
  '..GGG...',
  '.GGGGG..',
  'GGGwwGG.',
  'GGwwwGGG',
  'GGGwwGG.',
  '.GGGGGG.',
  '..GGGG..',
  '...GG...',
];

// ---------------------------------------------------------------------------
// Obstáculos
// ---------------------------------------------------------------------------

/**
 * Rampa de tierra. 20×14.
 *
 * El cuerpo va en el marrón oscuro del borde de pista y la cara superior en
 * crema. La primera versión rellenaba la rampa con el color de la pista y era
 * literalmente invisible sobre ella.
 */
export const RAMPA: Sprite = [
  '..................cc',
  '................ccbb',
  '..............ccbbbb',
  '............ccbbbbbb',
  '..........ccbbbbbbbb',
  '........ccbbbbbbbbbb',
  '......ccbbbbbbbbbbbb',
  '....ccbbbbbbbbbbbbbb',
  '..ccbbbbbbbbbbbbbbbb',
  'ccbbbbbbbbbbbbbbbbbb',
  'bbbbbbbbbbbbbbbbbbbb',
  'bbnbbbbnbbbbnbbbbnbb',
  'bbbbbbbbbbbbbbbbbbbb',
  'nnnnnnnnnnnnnnnnnnnn',
];

/**
 * Charco de lodo. 26×9.
 *
 * Plano y con salpicaduras claras. La primera versión era casi negra y
 * redonda: sobre la pista naranja parecía un agujero, no barro.
 */
export const LODO: Sprite = [
  '.....MMMMMMMMMMMMMM.......',
  '..MMMmmmmmmmmmmmmmmMMM....',
  '.MMmmmmMmmmmmmMmmmmmmmMM..',
  'MMmmmmmmmmmMmmmmmmmmmmmmMM',
  'MmmmMmmmmmmmmmmmMmmmmmmmmM',
  'MMmmmmmmmMmmmmmmmmmmmMmmMM',
  '.MMmmmmmmmmmmMmmmmmmmmMM..',
  '..MMMmmmmmmmmmmmmmmMMM....',
  '.....MMMMMMMMMMMMMM.......',
];

/** Valla de obra. 12×14. */
export const VALLA: Sprite = [
  '............',
  'wwwwwwwwwwww',
  'wrrrwwwrrrww',
  'wwrrrwwwrrrw',
  'wwwrrrwwwrrr',
  'rwwwrrrwwwrr',
  'rrwwwrrrwwwr',
  'wwwwwwwwwwww',
  '...ww..ww...',
  '...ww..ww...',
  '...ww..ww...',
  '...ww..ww...',
  '..nnnnnnnn..',
  '............',
];

/**
 * Bonus TVS coleccionable. 19×16.
 *
 * Importado del diseño del cliente (imagenes_apoyo/Bonus_TVS_2.png) a su
 * tamaño nativo: el diseño llega en una rejilla de 19×16 con un encaje del
 * 100 %, y 16 px es justo el alto de un carril, así que entra sin remuestrear
 * y no se pierde ni un píxel.
 *
 *   npm run arte:importar -- ../imagenes_apoyo/Bonus_TVS_2.png ITEM_TVS  *     --tamano 19x16 --fondo auto --offset -4,-4  *     --mapa "ffffff=w,ff0000=r,156082=l"
 */
export const ITEM_TVS: Sprite = [
  '...................',
  '....rrrrrrrrrrr....',
  '....rwwwwwwwwwr....',
  '...rrwwwwwwwwwrr...',
  '.rrrwwwwwwwwwwwrrr.',
  '.rwwwwwwwwwwwwwwwr.',
  '.rwlllwlwwwlwlllwr.',
  '.rwwlwwlwwwlwlwwwr.',
  '.rwwlwwwlwlwwwwlwr.',
  '.rwwlwwwwlwwwlllwr.',
  '.rwwwwwwwwwwwwwwwr.',
  '.rrrwwwwwwwwwwwwrr.',
  '...rrwwwwwwwwwwrr..',
  '....rrwwwwwwwwrr...',
  '.....rrrrrrrrrr....',
  '...................',
];

/** Sombra de la moto en el aire. 14×4. */
export const SOMBRA: Sprite = [
  '...nnnnnnnn...',
  '.nnnnnnnnnnnn.',
  '.nnnnnnnnnnnn.',
  '...nnnnnnnn...',
];

// ---------------------------------------------------------------------------
// Escenario
// ---------------------------------------------------------------------------

/**
 * Un espectador. 6×10. Se repiten con colores distintos para poblar la
 * tribuna.
 */
export const ESPECTADOR: Sprite = [
  '..kk..',
  '.kkkk.',
  '..kk..',
  '.rrrr.',
  'rrrrrr',
  'rrrrrr',
  '.rrrr.',
  '.r..r.',
  '.n..n.',
  '.n..n.',
];

/** Espectador con los brazos arriba, para variar la grada. */
export const ESPECTADOR_ANIMANDO: Sprite = [
  'r....r',
  'r.kk.r',
  'rkkkkr',
  'rrkkrr',
  '.rrrr.',
  'rrrrrr',
  '.rrrr.',
  '.r..r.',
  '.n..n.',
  '.n..n.',
];

/** Bombilla de las luces navideñas. 4×5. */
export const BOMBILLA: Sprite = [
  '.nn.',
  'rrrr',
  'rrrr',
  '.rr.',
  '..r.',
];

/**
 * Pino nevado del fondo. 16×20.
 *
 * La nieve va en manchas sobre las ramas, no bordeando cada fila: al bordearlo
 * entero, el pino se leía como un triángulo hueco en vez de como un árbol.
 * El tronco es marrón; en negro parecía un cuadrado suelto.
 */
export const PINO: Sprite = [
  '.......ww.......',
  '......wwww......',
  '......VVVV......',
  '.....VVVVVV.....',
  '.....VwwwVV.....',
  '....VVVVVVVV....',
  '....VVVVVVVV....',
  '...VVVVVVVVVV...',
  '...VwwVVVVwwV...',
  '..VVVVVVVVVVVV..',
  '..VVVVVVVVVVVV..',
  '.VVVVVVVVVVVVVV.',
  '.VwwVVVVVVVVwwV.',
  'VVVVVVVVVVVVVVVV',
  'VVVVVVVVVVVVVVVV',
  '.VVVVVVVVVVVVVV.',
  '......mmmm......',
  '......mmmm......',
  '......mmmm......',
  '......mmmm......',
];

/** Nube. 20×8. */
export const NUBE: Sprite = [
  '.......wwww.........',
  '.....wwwwwwww.......',
  '...wwwwwwwwwwww.....',
  '..wwwwwwwwwwwwwww...',
  '.wwwwwwwwwwwwwwwwww.',
  'wwwwwwwwwwwwwwwwwwww',
  '..wwwwwwwwwwwwwwww..',
  '.....wwwwwwwwww.....',
];

// ---------------------------------------------------------------------------
// Podio
// ---------------------------------------------------------------------------

/**
 * Piloto celebrando, de frente. 14×18.
 *
 * El traje va en rojo: en azul se perdía contra el fondo azul del podio y solo
 * se veían el casco y las botas.
 */
export const PILOTO_PODIO: Sprite = [
  '.....wwww.....',
  '....wwwwww....',
  '...wwwAAwww...',
  '...wwwwwwww...',
  '....wkkkk.....',
  'w....kkkk....w',
  'ww...rrrr...ww',
  '.w..rrrrrr..w.',
  '.wwrrrrrrrrww.',
  '..wrrrrrrrrw..',
  '...rrrrrrrr...',
  '...rrrrrrrr...',
  '...rr....rr...',
  '...rr....rr...',
  '...rr....rr...',
  '...nn....nn...',
  '..nnnn..nnnn..',
  '..nnnn..nnnn..',
];

/**
 * Copa. 10×12.
 *
 * Boca ancha arriba, pie ancho abajo y tallo estrecho en medio. La primera
 * versión era simétrica y parecía un reloj de arena.
 */
export const COPA: Sprite = [
  'y.yyyyyy.y',
  'yyyyyyyyyy',
  'yyyyyyyyyy',
  'yyyyyyyyyy',
  '.yyyyyyyy.',
  '..yyyyyy..',
  '...yyyy...',
  '....yy....',
  '....yy....',
  '...yyyy...',
  '.yyyyyyyy.',
  'yyyyyyyyyy',
];

/**
 * Destello. 8×8.
 *
 * Una estrella de cinco puntas a este tamaño se ve como una mancha; un
 * destello de cuatro puntas se lee al instante.
 */
export const ESTRELLA: Sprite = [
  '...yy...',
  '...yy...',
  '..yyyy..',
  'yyyyyyyy',
  'yyyyyyyy',
  '..yyyy..',
  '...yy...',
  '...yy...',
];
