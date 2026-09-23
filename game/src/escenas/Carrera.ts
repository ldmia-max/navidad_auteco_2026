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
import { sonido } from '../audio/sonido';
import {
  TEMP_MAX,
  TIPO_LODO,
  TIPO_RAMPA,
  TOTAL_TICKS,
  TPS,
  V_MAX_TURBO,
  div,
} from '../sim/constantes';
import { BIT_ABAJO, BIT_ACELERA, BIT_ARRIBA, BIT_TURBO, codificar, registroVacio } from '../sim/entradas';
import { generarPista, type Pista } from '../sim/pista';
import { crearEstado, distanciaMetros, paso, segundosRestantes, type Estado } from '../sim/simulacion';
import { TextoPixel } from '../render/fuente';
import type { Tema } from '../render/tema';
import { ALTO, ANCHO, CARRIL_ALTO, PANEL_ALTO, PANEL_Y, PISTA_ALTO, PISTA_Y } from '../render/medidas';
import { TEX, crearPanel, crearTexturas } from '../render/texturas';

export { ALTO, ANCHO };

/** La moto se queda quieta en pantalla y el mundo se mueve. */
const MOTO_X = 74;

/** Píxeles por metro al dibujar. */
const PX_POR_METRO = 8;

/** Cuántos píxeles de altura equivale un milímetro de salto. */
const PX_POR_MM_ALTURA = 0.012;

/**
 * Cuadros que tarda la moto en deslizarse de un carril al otro.
 *
 * Es SOLO visual. La simulación cambia de carril en un tick y así tiene que
 * seguir: si el cambio tardara en surtir efecto, alteraría las colisiones y
 * habría que replicar la interpolación exacta en el puerto PHP de E6. La moto
 * se desliza en pantalla mientras la física ya la considera en el carril
 * nuevo, que es justo lo que hacía el original.
 */
const CUADROS_CAMBIO_CARRIL = 7;

/**
 * Tope de ticks que se pueden recuperar en un solo frame.
 *
 * Si el navegador se congela (cambio de pestaña, notificación, recolector de
 * basura), al volver habría que simular cientos de ticks de golpe y la pantalla
 * daría un salto imposible de jugar. Con el tope, la carrera sigue durando
 * exactamente 5400 ticks: lo que se estira es el reloj de pared, no la carrera.
 */
const MAX_TICKS_POR_FRAME = 8;

/** Cuánto dura en pantalla el "+50" de un bonus. */
const TICKS_FLOTANTE = 45;

export interface DatosCarrera {
  seed: number;
  token: string;
  nombre: string;
  tema: Tema;
  alTerminar: (resultado: ResultadoCarrera) => void;
}

export interface ResultadoCarrera {
  token: string;
  nombre: string;
  distancia: number;
  items: number;
  caidas: number;
  sobrecalentamientos: number;
  entradas: string;
  ticks: number;
}

export class Carrera extends Phaser.Scene {
  private datos!: DatosCarrera;
  private tema!: Tema;
  private pista!: Pista;
  private estado!: Estado;
  private registro!: Uint8Array;

  private acumulador = 0;
  private corriendo = false;
  private terminada = false;

  // Capas del fondo que se mueven con el parallax.
  private nubes!: Phaser.GameObjects.TileSprite;
  private cerros!: Phaser.GameObjects.TileSprite;
  private tribuna!: Phaser.GameObjects.TileSprite;
  private cesped!: Phaser.GameObjects.TileSprite;
  private suelo!: Phaser.GameObjects.TileSprite;
  private cartel!: Phaser.GameObjects.Image;

  // Actores
  private moto!: Phaser.GameObjects.Image;
  private sombra!: Phaser.GameObjects.Image;
  private humo: Phaser.GameObjects.Image[] = [];
  private obstaculos: Phaser.GameObjects.Image[] = [];
  private items: Phaser.GameObjects.Image[] = [];

  // Panel
  private textoDist!: TextoPixel;
  private textoTiempo!: TextoPixel;
  private barraTemp!: Phaser.GameObjects.Rectangle;
  private avisoMotor!: TextoPixel;
  private avisoFondo!: Phaser.GameObjects.Rectangle;
  private textoCuenta!: TextoPixel;

  /** Rótulos "+50" que suben flotando al recoger un bonus. */
  private flotantes: Array<{ texto: TextoPixel; ticks: number; x: number; y: number }> = [];

  // Interpolación visual del cambio de carril.
  private carrilDibujado = 1;
  private carrilAnterior = 1;
  private cuadrosCambio = 0;

  // Para disparar sonidos cuando algo cambia entre un tick y el siguiente.
  private itemsPrevios = 0;
  private caidasPrevias = 0;
  private sobrecalentamientosPrevios = 0;
  private enAirePrevio = false;

  private teclas?: {
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
    this.tema = datos.tema;
    this.pista = generarPista(datos.seed);
    this.estado = crearEstado();
    this.registro = registroVacio();

    this.acumulador = 0;
    this.corriendo = false;
    this.terminada = false;
    this.obstaculos = [];
    this.items = [];
    this.humo = [];
    this.flotantes = [];

    this.carrilDibujado = this.estado.carril;
    this.carrilAnterior = this.estado.carril;
    this.cuadrosCambio = 0;

    this.itemsPrevios = 0;
    this.caidasPrevias = 0;
    this.sobrecalentamientosPrevios = 0;
    this.enAirePrevio = false;
  }

  create(): void {
    crearTexturas(this, this.tema);
    crearPanel(this, this.tema, ANCHO, PANEL_ALTO);

    this.construirFondo();
    this.construirActores();
    this.construirPanel();
    this.construirControles();

    sonido.arrancarMotor();
    this.cuentaRegresiva();
  }

  // -------------------------------------------------------------------------
  // Construcción
  // -------------------------------------------------------------------------

  private construirFondo(): void {
    this.add.image(0, 0, TEX.cielo).setOrigin(0, 0);

    /*
     * Las bandas no se pisan: los pinos ocupan la parte baja de los cerros y
     * la tribuna empieza justo debajo. En la primera versión la tribuna
     * arrancaba dentro de los cerros y se comía los pinos enteros.
     */
    this.nubes = this.add.tileSprite(0, 0, ANCHO, 18, TEX.nubes).setOrigin(0, 0);
    this.cerros = this.add.tileSprite(0, 18, ANCHO, 28, TEX.cerros).setOrigin(0, 0);
    this.tribuna = this.add.tileSprite(0, 46, ANCHO, 28, TEX.tribuna).setOrigin(0, 0);

    this.cartel = this.add.image(ANCHO / 2, 48, TEX.cartel).setOrigin(0.5, 0);

    this.cesped = this.add.tileSprite(0, 74, ANCHO, 8, TEX.cesped).setOrigin(0, 0);
    this.suelo = this.add.tileSprite(0, PISTA_Y, ANCHO, PISTA_ALTO, TEX.pista).setOrigin(0, 0);
  }

  private construirActores(): void {
    // Se crean pocos objetos y se reciclan: en pantalla nunca caben muchos.
    for (let i = 0; i < 14; i++) {
      this.obstaculos.push(this.add.image(-100, 0, TEX.valla).setOrigin(0.5, 1).setVisible(false));
    }
    for (let i = 0; i < 6; i++) {
      this.items.push(this.add.image(-100, 0, TEX.item).setOrigin(0.5, 0.5).setVisible(false));
    }

    this.sombra = this.add.image(MOTO_X, 0, TEX.sombra).setOrigin(0.5, 0.5).setVisible(false);
    this.moto = this.add.image(MOTO_X, 0, TEX.moto).setOrigin(0.5, 1);

    for (let i = 0; i < 4; i++) {
      this.humo.push(this.add.image(-100, 0, TEX.humo).setOrigin(0.5, 0.5).setVisible(false));
    }
  }

  private construirPanel(): void {
    this.add.image(0, PANEL_Y, TEX.panel).setOrigin(0, 0);

    const p = this.tema.paleta;
    const cx = Math.floor(ANCHO / 2);

    this.textoDist = new TextoPixel(this, 40, PANEL_Y + 16, p.blanco, 'centro');
    this.textoTiempo = new TextoPixel(this, ANCHO - 40, PANEL_Y + 16, p.blanco, 'centro');

    // Barra de temperatura: verde de fondo, rojo que crece encima.
    this.add.rectangle(cx - 31, PANEL_Y + 14, 62, 8, p.tempFria).setOrigin(0, 0);
    this.barraTemp = this.add.rectangle(cx - 31, PANEL_Y + 14, 0, 8, p.tempCaliente).setOrigin(0, 0);

    /*
     * Aviso del motor: letras rojas sobre una caja negra.
     * El rojo solo no se leería sobre el verde del césped ni sobre la pista.
     */
    this.avisoFondo = this.add.rectangle(cx, PISTA_Y - 13, 10, 11, p.negro).setOrigin(0.5, 0);
    this.avisoFondo.setDepth(9);
    this.avisoFondo.setVisible(false);

    this.avisoMotor = new TextoPixel(this, cx, PISTA_Y - 11, p.rojo, 'centro');
    this.avisoMotor.setVisible(false);
    this.avisoMotor.setDepth(10);

    // Rótulos flotantes del bonus. Se reciclan; nunca coinciden muchos a la vez.
    for (let i = 0; i < 4; i++) {
      const t = new TextoPixel(this, -100, -100, p.crema, 'centro');
      t.set(this.tema.textos.bonus);
      t.setVisible(false);
      t.setDepth(12);
      this.flotantes.push({ texto: t, ticks: 0, x: 0, y: 0 });
    }
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
    this.textoCuenta = new TextoPixel(this, ANCHO / 2, 36, this.tema.paleta.blanco, 'centro');
    this.textoCuenta.setDepth(20);
    this.textoCuenta.set('3');
    sonido.cuentaRegresiva(3);

    let n = 3;

    this.time.addEvent({
      delay: 1000,
      repeat: 3,
      callback: () => {
        n--;

        if (n > 0) {
          this.textoCuenta.set(String(n));
          sonido.cuentaRegresiva(n);
        } else if (n === 0) {
          this.textoCuenta.set(this.tema.textos.cuentaYa);
          sonido.cuentaRegresiva(0);
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

        const carrilAntes = this.estado.carril;
        paso(this.estado, entrada, this.pista);

        if (this.estado.carril !== carrilAntes) {
          this.carrilAnterior = carrilAntes;
          this.cuadrosCambio = CUADROS_CAMBIO_CARRIL;
        }

        this.sonarCambios();

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
     * Zonas táctiles, cuadrantes de media pantalla:
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

  /** Dispara los sonidos de lo que acaba de pasar en el último tick. */
  private sonarCambios(): void {
    const e = this.estado;

    if (e.items !== this.itemsPrevios) {
      sonido.recogerItem();
      this.itemsPrevios = e.items;
      this.lanzarFlotante();
    }

    if (e.caidas !== this.caidasPrevias) {
      sonido.caida();
      this.caidasPrevias = e.caidas;
    }

    if (e.sobrecalentamientos !== this.sobrecalentamientosPrevios) {
      sonido.sobrecalentar();
      this.sobrecalentamientosPrevios = e.sobrecalentamientos;
    }

    if (e.enAire && !this.enAirePrevio) {
      sonido.salto();
    } else if (!e.enAire && this.enAirePrevio && e.caido === 0) {
      sonido.aterrizajeLimpio();
    }
    this.enAirePrevio = e.enAire;

    // Pitido intermitente mientras la temperatura está en zona roja.
    if (e.sobrecalentado === 0 && e.temp > TEMP_MAX * 0.8 && e.tick % 20 === 0) {
      sonido.avisoCalor();
    }

    sonido.ajustarMotor(div(e.vel * 1000, V_MAX_TURBO), e.sobrecalentado > 0);
  }

  // -------------------------------------------------------------------------
  // Pintado
  // -------------------------------------------------------------------------

  private pintar(): void {
    const e = this.estado;
    const scroll = div(e.pos * PX_POR_METRO, 1000);

    // Parallax: cuanto más lejos, más despacio.
    this.nubes.tilePositionX = scroll * 0.04;
    this.cerros.tilePositionX = scroll * 0.14;
    this.tribuna.tilePositionX = scroll * 0.36;
    this.cesped.tilePositionX = scroll * 0.7;
    this.suelo.tilePositionX = scroll;

    // El cartel viaja con la tribuna y reaparece: si se quedara clavado en el
    // centro, la tribuna se movería por debajo y parecería un error.
    const recorrido = ANCHO + this.cartel.width;
    this.cartel.x = ANCHO - (((scroll * 0.36) % recorrido) | 0);

    this.pintarMoto();
    this.pintarPista();
    this.pintarFlotantes();
    this.pintarPanel();
  }

  private pintarMoto(): void {
    const e = this.estado;

    // Interpolación visual entre carriles. La física ya está en el nuevo.
    if (this.cuadrosCambio > 0) {
      this.cuadrosCambio--;
      const avance = 1 - this.cuadrosCambio / CUADROS_CAMBIO_CARRIL;
      this.carrilDibujado = this.carrilAnterior + (e.carril - this.carrilAnterior) * avance;
    } else {
      this.carrilDibujado = e.carril;
    }

    const yCarril = PISTA_Y + this.carrilDibujado * CARRIL_ALTO + CARRIL_ALTO;
    const alturaPx = e.altura * PX_POR_MM_ALTURA;

    // La moto se inclina mientras cambia de carril, como en el original.
    const inclinacionCarril = this.cuadrosCambio > 0 ? (e.carril - this.carrilAnterior) * 7 : 0;

    if (e.caido > 0) {
      this.moto.setTexture(TEX.motoCaida);
      this.moto.setAngle(0);
    } else if (e.enAire) {
      /*
       * En el aire se muestra la pose de salto tal como se dibujó, sin girarla
       * más. Sube a la rampa girada, se mantiene girada todo el vuelo y vuelve
       * a la normal al tocar el suelo.
       */
      this.moto.setTexture(TEX.motoWheelie);
      this.moto.setAngle(0);
    } else {
      this.moto.setTexture(TEX.moto);
      this.moto.setAngle(inclinacionCarril);
    }

    this.moto.setPosition(MOTO_X, yCarril - alturaPx);

    this.sombra.setVisible(e.enAire);
    if (e.enAire) {
      this.sombra.setPosition(MOTO_X, yCarril - 1);
    }

    // Parpadeo mientras se está caído, para que se note por qué no avanza.
    this.moto.setAlpha(e.caido > 0 && Math.floor(e.caido / 6) % 2 === 0 ? 0.45 : 1);

    this.pintarHumo(yCarril);
  }

  /** Humo saliendo del motor cuando la temperatura aprieta. */
  private pintarHumo(yCarril: number): void {
    const e = this.estado;
    const caliente = e.sobrecalentado > 0 || e.temp > TEMP_MAX * 0.7;

    if (!caliente) {
      for (const h of this.humo) {
        h.setVisible(false);
      }
      return;
    }

    const intensidad = e.sobrecalentado > 0 ? 1 : (e.temp - TEMP_MAX * 0.7) / (TEMP_MAX * 0.3);

    this.humo.forEach((h, i) => {
      // Cada bocanada sube y se desvanece con un desfase distinto.
      const fase = ((e.tick * 2 + i * 15) % 60) / 60;

      h.setVisible(true);
      h.setPosition(MOTO_X - 10 - fase * 8, yCarril - 12 - fase * 14);
      h.setAlpha((1 - fase) * 0.7 * intensidad);
      h.setScale(0.5 + fase);
    });
  }

  /** Coloca obstáculos y logos visibles reciclando los objetos creados. */
  private pintarPista(): void {
    const e = this.estado;
    const desdeMm = e.pos - div(MOTO_X * 1000, PX_POR_METRO);
    const hastaMm = e.pos + div((ANCHO - MOTO_X + 24) * 1000, PX_POR_METRO);

    let usados = 0;

    for (const obs of this.pista.obstaculos) {
      if (obs.pos < desdeMm) continue;
      if (obs.pos > hastaMm) break;
      if (usados >= this.obstaculos.length) break;

      const img = this.obstaculos[usados++];
      const x = MOTO_X + div((obs.pos - e.pos) * PX_POR_METRO, 1000);
      const y = PISTA_Y + obs.carril * CARRIL_ALTO + CARRIL_ALTO;

      if (obs.tipo === TIPO_RAMPA) {
        img.setTexture(TEX.rampa).setOrigin(0.5, 1).setPosition(x, y + 1);
      } else if (obs.tipo === TIPO_LODO) {
        img.setTexture(TEX.lodo).setOrigin(0.5, 0.5).setPosition(x, y - CARRIL_ALTO / 2);
      } else {
        img.setTexture(TEX.valla).setOrigin(0.5, 1).setPosition(x, y + 1);
      }

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
      const flote = Math.round(Math.sin((e.tick + i * 10) / 8) * 1.5);

      img.setPosition(
        MOTO_X + div((item.pos - e.pos) * PX_POR_METRO, 1000),
        PISTA_Y + item.carril * CARRIL_ALTO + CARRIL_ALTO / 2 + flote
      );
      img.setVisible(true);
    }

    for (let i = usadosItem; i < this.items.length; i++) {
      this.items[i].setVisible(false);
    }
  }

  /**
   * Suelta un "+50" sobre la moto, como las monedas del Mario.
   *
   * Sube unos píxeles mientras se desvanece. Si no hay ninguno libre se
   * reutiliza el más viejo: con cuatro sobra, porque los bonus están a 200 m
   * unos de otros.
   */
  private lanzarFlotante(): void {
    const libre =
      this.flotantes.find((f) => f.ticks === 0) ??
      this.flotantes.reduce((a, b) => (a.ticks > b.ticks ? a : b));

    const yCarril = PISTA_Y + this.carrilDibujado * CARRIL_ALTO + CARRIL_ALTO;

    libre.ticks = TICKS_FLOTANTE;
    libre.x = MOTO_X;
    libre.y = yCarril - 26;
    libre.texto.setVisible(true);
  }

  private pintarFlotantes(): void {
    for (const f of this.flotantes) {
      if (f.ticks <= 0) {
        continue;
      }

      f.ticks--;
      const avance = 1 - f.ticks / TICKS_FLOTANTE;

      f.texto.mover(f.x, Math.round(f.y - avance * 14));
      // Opaco durante la primera mitad y luego se desvanece.
      f.texto.setAlpha(avance < 0.5 ? 1 : 2 - avance * 2);

      if (f.ticks === 0) {
        f.texto.setVisible(false);
      }
    }
  }

  private pintarPanel(): void {
    const e = this.estado;
    const t = this.tema.textos;

    this.textoDist.set(`${distanciaMetros(e)}${t.unidadMetros}`);

    const seg = segundosRestantes(e, TOTAL_TICKS);
    this.textoTiempo.set(`${div(seg, 60)}:${String(seg % 60).padStart(2, '0')}`);

    this.barraTemp.width = Math.round((e.temp / TEMP_MAX) * 62);

    /*
     * Los dos avisos parpadean. El de motor parado, al doble de velocidad:
     * cuando ya no se puede hacer nada, el aviso tiene que verse más urgente
     * que cuando todavía hay tiempo de soltar el turbo.
     */
    if (e.sobrecalentado > 0) {
      this.avisoMotor.set(t.avisoSobrecalentado);
      this.mostrarAviso(Math.floor(e.tick / 5) % 2 === 0);
    } else if (e.temp > TEMP_MAX * 0.8) {
      this.avisoMotor.set(t.avisoCaliente);
      this.mostrarAviso(Math.floor(e.tick / 10) % 2 === 0);
    } else {
      this.mostrarAviso(false);
    }
  }

  /** Enseña u oculta el aviso del motor con su caja de fondo. */
  private mostrarAviso(visible: boolean): void {
    this.avisoMotor.setVisible(visible);
    this.avisoFondo.setVisible(visible);

    if (visible) {
      this.avisoFondo.width = this.avisoMotor.anchoActual + 6;
    }
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

    sonido.pararMotor();
    sonido.finCarrera();

    const e = this.estado;

    this.datos.alTerminar({
      token: this.datos.token,
      nombre: this.datos.nombre,
      distancia: distanciaMetros(e),
      items: e.items,
      caidas: e.caidas,
      sobrecalentamientos: e.sobrecalentamientos,
      entradas: codificar(this.registro),
      ticks: e.tick,
    });
  }
}
