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

import { anchoTexto, normalizar } from './fuente';
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
  ['MOTO_WHEELIE', S.MOTO_WHEELIE],
  ['MOTO_CAIDA', S.MOTO_CAIDA],
  ['HUMO', S.HUMO],
  ['SOMBRA', S.SOMBRA],
  ['RAMPA', S.RAMPA],
  ['LODO', S.LODO],
  ['VALLA', S.VALLA],
  ['ITEM_TVS', S.ITEM_TVS],
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
 * La moto y sus variantes tienen que medir lo mismo: si no, al cambiar de
 * textura la moto daría un salto en pantalla.
 */
const medidas = (s: readonly string[]) => `${s[0].length}×${s.length}`;

comprobar('la moto y el wheelie miden igual', medidas(S.MOTO) === medidas(S.MOTO_WHEELIE), medidas(S.MOTO));
comprobar('la moto y la caída miden igual', medidas(S.MOTO) === medidas(S.MOTO_CAIDA), medidas(S.MOTO_CAIDA));

// El bonus no puede sobresalir del carril, o se confundiria con el de al lado.
comprobar(
  'el bonus TVS cabe en un carril',
  S.ITEM_TVS.length <= CARRIL_ALTO,
  `${S.ITEM_TVS.length} px de alto, carril de ${CARRIL_ALTO}`
);
comprobar(
  'la valla no tapa dos carriles',
  S.VALLA.length <= CARRIL_ALTO,
  `${S.VALLA.length} px de alto`
);

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

// El dato más largo del panel: cinco cifras y la unidad.
comprobar('la distancia máxima cabe en su recuadro', anchoTexto('3100M') <= 70, `${anchoTexto('3100M')} px`);

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

console.log('');
console.log(fallos === 0 ? 'TODO OK' : `${fallos} FALLO(S)`);

if (fallos > 0) {
  process.exit(1);
}
