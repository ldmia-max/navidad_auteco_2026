/**
 * Banco de pruebas del arte.
 *
 *   cd game && npm run arte
 *
 * Comprueba que los sprites están bien formados y que el tema se fusiona como
 * debe. Un sprite con una fila de más o de menos descoloca todo el dibujo y el
 * fallo es difícil de ver leyendo: más vale que salte aquí y no en la jornada.
 *
 * No depende de Phaser ni del navegador.
 */

import { readFileSync } from 'node:fs';

import { anchoTexto, normalizar } from './fuente';
import { DISTANCIA_MAXIMA_M } from '../sim/constantes';
import { CARRIL_ALTO } from './medidas';
import { LEYENDA, validar } from './pixeles';
import * as S from './sprites';
import { TEMA_POR_DEFECTO, fusionarTema } from './tema';

let fallos = 0;

function comprobar(etiqueta: string, condicion: boolean, detalle = ''): void {
  if (!condicion) {
    fallos++;
  }
  console.log(`${condicion ? 'OK   ' : 'FALLA'} ${etiqueta}${detalle ? '  ' + detalle : ''}`);
}

// ===========================================================================
console.log('=== Sprites bien formados ===\n');

const sprites: Array<[string, readonly string[]]> = [
  ['MOTO', S.MOTO],
  ['MOTO_CAIDA', S.MOTO_CAIDA],
  ['HUMO', S.HUMO],
  ['IMPULSOR', S.IMPULSOR],
  ['ACEITE', S.ACEITE],
  ['CONO', S.CONO],
  ['ITEM_LLAVE', S.ITEM_LLAVE],
  ['ESPECTADOR', S.ESPECTADOR],
  ['ESPECTADOR_ANIMANDO', S.ESPECTADOR_ANIMANDO],
  ['BOMBILLA', S.BOMBILLA],
  ['PINO', S.PINO],
  ['NUBE', S.NUBE],
  ['PILOTO_PODIO', S.PILOTO_PODIO],
  ['COPA', S.COPA],
  ['ESTRELLA', S.ESTRELLA],
];

for (const [nombre, sprite] of sprites) {
  try {
    const { ancho, alto } = validar(nombre, sprite);
    comprobar(`${nombre}`, true, `${ancho}×${alto}`);
  } catch (e) {
    comprobar(`${nombre}`, false, (e as Error).message);
  }
}

// ===========================================================================
console.log('\n=== Tamaños que el juego da por hechos ===');

/*
 * La moto y su caída tienen que medir lo mismo: si no, al cambiar de textura
 * la moto daría un salto en pantalla.
 */
const medidas = (s: readonly string[]) => `${s[0].length}×${s.length}`;

comprobar('la moto y la caída miden igual', medidas(S.MOTO) === medidas(S.MOTO_CAIDA), medidas(S.MOTO_CAIDA));

/*
 * Nada de lo que va sobre la pista puede sobresalir de su carril: se
 * confundiría con el de al lado y el jugador esquivaría donde no debe. Los
 * diseños llegan en la matriz de 24×24, más alta que un carril, así que se
 * importan con --recortar y esto comprueba que el recorte bastó.
 */
for (const [nombre, sprite] of [
  ['ITEM_LLAVE', S.ITEM_LLAVE],
  ['CONO', S.CONO],
  ['IMPULSOR', S.IMPULSOR],
] as Array<[string, readonly string[]]>) {
  comprobar(
    `${nombre} cabe en un carril`,
    sprite.length <= CARRIL_ALTO,
    `${sprite.length} px de alto, carril de ${CARRIL_ALTO}`
  );
}

// ===========================================================================
console.log('\n=== Leyenda de colores ===');

const usados = new Set<string>();
for (const [, sprite] of sprites) {
  for (const fila of sprite) {
    for (const ch of fila) {
      usados.add(ch);
    }
  }
}

const desconocidos = Array.from(usados).filter((ch) => !(ch in LEYENDA));
comprobar('todos los caracteres están en la leyenda', desconocidos.length === 0, desconocidos.join(' '));

const sinUsar = Object.keys(LEYENDA).filter((ch) => !usados.has(ch));
console.log(`     caracteres de la leyenda sin usar: ${sinUsar.length === 0 ? 'ninguno' : sinUsar.join(' ')}`);

// ===========================================================================
console.log('\n=== Tipografía ===');

comprobar('quita las tildes', normalizar('Participación') === 'PARTICIPACION');
comprobar('quita los signos de apertura', normalizar('¡Ya!') === 'YA!');
comprobar('pasa a mayúsculas', normalizar('dist') === 'DIST');

// Los rótulos del panel tienen que caber en sus recuadros de 72 px.
for (const rotulo of ['DIST', 'TEMP', 'TIME']) {
  comprobar(`el rótulo ${rotulo} cabe en el panel`, anchoTexto(rotulo) <= 72, `${anchoTexto(rotulo)} px`);
}

// El aviso más largo tiene que caber en los 320 px de pantalla.
const avisoLargo = TEMA_POR_DEFECTO.textos.avisoSobrecalentado;
comprobar(
  'el aviso de motor sobrecalentado cabe en pantalla',
  anchoTexto(avisoLargo) <= 320,
  `"${avisoLargo}" mide ${anchoTexto(avisoLargo)} px`
);

// El aviso de subida de velocidad sale centrado sobre la pista.
const avisoVel = TEMA_POR_DEFECTO.textos.avisoVelocidad;
comprobar(
  'el aviso de subida de velocidad cabe en pantalla',
  anchoTexto(avisoVel) <= 320,
  `"${avisoVel}" mide ${anchoTexto(avisoVel)} px`
);

// El dato más largo del panel: cinco cifras y la unidad.
comprobar(
  'la distancia máxima cabe en su recuadro',
  anchoTexto(`${DISTANCIA_MAXIMA_M}M`) <= 70,
  `${anchoTexto(`${DISTANCIA_MAXIMA_M}M`)} px`
);

// El cartel de la tribuna no puede ser más ancho que la pantalla.
const cartel = TEMA_POR_DEFECTO.textos.cartelTribuna;
comprobar('el cartel de la tribuna cabe', anchoTexto(cartel) + 14 <= 320, `${anchoTexto(cartel) + 14} px`);

// ===========================================================================
console.log('\n=== Tema ===');

const vacio = fusionarTema({});
comprobar('un tema vacío deja los valores por defecto', vacio.paleta.azul === TEMA_POR_DEFECTO.paleta.azul);

const conHex = fusionarTema({ paleta: { azul: '#123456' } });
comprobar('acepta colores en hexadecimal', conHex.paleta.azul === 0x123456);

const sinAlmohadilla = fusionarTema({ paleta: { rojo: 'AABBCC' } });
comprobar('acepta hexadecimal sin almohadilla', sinAlmohadilla.paleta.rojo === 0xaabbcc);

const basura = fusionarTema({ paleta: { azul: 'no soy un color' } });
comprobar('ignora lo que no entiende', basura.paleta.azul === TEMA_POR_DEFECTO.paleta.azul);

const textoNuevo = fusionarTema({ textos: { cartelTribuna: 'FELIZ NAVIDAD' } });
comprobar('cambia un texto', textoNuevo.textos.cartelTribuna === 'FELIZ NAVIDAD');
comprobar('y conserva los demás', textoNuevo.textos.rotuloDistancia === 'DIST');

const nulo = fusionarTema(null);
comprobar('sobrevive a un tema nulo', nulo.paleta.rojo === TEMA_POR_DEFECTO.paleta.rojo);

// ===========================================================================
console.log('\n=== theme.json ===');

/*
 * El tema se funde encima de los valores por defecto ignorando lo que no
 * entiende, que es lo correcto en producción: un archivo mal escrito no puede
 * dejar el juego sin colores. Pero en el repositorio esa tolerancia esconde
 * errores. Una clave que ya no existe —un color que se renombró, un texto que
 * se quitó— se ignora en silencio y el juego sale con el valor por defecto,
 * que casi nunca es lo que alguien quiso poner ahí.
 *
 * El original vive en game/public/; npm run build lo copia a assets/game/.
 * Editar la copia no sirve de nada, y eso también ya pasó.
 */
const rutaTema = new URL('../../public/theme.json', import.meta.url);
const temaArchivo = JSON.parse(readFileSync(rutaTema, 'utf8')) as Record<string, Record<string, unknown>>;

for (const [seccion, validas] of [
  ['paleta', Object.keys(TEMA_POR_DEFECTO.paleta)],
  ['textos', Object.keys(TEMA_POR_DEFECTO.textos)],
] as Array<[string, string[]]>) {
  // Las claves que empiezan por guion bajo son comentarios para quien lo edite.
  const claves = Object.keys(temaArchivo[seccion] ?? {}).filter((k) => !k.startsWith('_'));
  const sobran = claves.filter((k) => !validas.includes(k));
  const faltan = validas.filter((k) => !claves.includes(k));

  comprobar(`theme.json no trae claves desconocidas en ${seccion}`, sobran.length === 0, sobran.join(' '));
  comprobar(`theme.json cubre todo ${seccion}`, faltan.length === 0, faltan.join(' '));
}

console.log('');
console.log(fallos === 0 ? 'TODO OK' : `${fallos} FALLO(S)`);

if (fallos > 0) {
  process.exit(1);
}
