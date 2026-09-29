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
// La moto rodando viene del diseño del cliente e importa a su TAMAÑO NATIVO:
// llega dibujada en la matriz de 24×24 del proyecto, con un encaje del 100 %,
// así que cada píxel dibujado es un píxel del juego y no se pierde ninguno.
// Ver docs/arte-y-sonido.md.
//
//   npm run arte:importar -- ../imagenes_apoyo/piloto_nuevo.png MOTO //     --tamano 24x24 --fondo auto --offset -4,-4 //     --mapa "ff9f00=.,000000=n,ff0000=e,ffffff=w,0070c0=B,a6a6a6=t,ff6f00=O,ffff00=f"
//
// No se retoca a mano: si hay que cambiar algo, se cambia el PNG y se vuelve
// a importar.
//
// Ya no hay pose de salto: los impulsores sustituyeron a las rampas y la moto
// no despega del suelo en ningún momento.
//
// Las dos poses van alineadas por abajo y tienen que medir lo mismo: la escena
// las dibuja con origen (0.5, 1) sobre la línea del carril, y un cambio de
// tamaño haría saltar la moto al cambiar de pose.
// ---------------------------------------------------------------------------

/** Moto deportiva rodando. Casco y carenado rojos, traje blanco y azul. */
export const MOTO: Sprite = [
  '........................',
  '........................',
  '........................',
  '..........eeee..........',
  '.........eeeeee.........',
  '.........eeennn.........',
  '.........eeeenn.........',
  '.........neeeee.........',
  '........wwBBeee.........',
  '.......wwwBB............',
  '.......wwwBB...nn.......',
  '......wwwwBBBBB.n.......',
  '...eOewwwwwBBBnBne......',
  '....eeewwwwweeeteef.....',
  '......ewwwwweeettee.....',
  '...nnneeewwwweeettenn...',
  '..nn.nneeewwwee..tt.nn..',
  '.nn.ttnnenwwwnn.nnt..nn.',
  '.n..nttnenBBBnn.n.tn..n.',
  '.n....tttnBBBn..n..t..n.',
  '.nn...nnttBBBB..nn...nn.',
  '..nn.nn..........nn.nn..',
  '...nnn............nnn...',
  '........................',
];

/**
 * Piloto en el suelo y la moto tumbada. 24×24.
 *
 * DERIVADA, no viene de un diseño: es la única pose que no mandó el cliente.
 * Se recolorea cada vez que cambia el diseño de la moto, para que se lea como
 * el mismo piloto; ahora va con el casco y el carenado rojos y el traje blanco
 * y azul.
 *
 * Las ruedas van aplastadas, más anchas que altas. Con ruedas redondas la moto
 * seguía pareciendo de pie y el jugador no entendía por qué no avanzaba.
 */
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
  '........................',
  '........................',
  '........................',
  '..eeee..................',
  '.eennee.................',
  '.eennee....eeeeeeeeee...',
  '..eeee....ettttttttte...',
  '.wwwwww..BBeeeeeeeeeeBB.',
  'wwwwwwww.eeeeeeeeeeeeee.',
  '.wwwww..nnnnnn....nnnnnn',
  '..BBB...nn..nn....nn..nn',
  '..w.w...nnnnnn....nnnnnn',
  '........................',
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
 * Impulsor de pista. 13×8.
 *
 * Importado del diseño del cliente a su tamaño nativo, recortando el marco
 * vacío de la matriz de 24×24 para que quepa dentro de un carril de 16 px:
 *
 *   npm run arte:importar -- ../imagenes_apoyo/impulsador.png IMPULSOR  *     --tamano 24x24 --fondo auto --offset -4,-4 --recortar  *     --mapa "ffffff=.,595959=g,66ff33=x,12501a=X"
 *
 * Va pintado plano sobre el asfalto: es una placa, no un bulto. Sustituye a la
 * rampa, y con ella desapareció el salto.
 */
export const IMPULSOR: Sprite = [
  '.ggggggggg...',
  '..gXxxgXxxg..',
  '...gXxxgXxxg.',
  '....gXxxgXxxg',
  '...gXxxgXxxg.',
  '..gXxxgXxxg..',
  '.gXxxgXxxg...',
  'ggggggggg....',
];

/**
 * Charco de aceite. 26×9.
 *
 * DERIVADO, no viene de un diseño. Conserva píxel por píxel la huella del
 * charco de lodo al que sustituye —misma forma y mismo tamaño, así que frena
 * exactamente igual— y solo cambia de color: negro con tornasol violáceo en
 * vez de marrón.
 *
 * El borde claro no es decoración. Sobre la tierra naranja un charco oscuro
 * se veía solo; sobre el asfalto gris, un charco casi negro y sin contorno
 * desaparece, y el jugador no puede esquivar lo que no ve.
 */
export const ACEITE: Sprite = [
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

/**
 * Cono de vía. 10×14.
 *
 * Importado del diseño del cliente, recortado igual que el impulsor:
 *
 *   npm run arte:importar -- ../imagenes_apoyo/cono_via.png CONO  *     --tamano 24x24 --fondo auto --offset -4,-4 --recortar  *     --mapa "ffffff=.,ff6f00=O,d9d9d9=T,d25a00=o"
 *
 * Es el único obstáculo que tumba. Sustituye a la valla de obra.
 */
export const CONO: Sprite = [
  '....OO....',
  '....OO....',
  '...OOOO...',
  '...TTTT...',
  '...OOOO...',
  '..OOOOOO..',
  '..TTTTTT..',
  '..OOOOOO..',
  '.OOOOOOOO.',
  '.TTTTTTTT.',
  '.OOOOOOOO.',
  '.oooooooo.',
  'OOOOOOOOOO',
  'OOOOOOOOOO',
];

/**
 * Llave del bonus. 18×10.
 *
 * Importada del diseño del cliente, recortada para caber en un carril:
 *
 *   npm run arte:importar -- ../imagenes_apoyo/Llave_Bonus.png ITEM_LLAVE  *     --tamano 24x24 --fondo auto --offset -4,-4 --recortar  *     --mapa "ffffff=.,ffdb01=Y,000000=n,ff9f00=j,ffef8f=Z"
 *
 * Sustituye al logo TVS y vale lo mismo: +50 m.
 */
export const ITEM_LLAVE: Sprite = [
  '...nnnn...........',
  '..nYYYYn..........',
  '.nYYYYYYn.........',
  'nYYYjjYYYnnnnnnnn.',
  'nZYj..jYYYYYYYYYZn',
  'nZYj..jYYYYYjjjjYn',
  'nZYYjjYYYnnYYYYYYn',
  '.nZYYYYYn..jnjnjn.',
  '..nZYYYn..........',
  '...nnnn...........',
];

// ---------------------------------------------------------------------------
// Escenario
// ---------------------------------------------------------------------------

/*
 * Público. Tres poses importadas de los diseños del cliente, todas dibujadas
 * en la matriz de 24×24 del proyecto y recortadas al entrar:
 *
 *   npm run arte:importar -- ../imagenes_apoyo/espectador.png ESPECTADOR_BUSTO  *     --tamano 24x24 --fondo auto --offset -4,-4 --recortar  *     --mapa "ffffff=.,000000=n,3c7d22=r,ffcc99=k,808080=z"
 *
 *   ... espectador_2.png  -> ESPECTADOR
 *   ... espectador_3.png  -> ESPECTADOR_ANIMANDO
 *
 * La ropa va como 'r' a propósito. crearTribuna() sustituye ese carácter por
 * un color distinto en cada persona, así que con un solo dibujo se puebla la
 * grada entera sin declarar una copia por color. El verde del diseño es solo
 * el color de partida; en pantalla no aparece.
 */

/** Busto. Va en la fila de atrás, donde solo se ven cabezas y hombros. */
export const ESPECTADOR_BUSTO: Sprite = [
  '..nnnn..',
  '.nnkknn.',
  '.nkkkkn.',
  '.nnkknn.',
  'nnrrrrnn',
  'nrrrrrrn',
  'nrrrrrrn',
  'nnrrrrnn',
];

/** De pie, cuerpo entero. La fila de delante. */
export const ESPECTADOR: Sprite = [
  '..nnnn..',
  '.nnkknn.',
  '.nkkkkn.',
  '.nnkknn.',
  'nnrrrrnn',
  'nrrrrrrn',
  'nrrrrrrn',
  'nnrrrrnn',
  '.nrnnrn.',
  '.nznnzn.',
  '.nnnnnn.',
];

/** Con los brazos arriba, animando. */
export const ESPECTADOR_ANIMANDO: Sprite = [
  'nnn..nnn',
  'nrnnnnrn',
  'nrnkknrn',
  'nrkkkkrn',
  'nrrkkrrn',
  'nnrrrrnn',
  '.nrrrrn.',
  '.nrrrrn.',
  '.nrrrrn.',
  '.nrnnrn.',
  '.nznnzn.',
  '.nnnnnn.',
];

/**
 * Valla que separa al público de la pista. 15×8.
 *
 *   npm run arte:importar -- ../imagenes_apoyo/valla.png VALLA_TRIBUNA  *     --tamano 24x24 --fondo auto --offset -4,-4 --recortar --mapa "ffffff=.,808080=z"
 *
 * Es una valla portátil suelta, con sus patas, y se repite a lo largo de la
 * grada dejando un píxel entre una y otra: son vallas independientes puestas
 * en fila, no una barandilla continua. Por los huecos entre barrotes se ven
 * las piernas del público, que es lo que la hace parecer una valla y no una
 * franja gris.
 *
 * Va en plata clara y no en el gris del diseño: sobre el verde oscuro de la
 * grada, el #808080 original quedaba casi negro. No comparte color con el
 * calzado del público, que sí se queda en ese gris, porque lo que allí es
 * una sombra de dos píxeles aquí es toda la valla.
 */
export const VALLA_TRIBUNA: Sprite = [
  '..TTTTTTTTTTT..',
  '.T.T.T.T.T.T.T.',
  '.T.T.T.T.T.T.T.',
  '.T.T.T.T.T.T.T.',
  '.T.T.T.T.T.T.T.',
  '.TTTTTTTTTTTTT.',
  '.T...........T.',
  'TTT.........TTT',
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
