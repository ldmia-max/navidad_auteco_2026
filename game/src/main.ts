/**
 * Arranque del juego.
 *
 * El bundle expone una sola función global, window.navidadTvsIniciarJuego(),
 * que acceso.js llama cuando el participante pulsa "Iniciar carrera". Todo lo
 * demás queda encapsulado.
 */

import Phaser from 'phaser';
import { sonido } from './audio/sonido';
import { ALTO, ANCHO, Carrera, type ResultadoCarrera } from './escenas/Carrera';
import { Podio } from './escenas/Podio';
import { TOTAL_TICKS } from './sim/constantes';
import { cargarTema, type Tema } from './render/tema';

export interface SesionJuego {
  token: string;
  nombre: string;
  seed: number;
  contenedor?: string;
  /** URL de theme.json. Sin ella se usan los colores por defecto. */
  urlTema?: string;
  alTerminar?: (resultado: ResultadoCarrera) => void;
}

let juego: Phaser.Game | null = null;

/**
 * Tope del factor de escala.
 *
 * A ×6 el lienzo mide 1920×1080. Más allá, los píxeles se vuelven tan grandes
 * que la moto deja de leerse como una moto.
 */
const FACTOR_MAXIMO = 6;

/**
 * Rango del módulo con que se dimensiona el mando. Espejo del clamp de --m en
 * arcade.css.
 */
const MANDO_M_MIN = 11;
const MANDO_M_MAX = 16;

/** Aire entre el lienzo y el mando, en píxeles CSS. */
const HOLGURA = 12;

/**
 * Ajusta el tamaño del lienzo a un múltiplo entero del tamaño interno.
 *
 * Escalar por un factor fraccionario mezcla píxeles vecinos y el pixel art
 * pierde el filo: es la diferencia entre parecer de 16 bits y parecer una foto
 * borrosa de algo de 16 bits.
 *
 * El espacio disponible se mide contra la ventana, no contra el contenedor. El
 * contenedor de la página tiene un ancho máximo pensado para leer texto y
 * dejaba el juego clavado en ×2 en un monitor de 1920: había que forzar la
 * vista para distinguir al piloto.
 *
 * ---------------------------------------------------------------------------
 * EL REPARTO CON EL MANDO
 *
 * El mando vive debajo del lienzo y ocupa alto de verdad, así que hay que
 * descontarlo. Lo delicado es el orden en que se reparte.
 *
 * La primera versión medía el mando y le daba al lienzo lo que sobrara. Como
 * el lienzo solo crece de 200 en 200, un mando 77 px más alto le costó un
 * escalón entero: en un escritorio pasó de 600 a 400 px de lado. Setenta y
 * siete píxeles de mando se comieron doscientos de juego.
 *
 * Ahora es al revés, que es la prioridad correcta. Primero se calcula el mayor
 * múltiplo que entra suponiendo el mando en su tamaño MÍNIMO, y después el
 * mando se estira para ocupar lo que haya quedado libre. El mando sí puede
 * crecer de forma continua; el lienzo no.
 * ---------------------------------------------------------------------------
 */
function ajustarEscala(lienzo: HTMLCanvasElement, contenedor: HTMLElement): void {
  const mando = document.getElementById('ntvs-mando');
  const conMando = mando !== null && !mando.hidden;

  /*
   * El alto del mando se mide, no se calcula: depende de la hoja de estilos, y
   * repetir aquí sus proporciones sería otra copia que se desincroniza sola.
   * Se pone en el mínimo, se mide, y de ahí sale cuánto ocupa por cada píxel
   * de módulo.
   */
  let altoPorModulo = 0;
  let altoMinMando = 0;

  if (conMando && mando) {
    mando.style.setProperty('--m', `${MANDO_M_MIN}px`);
    altoPorModulo = mando.offsetHeight / MANDO_M_MIN;
    altoMinMando = mando.offsetHeight + HOLGURA;
  }

  /*
   * Margen pequeño a propósito. innerHeight ya descuenta la barra del
   * navegador, así que restarle mucho más deja los teléfonos pequeños en ×1
   * por unos pocos píxeles de diferencia.
   */
  const dispoAncho = Math.max(window.innerWidth - 8, ANCHO);
  const dispoAlto = Math.max(window.innerHeight - 8 - altoMinMando, ALTO);

  const factor = Math.min(
    FACTOR_MAXIMO,
    Math.max(1, Math.floor(Math.min(dispoAncho / ANCHO, dispoAlto / ALTO)))
  );

  lienzo.style.width = `${ANCHO * factor}px`;
  lienzo.style.height = `${ALTO * factor}px`;
  lienzo.style.imageRendering = 'pixelated';
  lienzo.style.display = 'block';
  lienzo.style.margin = '0 auto';
  lienzo.style.touchAction = 'none';

  // El panel que lo contiene tiene que dejarle sitio.
  contenedor.style.width = '100%';

  // Lo que sobró, para el mando.
  if (conMando && mando && altoPorModulo > 0) {
    const sobra = window.innerHeight - 8 - ALTO * factor - HOLGURA;

    const m = Math.min(
      MANDO_M_MAX,
      // El ancho también manda: en un teléfono estrecho el mando no puede
      // crecer aunque sobre alto. Es el mismo 3,4vw del clamp de la hoja.
      window.innerWidth * 0.034,
      Math.max(MANDO_M_MIN, sobra / altoPorModulo)
    );

    mando.style.setProperty('--m', `${Math.max(MANDO_M_MIN, m)}px`);
  }
}

/**
 * Crea el juego y arranca la carrera.
 *
 * @param sesion Datos que entrega el endpoint de inicio de carrera.
 */
export async function iniciarJuego(sesion: SesionJuego): Promise<void> {
  const idContenedor = sesion.contenedor ?? 'ntvs-game-root';
  const contenedor = document.getElementById(idContenedor);

  if (!contenedor) {
    console.error('[navidad-tvs] No se encontró el contenedor del juego:', idContenedor);
    return;
  }

  if (juego) {
    // Un solo intento: si ya hay una partida montada, no se monta otra.
    console.warn('[navidad-tvs] El juego ya estaba iniciado.');
    return;
  }

  /*
   * El audio se desbloquea aquí porque esta llamada viene del clic en "Iniciar
   * carrera". iOS no deja sonar nada fuera de un gesto del usuario, y este es
   * el último que hay antes de la carrera.
   */
  sonido.iniciar();

  const tema: Tema = await cargarTema(sesion.urlTema);

  contenedor.innerHTML = '';

  juego = new Phaser.Game({
    type: Phaser.AUTO,
    width: ANCHO,
    height: ALTO,
    parent: contenedor,
    backgroundColor: '#0b1730',
    pixelArt: true,
    roundPixels: true,
    scale: { mode: Phaser.Scale.NONE, autoCenter: Phaser.Scale.NO_CENTER },
    // El juego no usa el motor de física de Phaser: la nuestra es propia y en
    // enteros, porque el servidor tiene que poder repetirla exactamente.
    physics: undefined,
    scene: [Carrera, Podio],
    callbacks: {
      postBoot: (instancia) => {
        const lienzo = instancia.canvas;
        ajustarEscala(lienzo, contenedor);
        window.addEventListener('resize', () => ajustarEscala(lienzo, contenedor));
        window.addEventListener('orientationchange', () => ajustarEscala(lienzo, contenedor));
      },
    },
  });

  juego.scene.start('carrera', {
    seed: sesion.seed,
    token: sesion.token,
    nombre: sesion.nombre,
    tema,
    alTerminar: (resultado: ResultadoCarrera) => {
      // La pantalla del podio se muestra siempre; el envío del resultado al
      // servidor lo resuelve quien nos llamó (E6).
      juego?.scene.stop('carrera');
      juego?.scene.start('podio', {
        tema,
        nombre: resultado.nombre,
        distancia: resultado.distancia,
      });

      if (sesion.alTerminar) {
        sesion.alTerminar(resultado);
      } else {
        console.log('[navidad-tvs] Carrera terminada', resultado);
      }
    },
  });
}

declare global {
  interface Window {
    navidadTvsIniciarJuego?: (sesion: SesionJuego) => void;
    navidadTvsTicks?: number;
    navidadTvsSilenciar?: (valor: boolean) => void;
  }
}

window.navidadTvsIniciarJuego = (sesion: SesionJuego) => {
  void iniciarJuego(sesion);
};
window.navidadTvsTicks = TOTAL_TICKS;
window.navidadTvsSilenciar = (valor: boolean) => sonido.silenciar(valor);
