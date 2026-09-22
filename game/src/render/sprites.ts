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
// Moto doble propósito, vista lateral, mirando a la derecha. 20×16.
// ---------------------------------------------------------------------------

/** Rodando normal. */
export const MOTO: Sprite = [
  '.........www..........',
  '........wwwww.........',
  '........wwwAw.........',
  '.........www..........',
  '........kkkk..........',
  '.......aaaaaa.........',
  '......aaaaaaaa...kkk..',
  '.....aaaaaaaaakkk.ww..',
  '....raaaaaaaar....w...',
  '...rrrrrrrrrrrrr..w...',
  '..rrrrrrrrrrrrrrrrr...',
  '..nnnnnrrrrrrrnnnnn...',
  '.nnnnnnnrrrrrnnnnnnn..',
  '.nnGGGnn.....nnGGGnn..',
  '.nnGGGnn.....nnGGGnn..',
  '.nnGGGnn.....nnGGGnn..',
  '.nnnnnnn.....nnnnnnn..',
  '..nnnnn.......nnnnn...',
];

/** Rueda delantera levantada al salir de una rampa. */
export const MOTO_WHEELIE: Sprite = [
  '.........www..........',
  '........wwwww......nnn',
  '........wwwAw....nnnnn',
  '.........www...nnnnnnn',
  '........kkkk...nnGGGnn',
  '.......aaaaaa..nnGGGnn',
  '......aaaaaaakknnGGGnn',
  '.....aaaaaaaaaknnnnnnn',
  '....raaaaaaaarr.nnnnn.',
  '...rrrrrrrrrrrrr......',
  '..rrrrrrrrrrrrrr......',
  '..nnnnnrrrrrrr........',
  '.nnnnnnnrrrrr.........',
  '.nnGGGnn..............',
  '.nnGGGnn..............',
  '.nnGGGnn..............',
  '.nnnnnnn..............',
  '..nnnnn...............',
];

/** Piloto en el suelo. La moto tumbada y el casco a un lado. */
export const MOTO_CAIDA: Sprite = [
  '......................',
  '......................',
  '......................',
  '......................',
  '......................',
  '......................',
  '......................',
  '......................',
  '...www................',
  '..wwwww......rrrr.....',
  '..wwwAw....rrrrrrrr...',
  '...www...rrrrrrrrrrr..',
  '.aaaaaa.nnnnn..nnnnn..',
  'aaaaaaa.nnGGGnnnGGGnn.',
  '.aaaaaa.nnnnn..nnnnn..',
  '..kkkk...nnn....nnn...',
  '......................',
  '......................',
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
 * Logo TVS coleccionable. 14×14.
 *
 * La letra del logo sobre una placa blanca, sin el caballo, como se pidió. La
 * T va con la inclinación del original.
 */
export const ITEM_TVS: Sprite = [
  '...wwwwwwww...',
  '..wwwwwwwwww..',
  '.wwwwwwwwwwww.',
  'wwwwwwwwwwwwww',
  'ww..aaaaaaa.ww',
  'ww.aaaaaaaa.ww',
  'ww....aaa...ww',
  'ww....aaa...ww',
  'ww...aaa....ww',
  'ww...aaa....ww',
  'wwww.aa...wwww',
  '.wwwwwwwwwwww.',
  '..wwwwwwwwww..',
  '...wwwwwwww...',
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
