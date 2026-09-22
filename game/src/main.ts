/**
 * Arranque del juego.
 *
 * El bundle expone una sola función global, window.navidadTvsIniciarJuego(),
 * que acceso.js llama cuando el participante pulsa "Iniciar carrera". Todo lo
 * demás queda encapsulado.
 */

import Phaser from 'phaser';
import { ALTO, ANCHO, Carrera, type ResultadoCarrera } from './escenas/Carrera';
import { TOTAL_TICKS } from './sim/constantes';

export interface SesionJuego {
  token: string;
  nombre: string;
  seed: number;
  contenedor?: string;
  alTerminar?: (resultado: ResultadoCarrera) => void;
}

let juego: Phaser.Game | null = null;

/**
 * Ajusta el tamaño del lienzo a un múltiplo entero del tamaño interno.
 *
 * Escalar por un factor fraccionario mezcla píxeles vecinos y el pixel art
 * pierde el filo: es la diferencia entre parecer de 16 bits y parecer una foto
 * borrosa de algo de 16 bits.
 */
function ajustarEscala(lienzo: HTMLCanvasElement, contenedor: HTMLElement): void {
  const dispoAncho = contenedor.clientWidth || ANCHO;
  const dispoAlto = Math.max(window.innerHeight - 40, 180);

  const factor = Math.max(1, Math.floor(Math.min(dispoAncho / ANCHO, dispoAlto / ALTO)));

  lienzo.style.width = `${ANCHO * factor}px`;
  lienzo.style.height = `${ALTO * factor}px`;
  lienzo.style.imageRendering = 'pixelated';
  lienzo.style.display = 'block';
  lienzo.style.margin = '0 auto';
  lienzo.style.touchAction = 'none';
}

/**
 * Crea el juego y arranca la carrera.
 *
 * @param sesion Datos que entrega el endpoint de inicio de carrera.
 */
export function iniciarJuego(sesion: SesionJuego): void {
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
    scene: [Carrera],
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
    alTerminar: (resultado: ResultadoCarrera) => {
      if (sesion.alTerminar) {
        sesion.alTerminar(resultado);
      } else {
        // Mientras E6 no exista, el resultado se deja a la vista para poder
        // revisarlo desde la consola del navegador.
        console.log('[navidad-tvs] Carrera terminada', resultado);
      }
    },
  });
}

declare global {
  interface Window {
    navidadTvsIniciarJuego?: (sesion: SesionJuego) => void;
    navidadTvsTicks?: number;
  }
}

window.navidadTvsIniciarJuego = iniciarJuego;
window.navidadTvsTicks = TOTAL_TICKS;
