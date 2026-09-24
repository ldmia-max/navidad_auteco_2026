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
 * Ajusta el tamaño del lienzo a un múltiplo entero del tamaño interno.
 *
 * Escalar por un factor fraccionario mezcla píxeles vecinos y el pixel art
 * pierde el filo: es la diferencia entre parecer de 16 bits y parecer una foto
 * borrosa de algo de 16 bits.
 *
 * El espacio disponible se mide contra la ventana, no contra el contenedor. El
 * contenedor de la página tiene un ancho máximo pensado para leer texto y
 * dejaba el juego clavado en ×2, o sea 640×360 en un monitor de 1920: había
 * que forzar la vista para distinguir al piloto.
 */
function ajustarEscala(lienzo: HTMLCanvasElement, contenedor: HTMLElement): void {
  /*
   * El mando vive debajo del lienzo y ocupa alto de verdad. Si no se descuenta,
   * en un teléfono el juego se escala hasta llenar la pantalla y los botones
   * quedan fuera de la vista, que es justo lo contrario de lo que se buscaba
   * al sacarlos del lienzo.
   *
   * Se mide en vez de reservar un número fijo: los botones cambian de tamaño
   * con el ancho de la pantalla.
   */
  const mando = document.getElementById('ntvs-mando');
  const altoMando = mando && !mando.hidden ? mando.offsetHeight + 24 : 0;

  /*
   * Margen pequeño a propósito. innerHeight ya descuenta la barra del
   * navegador, así que restarle mucho más deja los teléfonos pequeños en ×1
   * por unos pocos píxeles de diferencia.
   */
  const dispoAncho = Math.max(window.innerWidth - 8, ANCHO);
  const dispoAlto = Math.max(window.innerHeight - 8 - altoMando, ALTO);

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
