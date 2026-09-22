/**
 * Escena de la carrera.
 *
 * Solo dibuja y recoge lo que pulsa el jugador. Toda la física vive en
 * src/sim/, que no sabe que Phaser existe: esa separación es lo que permite
 * que el servidor reejecute la misma carrera en E6.
 *
 * El render nunca toca el estado de la simulación. Lee y pinta.
 */

import Phaser from 'phaser';
import {
  CARRILES,
  ITEM_METROS,
  TIPO_LODO,
  TIPO_RAMPA,
  TIPO_VALLA,
  TOTAL_TICKS,
  TPS,
  TEMP_MAX,
  div,
} from '../sim/constantes';
import { BIT_ABAJO, BIT_ACELERA, BIT_ARRIBA, BIT_TURBO, codificar, registroVacio } from '../sim/entradas';
import { generarPista, type Pista } from '../sim/pista';
import { crearEstado, distanciaMetros, paso, segundosRestantes, type Estado } from '../sim/simulacion';
import { PAL, css } from '../render/paleta';
import { TEX, crearTexturas } from '../render/texturas';

export const ANCHO = 320;
export const ALTO = 180;

/** Dónde empieza y termina la pista en vertical. */
const PISTA_Y = 96;
const PISTA_ALTO = 52;
const CARRIL_ALTO = PISTA_ALTO / CARRILES;

/** La moto se queda quieta en pantalla y el mundo se mueve. */
const MOTO_X = 72;

/** Píxeles por metro al dibujar. */
const PX_POR_METRO = 8;

/** Altura máxima de salto, en píxeles, para escalar la altura de la simulación. */
const PX_POR_MM_ALTURA = 0.012;

/**
 * Tope de ticks que se pueden recuperar en un solo frame.
 *
 * Si el navegador se congela (cambio de pestaña, notificación, garbage
 * collector), al volver habría que simular cientos de ticks de golpe y la
 * pantalla daría un salto imposible de jugar. Con el tope, la carrera sigue
 * durando exactamente 5400 ticks: lo que se estira es el reloj de pared, no la
 * carrera. Todos simulan lo mismo, que es lo que exige el concurso.
 */
const MAX_TICKS_POR_FRAME = 8;

export interface DatosCarrera {
  seed: number;
  token: string;
  nombre: string;
  alTerminar: (resultado: ResultadoCarrera) => void;
}

export interface ResultadoCarrera {
  token: string;
  nombre: string;
  distancia: number;
  items: number;
  caidas: number;
  calones: number;
  entradas: string;
  ticks: number;
}

export class Carrera extends Phaser.Scene {
  private datos!: DatosCarrera;
  private pista!: Pista;
  private estado!: Estado;
  private registro!: Uint8Array;

  private acumulador = 0;
  private corriendo = false;
  private terminada = false;

  // Fondo. Solo se guardan las capas que se mueven con el parallax; el cielo y
  // el cartel se pintan una vez y no se vuelven a tocar.
  private cerros!: Phaser.GameObjects.TileSprite;
  private tribuna!: Phaser.GameObjects.TileSprite;
  private suelo!: Phaser.GameObjects.TileSprite;

  // Actores
  private moto!: Phaser.GameObjects.Image;
  private sombra!: Phaser.GameObjects.Image;
  private obstaculos: Phaser.GameObjects.Image[] = [];
  private items: Phaser.GameObjects.Image[] = [];

  // Panel inferior
  private textoDist!: Phaser.GameObjects.Text;
  private textoTiempo!: Phaser.GameObjects.Text;
  private barraTemp!: Phaser.GameObjects.Rectangle;
  private avisoCalado!: Phaser.GameObjects.Text;

  // Cuenta regresiva
  private textoCuenta!: Phaser.GameObjects.Text;

  // Controles
  private teclas!: {
    acelera: Phaser.Input.Keyboard.Key;
    turbo: Phaser.Input.Keyboard.Key;
    arriba: Phaser.Input.Keyboard.Key;
    abajo: Phaser.Input.Keyboard.Key;
  };

  constructor() {
    super('carrera');
  }

  init(datos: DatosCarrera): void {
    this.datos = datos;
    this.pista = generarPista(datos.seed);
    this.estado = crearEstado();
    this.registro = registroVacio();
    this.acumulador = 0;
    this.corriendo = false;
    this.terminada = false;
    this.obstaculos = [];
    this.items = [];
  }

  create(): void {
    crearTexturas(this);

    this.construirFondo();
    this.construirActores();
    this.construirPanel();
    this.construirControles();
    this.cuentaRegresiva();
  }

  // -------------------------------------------------------------------------
  // Construcción
  // -------------------------------------------------------------------------

  private construirFondo(): void {
    this.add.rectangle(0, 0, ANCHO, PISTA_Y, PAL.cieloAlto).setOrigin(0, 0);

    this.tribuna = this.add.tileSprite(0, 8, ANCHO, 34, TEX.tribuna).setOrigin(0, 0);

    // El cartel de la tribuna: donde el original decía NINTENDO.
    this.add
      .text(ANCHO / 2, 14, 'CONCURSO TVS', {
        fontFamily: 'monospace',
        fontSize: '10px',
        color: css(PAL.blanco),
        backgroundColor: css(PAL.azul),
        padding: { x: 4, y: 2 },
      })
      .setOrigin(0.5, 0);

    this.cerros = this.add.tileSprite(0, 48, ANCHO, 24, TEX.cerros).setOrigin(0, 0);

    this.add.rectangle(0, 72, ANCHO, PISTA_Y - 72, PAL.verde).setOrigin(0, 0);

    this.suelo = this.add.tileSprite(0, PISTA_Y, ANCHO, PISTA_ALTO, TEX.pista).setOrigin(0, 0);

    this.add.rectangle(0, PISTA_Y - 2, ANCHO, 2, PAL.pistaBorde).setOrigin(0, 0);
    this.add.rectangle(0, PISTA_Y + PISTA_ALTO, ANCHO, 2, PAL.pistaBorde).setOrigin(0, 0);
  }

  private construirActores(): void {
    // Se crean pocos objetos y se reciclan: en pantalla nunca caben muchos.
    for (let i = 0; i < 12; i++) {
      const img = this.add.image(-100, 0, TEX.valla).setOrigin(0.5, 1).setVisible(false);
      this.obstaculos.push(img);
    }
    for (let i = 0; i < 6; i++) {
      const img = this.add.image(-100, 0, TEX.item).setOrigin(0.5, 0.5).setVisible(false);
      this.items.push(img);
    }

    this.sombra = this.add.image(MOTO_X, 0, TEX.sombra).setOrigin(0.5, 0.5).setVisible(false);
    this.moto = this.add.image(MOTO_X, 0, TEX.moto).setOrigin(0.5, 1);
  }

  private construirPanel(): void {
    const y = PISTA_Y + PISTA_ALTO + 2;
    const alto = ALTO - y;

    this.add.rectangle(0, y, ANCHO, alto, PAL.negro).setOrigin(0, 0);

    const estiloRotulo = { fontFamily: 'monospace', fontSize: '8px', color: css(PAL.rojo) };
    const estiloDato = { fontFamily: 'monospace', fontSize: '10px', color: css(PAL.blanco) };

    // Izquierda: distancia. Centro: temperatura. Derecha: tiempo.
    this.add.text(24, y + 3, 'DIST', estiloRotulo).setOrigin(0.5, 0);
    this.textoDist = this.add.text(38, y + 13, '0 m', estiloDato).setOrigin(0.5, 0);
    this.marco(6, y + 11, 64, 14);

    this.add.text(ANCHO / 2, y + 3, 'TEMP', estiloRotulo).setOrigin(0.5, 0);
    this.marco(ANCHO / 2 - 32, y + 12, 64, 12);
    this.add.rectangle(ANCHO / 2 - 30, y + 14, 60, 8, PAL.tempFria).setOrigin(0, 0);
    this.barraTemp = this.add.rectangle(ANCHO / 2 - 30, y + 14, 0, 8, PAL.tempCaliente).setOrigin(0, 0);

    this.add.text(ANCHO - 40, y + 3, 'TIME', estiloRotulo).setOrigin(0.5, 0);
    this.textoTiempo = this.add.text(ANCHO - 38, y + 13, '1:30', estiloDato).setOrigin(0.5, 0);
    this.marco(ANCHO - 70, y + 11, 64, 14);

    this.avisoCalado = this.add
      .text(ANCHO / 2, PISTA_Y + 6, '¡MOTOR CALIENTE!', {
        fontFamily: 'monospace',
        fontSize: '10px',
        color: css(PAL.blanco),
        backgroundColor: css(PAL.rojo),
        padding: { x: 3, y: 1 },
      })
      .setOrigin(0.5, 0)
      .setVisible(false);
  }

  /** Marco azul de los recuadros del panel, como en la referencia. */
  private marco(x: number, y: number, w: number, h: number): void {
    const g = this.add.graphics();
    g.lineStyle(1, PAL.cielo, 1);
    g.strokeRect(x + 0.5, y + 0.5, w, h);
  }

  private construirControles(): void {
    const teclado = this.input.keyboard;

    if (teclado) {
      this.teclas = {
        acelera: teclado.addKey(Phaser.Input.Keyboard.KeyCodes.Z),
        turbo: teclado.addKey(Phaser.Input.Keyboard.KeyCodes.X),
        arriba: teclado.addKey(Phaser.Input.Keyboard.KeyCodes.UP),
        abajo: teclado.addKey(Phaser.Input.Keyboard.KeyCodes.DOWN),
      };
    }

    // Acelerar y cambiar de carril a la vez exige más de un dedo.
    this.input.addPointer(3);
  }

  private cuentaRegresiva(): void {
    this.textoCuenta = this.add
      .text(ANCHO / 2, 56, '3', {
        fontFamily: 'monospace',
        fontSize: '32px',
        color: css(PAL.blanco),
        stroke: css(PAL.rojo),
        strokeThickness: 3,
      })
      .setOrigin(0.5, 0.5);

    let n = 3;

    this.time.addEvent({
      delay: 1000,
      repeat: 3,
      callback: () => {
        n--;
        if (n > 0) {
          this.textoCuenta.setText(String(n));
        } else if (n === 0) {
          this.textoCuenta.setText('¡YA!');
          // El cronómetro arranca aquí, no antes: perder segundos por no estar
          // listo sería motivo de reclamo, y solo hay un intento.
          this.corriendo = true;
          this.acumulador = 0;
        } else {
          this.textoCuenta.setVisible(false);
        }
      },
    });
  }

  // -------------------------------------------------------------------------
  // Bucle
  // -------------------------------------------------------------------------

  override update(_tiempo: number, delta: number): void {
    if (this.corriendo && !this.terminada) {
      this.acumulador += delta;

      const msPorTick = 1000 / TPS;
      let ticksEsteFrame = 0;

      while (this.acumulador >= msPorTick && ticksEsteFrame < MAX_TICKS_POR_FRAME) {
        const entrada = this.leerEntrada();

        if (this.estado.tick < TOTAL_TICKS) {
          this.registro[this.estado.tick] = entrada;
        }

        paso(this.estado, entrada, this.pista);

        this.acumulador -= msPorTick;
        ticksEsteFrame++;

        if (this.estado.tick >= TOTAL_TICKS) {
          this.terminar();
          break;
        }
      }

      // Lo que no se pudo recuperar se descarta: acumularlo haría que el juego
      // corriera acelerado después de cada congelón.
      if (ticksEsteFrame >= MAX_TICKS_POR_FRAME) {
        this.acumulador = 0;
      }
    }

    this.pintar();
  }

  /** Lee el estado actual de teclado y pantalla táctil. */
  private leerEntrada(): number {
    let entrada = 0;

    if (this.teclas) {
      if (this.teclas.acelera.isDown) entrada |= BIT_ACELERA;
      if (this.teclas.turbo.isDown) entrada |= BIT_TURBO;
      if (this.teclas.arriba.isDown) entrada |= BIT_ARRIBA;
      if (this.teclas.abajo.isDown) entrada |= BIT_ABAJO;
    }

    /*
     * Zonas táctiles:
     *
     *   izquierda arriba  -> subir de carril / enderezar en el aire
     *   izquierda abajo   -> bajar de carril / inclinar en el aire
     *   derecha arriba    -> turbo
     *   derecha abajo     -> acelerador
     *
     * Se recorren todos los punteros para que acelerar y moverse a la vez
     * funcione con dos dedos.
     */
    for (const puntero of this.input.manager.pointers) {
      if (!puntero.isDown) {
        continue;
      }

      const x = puntero.worldX;
      const y = puntero.worldY;

      if (x < ANCHO / 2) {
        entrada |= y < ALTO / 2 ? BIT_ARRIBA : BIT_ABAJO;
      } else {
        entrada |= y < ALTO / 2 ? BIT_TURBO : BIT_ACELERA;
      }
    }

    return entrada;
  }

  // -------------------------------------------------------------------------
  // Pintado
  // -------------------------------------------------------------------------

  private pintar(): void {
    const e = this.estado;
    const metros = div(e.pos, 1000);
    const scroll = metros * PX_POR_METRO + div((e.pos % 1000) * PX_POR_METRO, 1000);

    // Parallax: cuanto más lejos, más despacio.
    this.cerros.tilePositionX = scroll * 0.15;
    this.tribuna.tilePositionX = scroll * 0.35;
    this.suelo.tilePositionX = scroll;

    // --- Moto ---------------------------------------------------------------
    const yCarril = PISTA_Y + e.carril * CARRIL_ALTO + CARRIL_ALTO;
    const alturaPx = e.altura * PX_POR_MM_ALTURA;

    this.moto.setTexture(e.caido > 0 ? TEX.motoCaida : TEX.moto);
    this.moto.setPosition(MOTO_X, yCarril - alturaPx);
    this.moto.setAngle(e.enAire ? -e.inclinacion / 10 : 0);

    this.sombra.setVisible(e.enAire);
    if (e.enAire) {
      this.sombra.setPosition(MOTO_X, yCarril - 1);
    }

    // Parpadeo mientras se está caído, para que se note por qué no avanza.
    this.moto.setAlpha(e.caido > 0 && Math.floor(e.caido / 6) % 2 === 0 ? 0.4 : 1);

    this.pintarPista(scroll);
    this.pintarPanel();
  }

  /** Coloca obstáculos y logos visibles reciclando los objetos creados. */
  private pintarPista(scroll: number): void {
    const e = this.estado;
    const desdeMm = e.pos - div(MOTO_X * 1000, PX_POR_METRO);
    const hastaMm = e.pos + div((ANCHO - MOTO_X) * 1000, PX_POR_METRO);

    let usados = 0;

    for (const obs of this.pista.obstaculos) {
      if (obs.pos < desdeMm) continue;
      if (obs.pos > hastaMm) break;
      if (usados >= this.obstaculos.length) break;

      const img = this.obstaculos[usados++];
      const x = MOTO_X + div((obs.pos - e.pos) * PX_POR_METRO, 1000);
      const y = PISTA_Y + obs.carril * CARRIL_ALTO + CARRIL_ALTO;

      img.setTexture(obs.tipo === TIPO_RAMPA ? TEX.rampa : obs.tipo === TIPO_LODO ? TEX.lodo : TEX.valla);
      img.setPosition(x, obs.tipo === TIPO_LODO ? y - 1 : y);
      img.setOrigin(0.5, obs.tipo === TIPO_LODO ? 0.5 : 1);
      img.setVisible(true);
    }

    for (let i = usados; i < this.obstaculos.length; i++) {
      this.obstaculos[i].setVisible(false);
    }

    let usadosItem = 0;

    for (let i = e.idxItem; i < this.pista.items.length; i++) {
      const item = this.pista.items[i];
      if (item.pos > hastaMm) break;
      if (usadosItem >= this.items.length) break;

      const img = this.items[usadosItem++];
      img.setPosition(
        MOTO_X + div((item.pos - e.pos) * PX_POR_METRO, 1000),
        PISTA_Y + item.carril * CARRIL_ALTO + CARRIL_ALTO / 2
      );
      img.setVisible(true);
    }

    for (let i = usadosItem; i < this.items.length; i++) {
      this.items[i].setVisible(false);
    }

    void scroll;
  }

  private pintarPanel(): void {
    const e = this.estado;

    this.textoDist.setText(`${distanciaMetros(e)} m`);

    const seg = segundosRestantes(e, TOTAL_TICKS);
    this.textoTiempo.setText(`${div(seg, 60)}:${String(seg % 60).padStart(2, '0')}`);

    // La barra roja crece de izquierda a derecha sobre la verde.
    this.barraTemp.width = Math.round((e.temp / TEMP_MAX) * 60);

    const caliente = e.calado > 0 || e.temp > TEMP_MAX * 0.8;
    this.avisoCalado.setVisible(caliente);
    this.avisoCalado.setText(e.calado > 0 ? 'MOTOR CALADO' : '¡MOTOR CALIENTE!');
  }

  // -------------------------------------------------------------------------
  // Final
  // -------------------------------------------------------------------------

  private terminar(): void {
    if (this.terminada) {
      return;
    }

    this.terminada = true;
    this.corriendo = false;

    const e = this.estado;

    this.datos.alTerminar({
      token: this.datos.token,
      nombre: this.datos.nombre,
      distancia: distanciaMetros(e),
      items: e.items,
      caidas: e.caidas,
      calones: e.calones,
      entradas: codificar(this.registro),
      ticks: e.tick,
    });
  }
}

export { ITEM_METROS, TIPO_VALLA };
