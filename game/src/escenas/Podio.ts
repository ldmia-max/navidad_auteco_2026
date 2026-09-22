/**
 * Pantalla final: el piloto en el podio.
 *
 * Sigue la referencia del original (imagenes_apoyo/Ejemplo_final_carrera.png):
 * copas y estrellas arriba, el piloto sobre el podio, y debajo un panel con
 * marco de cuadros de meta donde van el nombre y la distancia.
 */

import Phaser from 'phaser';
import { sonido } from '../audio/sonido';
import { TextoPixel, anchoTexto, pintarTexto } from '../render/fuente';
import type { Tema } from '../render/tema';
import { TEX } from '../render/texturas';

export const ANCHO = 320;
export const ALTO = 180;

export interface DatosPodio {
  tema: Tema;
  nombre: string;
  distancia: number;
}

export class Podio extends Phaser.Scene {
  private tema!: Tema;
  private datos!: DatosPodio;

  constructor() {
    super('podio');
  }

  init(datos: DatosPodio): void {
    this.datos = datos;
    this.tema = datos.tema;
  }

  create(): void {
    const p = this.tema.paleta;
    const t = this.tema.textos;

    this.add.rectangle(0, 0, ANCHO, ALTO, p.azul).setOrigin(0, 0);

    this.encabezado();
    this.escenaPodio();
    this.panelResultado();

    // El mensaje de agradecimiento cierra la pantalla.
    const gracias = new TextoPixel(this, ANCHO / 2, ALTO - 14, p.crema, 'centro');
    gracias.set(t.podioGracias);

    sonido.fanfarriaPodio();
    this.animar();
  }

  /** Copas, estrellas y el título, como en la referencia. */
  private encabezado(): void {
    const p = this.tema.paleta;
    const t = this.tema.textos;

    const titulo = new TextoPixel(this, ANCHO / 2, 12, p.verdeClaro, 'centro');
    titulo.set(t.podioTitulo);

    const anchoTitulo = anchoTexto(t.podioTitulo);
    const margen = ANCHO / 2 - anchoTitulo / 2;

    this.add.image(margen - 18, 15, TEX.copa).setOrigin(0.5, 0.5);
    this.add.image(ANCHO - margen + 18, 15, TEX.copa).setOrigin(0.5, 0.5);

    this.add.image(margen - 40, 13, TEX.estrella).setOrigin(0.5, 0.5).setScale(0.75);
    this.add.image(margen - 30, 18, TEX.estrella).setOrigin(0.5, 0.5).setScale(0.5);
    this.add.image(ANCHO - margen + 40, 13, TEX.estrella).setOrigin(0.5, 0.5).setScale(0.75);
    this.add.image(ANCHO - margen + 30, 18, TEX.estrella).setOrigin(0.5, 0.5).setScale(0.5);
  }

  /** El podio de tres escalones con el piloto arriba. */
  private escenaPodio(): void {
    const p = this.tema.paleta;
    const cx = ANCHO / 2;
    const base = 82;

    // Escalones: el primero al centro y más alto.
    this.add.image(cx, base, TEX.podioBloque).setOrigin(0.5, 1).setScale(1, 1);
    this.add.image(cx - 26, base, TEX.podioBloque).setOrigin(0.5, 1).setScale(1, 0.7);
    this.add.image(cx + 26, base, TEX.podioBloque).setOrigin(0.5, 1).setScale(1, 0.55);

    // Números de los puestos.
    const g = this.add.graphics();
    pintarTexto(g, '1', cx - 2, base - 26, p.blanco);
    pintarTexto(g, '2', cx - 28, base - 18, p.blanco);
    pintarTexto(g, '3', cx + 24, base - 14, p.blanco);

    this.add.image(cx, base - 30, TEX.pilotoPodio).setOrigin(0.5, 1).setName('piloto');
  }

  /** Panel con marco de cuadros: nombre y distancia. */
  private panelResultado(): void {
    const p = this.tema.paleta;
    const x = 26;
    const y = 92;
    const ancho = ANCHO - x * 2;
    const alto = 62;

    // Marco de cuadros de meta alrededor del panel.
    this.add.tileSprite(x, y, ancho, 8, TEX.marcoCuadros).setOrigin(0, 0);
    this.add.tileSprite(x, y + alto - 8, ancho, 8, TEX.marcoCuadros).setOrigin(0, 0);
    this.add.tileSprite(x, y, 8, alto, TEX.marcoCuadros).setOrigin(0, 0);
    this.add.tileSprite(x + ancho - 8, y, 8, alto, TEX.marcoCuadros).setOrigin(0, 0);

    this.add.rectangle(x + 8, y + 8, ancho - 16, alto - 16, p.azul).setOrigin(0, 0);

    const cx = ANCHO / 2;

    const nombre = new TextoPixel(this, cx, y + 18, p.crema, 'centro');
    nombre.set(this.datos.nombre);

    const distancia = new TextoPixel(this, cx, y + 34, p.blanco, 'centro');
    distancia.set(`${this.datos.distancia} ${this.tema.textos.unidadMetros}`);
  }

  /** El piloto da un saltito de celebración. */
  private animar(): void {
    const piloto = this.children.getByName('piloto') as Phaser.GameObjects.Image | null;

    if (!piloto) {
      return;
    }

    this.tweens.add({
      targets: piloto,
      y: piloto.y - 4,
      duration: 380,
      yoyo: true,
      repeat: -1,
      ease: 'Sine.easeInOut',
    });
  }
}
