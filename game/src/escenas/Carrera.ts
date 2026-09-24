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
  TIPO_ACEITE,
  TIPO_IMPULSOR,
  TOTAL_TICKS,
  TPS,
  V_MAX_TURBO,
  div,
} from '../sim/constantes';
import { BIT_ABAJO, BIT_ACELERA, BIT_ARRIBA, BIT_TURBO, codificar, registroVacio } from '../sim/entradas';
import { generarPista, type Pista } from '../sim/pista';
import {
  crearEstado,
  distanciaMetros,
  paso,
  segundosRestantes,
  subidaEscalon,
  type Estado,
} from '../sim/simulacion';
import { TextoPixel } from '../render/fuente';
import type { Tema } from '../render/tema';
import {
  ALTO,
  ANCHO,
  CARRIL_ALTO,
  CERROS_ALTO,
  CERROS_Y,
  CESPED_ALTO,
  CESPED_Y,
  MOTO_X,
  NUBES_ALTO,
  NUBES_Y,
  PANEL_ALTO,
  PANEL_Y,
  PISTA_ALTO,
  PISTA_Y,
  PX_POR_METRO,
  TRIBUNA_ALTO,
  TRIBUNA_Y,
} from '../render/medidas';
import { TEX, crearPanel, crearTexturas } from '../render/texturas';

export { ALTO, ANCHO };

/**
 * Estado del mando en pantalla.
 *
 * Lo crea y lo mantiene el JavaScript de la página (assets/js/acceso.js), que
 * es quien dibuja los botones. El juego solo lee.
 */
export interface Mando {
  arriba: boolean;
  abajo: boolean;
  acelera: boolean;
  turbo: boolean;
}

declare global {
  interface Window {
    navidadTvsMando?: Mando;
  }
}

/**
 * Línea sobre la que se apoya todo lo que está en un carril.
 *
 * Es el centro del carril, no su borde inferior. Con las ruedas en el borde la
 * moto parecía ir montada sobre la línea discontinua en vez de dentro del
 * carril, y quedaba más baja que los impulsores y los charcos, que sí van
 * centrados. Todo lo que pisa la pista —la moto, el cono— se apoya aquí.
 *
 * @param carril Índice del carril, que puede ser fraccionario mientras la moto
 *               se desliza de uno a otro.
 */
function apoyo(carril: number): number {
  return PISTA_Y + carril * CARRIL_ALTO + CARRIL_ALTO / 2;
}

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

/** Alto de la caja negra del aviso del motor: el texto y dos píxeles por lado. */
const ALTO_AVISO = 11;

/**
 * Cuánto dura el aviso de que la velocidad subió.
 *
 * Hace falta avisar. Cada 20 segundos el techo sube solo, y una moto que de
 * pronto corre más sin que el jugador haya hecho nada se lee como un fallo si
 * nadie le dice que es parte del juego.
 */
const TICKS_AVISO_VELOCIDAD = 96;

/**
 * Cuánto se queda la pantalla congelada al acabarse el tiempo.
 *
 * Sin esta pausa el reloj se leía como "0:01": el último segundo de carrera
 * muestra 0:01 durante sesenta ticks y el 0:00 solo existe en el tick 5400,
 * que pasaba directo al podio sin que diera tiempo de verlo. Ahora ese
 * fotograma se sostiene un momento, con el reloj en 0:00 y la moto de vuelta
 * en el suelo, y de ahí se va al podio.
 */
const MS_CONGELADO_FINAL = 1200;

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
  impulsores: number;
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
  private humo: Phaser.GameObjects.Image[] = [];
  private obstaculos: Phaser.GameObjects.Image[] = [];
  private items: Phaser.GameObjects.Image[] = [];

  // Panel
  private textoDist!: TextoPixel;
  private textoTiempo!: TextoPixel;
  private barraTemp!: Phaser.GameObjects.Rectangle;
  private anchoBarraTemp = 0;
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
  private impulsoresPrevios = 0;
  private subidaPrevia = 0;
  private ticksAvisoVelocidad = 0;
  private caidasPrevias = 0;
  private sobrecalentamientosPrevios = 0;

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
    this.impulsoresPrevios = 0;
    this.subidaPrevia = 0;
    this.ticksAvisoVelocidad = 0;
    this.caidasPrevias = 0;
    this.sobrecalentamientosPrevios = 0;
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
    this.nubes = this.add.tileSprite(0, NUBES_Y, ANCHO, NUBES_ALTO, TEX.nubes).setOrigin(0, 0);
    this.cerros = this.add.tileSprite(0, CERROS_Y, ANCHO, CERROS_ALTO, TEX.cerros).setOrigin(0, 0);
    this.tribuna = this.add.tileSprite(0, TRIBUNA_Y, ANCHO, TRIBUNA_ALTO, TEX.tribuna).setOrigin(0, 0);

    this.cartel = this.add.image(ANCHO / 2, TRIBUNA_Y + 2, TEX.cartel).setOrigin(0.5, 0);

    this.cesped = this.add.tileSprite(0, CESPED_Y, ANCHO, CESPED_ALTO, TEX.cesped).setOrigin(0, 0);
    this.suelo = this.add.tileSprite(0, PISTA_Y, ANCHO, PISTA_ALTO, TEX.pista).setOrigin(0, 0);
  }

  private construirActores(): void {
    // Se crean pocos objetos y se reciclan: en pantalla nunca caben muchos.
    for (let i = 0; i < 14; i++) {
      this.obstaculos.push(this.add.image(-100, 0, TEX.cono).setOrigin(0.5, 1).setVisible(false));
    }
    for (let i = 0; i < 6; i++) {
      this.items.push(this.add.image(-100, 0, TEX.item).setOrigin(0.5, 0.5).setVisible(false));
    }

    this.moto = this.add.image(MOTO_X, 0, TEX.moto).setOrigin(0.5, 1);

    for (let i = 0; i < 4; i++) {
      this.humo.push(this.add.image(-100, 0, TEX.humo).setOrigin(0.5, 0.5).setVisible(false));
    }
  }

  private construirPanel(): void {
    this.add.image(0, PANEL_Y, TEX.panel).setOrigin(0, 0);

    const p = this.tema.paleta;
    const cx = Math.floor(ANCHO / 2);

    // Las mismas tres columnas que dibuja crearPanel().
    const columna = Math.floor(ANCHO / 3);
    const cajaAncho = columna - 8;
    const izquierda = Math.floor((columna - cajaAncho) / 2);
    const derecha = ANCHO - izquierda - cajaAncho;

    this.textoDist = new TextoPixel(this, izquierda + Math.floor(cajaAncho / 2), PANEL_Y + 17, p.blanco, 'centro');
    this.textoTiempo = new TextoPixel(this, derecha + Math.floor(cajaAncho / 2), PANEL_Y + 17, p.blanco, 'centro');

    // Barra de temperatura: verde de fondo, rojo que crece encima.
    this.anchoBarraTemp = cajaAncho - 4;
    const barraX = cx - Math.floor(this.anchoBarraTemp / 2);
    this.add.rectangle(barraX, PANEL_Y + 16, this.anchoBarraTemp, 8, p.tempFria).setOrigin(0, 0);
    this.barraTemp = this.add.rectangle(barraX, PANEL_Y + 16, 0, 8, p.tempCaliente).setOrigin(0, 0);

    /*
     * Aviso del motor: letras rojas sobre una caja negra.
     * El rojo solo no se leería sobre el verde del césped ni sobre la pista.
     */
    this.avisoFondo = this.add.rectangle(cx, PISTA_Y - 13, 10, ALTO_AVISO, p.negro).setOrigin(0.5, 0);
    this.avisoFondo.setDepth(9);
    this.avisoFondo.setVisible(false);

    this.avisoMotor = new TextoPixel(this, cx, PISTA_Y - 11, p.rojo, 'centro');
    this.avisoMotor.setVisible(false);
    this.avisoMotor.setDepth(10);

    // Rótulos flotantes del bonus. Se reciclan; nunca coinciden muchos a la vez.
    for (let i = 0; i < 4; i++) {
      const t = new TextoPixel(this, -100, -100, p.crema, 'centro');
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

  /**
   * Lee el estado actual del teclado y del mando en pantalla.
   *
   * El mando es HTML, no forma parte del lienzo: vive debajo del juego, en la
   * página, y solo deja aquí cuatro banderas. Antes la pantalla se dividía en
   * cuatro cuadrantes invisibles y había que adivinar dónde tocar; con un solo
   * intento por persona, adivinar no es una opción.
   *
   * Y ya que hay botones de verdad, el lienzo dejó de escuchar toques: con el
   * mando debajo, un dedo apoyado en la esquina de la pantalla habría metido
   * turbo sin querer y recalentado el motor.
   */
  private leerEntrada(): number {
    let entrada = 0;

    if (this.teclas) {
      if (this.teclas.acelera.isDown) entrada |= BIT_ACELERA;
      if (this.teclas.turbo.isDown) entrada |= BIT_TURBO;
      if (this.teclas.arriba.isDown) entrada |= BIT_ARRIBA;
      if (this.teclas.abajo.isDown) entrada |= BIT_ABAJO;
    }

    const mando = typeof window !== 'undefined' ? window.navidadTvsMando : undefined;

    if (mando) {
      if (mando.acelera) entrada |= BIT_ACELERA;
      if (mando.turbo) entrada |= BIT_TURBO;
      if (mando.arriba) entrada |= BIT_ARRIBA;
      if (mando.abajo) entrada |= BIT_ABAJO;
    }

    return entrada;
  }

  /** Dispara los sonidos de lo que acaba de pasar en el último tick. */
  private sonarCambios(): void {
    const e = this.estado;

    if (e.items !== this.itemsPrevios) {
      sonido.recogerItem();
      this.itemsPrevios = e.items;
      this.lanzarFlotante(this.tema.textos.bonus);
    }

    // El techo de velocidad sube por escalones con el reloj.
    const subida = subidaEscalon(e.tick);

    if (subida !== this.subidaPrevia) {
      this.subidaPrevia = subida;
      this.ticksAvisoVelocidad = TICKS_AVISO_VELOCIDAD;
      this.textoCuenta.set(this.tema.textos.avisoVelocidad);
      sonido.subeVelocidad();
    }

    // Cada impulsor pisado suma un metro y lo anuncia igual que el bonus.
    if (e.impulsores !== this.impulsoresPrevios) {
      sonido.impulsor();
      this.impulsoresPrevios = e.impulsores;
      this.lanzarFlotante(this.tema.textos.bonusImpulsor);
    }

    if (e.caidas !== this.caidasPrevias) {
      sonido.caida();
      this.caidasPrevias = e.caidas;
    }

    if (e.sobrecalentamientos !== this.sobrecalentamientosPrevios) {
      sonido.sobrecalentar();
      this.sobrecalentamientosPrevios = e.sobrecalentamientos;
    }

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
    this.pintarAvisoVelocidad();
    this.pintarPanel();
  }

  private pintarMoto(): void {
    const e = this.estado;

    /*
     * Se acabó el tiempo: la moto vuelve al suelo, derecha y en su pose
     * normal. Dejar el fotograma final con el piloto por el aire o tumbado
     * parece que la carrera se cortó a media maniobra.
     */
    if (this.terminada) {
      const yFinal = apoyo(this.carrilDibujado);

      this.moto.setTexture(TEX.moto);
      this.moto.setAngle(0);
      this.moto.setAlpha(1);
      this.moto.setPosition(MOTO_X, yFinal);

      for (const h of this.humo) {
        h.setVisible(false);
      }

      return;
    }

    // Interpolación visual entre carriles. La física ya está en el nuevo.
    if (this.cuadrosCambio > 0) {
      this.cuadrosCambio--;
      const avance = 1 - this.cuadrosCambio / CUADROS_CAMBIO_CARRIL;
      this.carrilDibujado = this.carrilAnterior + (e.carril - this.carrilAnterior) * avance;
    } else {
      this.carrilDibujado = e.carril;
    }

    const yCarril = apoyo(this.carrilDibujado);

    // La moto se inclina mientras cambia de carril, como en el original.
    const inclinacionCarril = this.cuadrosCambio > 0 ? (e.carril - this.carrilAnterior) * 7 : 0;

    if (e.caido > 0) {
      this.moto.setTexture(TEX.motoCaida);
      this.moto.setAngle(0);
    } else {
      this.moto.setTexture(TEX.moto);
      this.moto.setAngle(inclinacionCarril);
    }

    this.moto.setPosition(MOTO_X, yCarril);

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
      const y = apoyo(obs.carril);

      if (obs.tipo === TIPO_IMPULSOR) {
        // Pintado plano sobre la línea de apoyo: es una placa en el suelo.
        img.setTexture(TEX.impulsor).setOrigin(0.5, 0.5).setPosition(x, y);
      } else if (obs.tipo === TIPO_ACEITE) {
        img.setTexture(TEX.aceite).setOrigin(0.5, 0.5).setPosition(x, y);
      } else {
        // El cono se apoya en la misma línea que las ruedas de la moto.
        img.setTexture(TEX.cono).setOrigin(0.5, 1).setPosition(x, y);
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
        apoyo(item.carril) + flote
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
  private lanzarFlotante(texto: string): void {
    const libre =
      this.flotantes.find((f) => f.ticks === 0) ??
      this.flotantes.reduce((a, b) => (a.ticks > b.ticks ? a : b));

    let y = apoyo(this.carrilDibujado) - 26;

    /*
     * Un bonus y una rampa pueden caer en el mismo tick. Si los dos rótulos
     * salieran del mismo sitio se taparían y no se leería ninguno, así que el
     * segundo arranca una línea más arriba.
     */
    while (this.flotantes.some((f) => f !== libre && f.ticks > 0 && Math.abs(f.y - y) < 9)) {
      y -= 9;
    }

    libre.ticks = TICKS_FLOTANTE;
    libre.x = MOTO_X;
    libre.y = y;
    libre.texto.set(texto);
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

  /** El aviso de subida de velocidad, parpadeando y por un momento. */
  private pintarAvisoVelocidad(): void {
    if (this.ticksAvisoVelocidad <= 0) {
      return;
    }

    this.ticksAvisoVelocidad--;
    this.textoCuenta.setVisible(this.ticksAvisoVelocidad === 0 ? false : Math.floor(this.ticksAvisoVelocidad / 8) % 2 === 0);
  }

  private pintarPanel(): void {
    const e = this.estado;
    const t = this.tema.textos;

    this.textoDist.set(`${distanciaMetros(e)}${t.unidadMetros}`);

    const seg = segundosRestantes(e, TOTAL_TICKS);
    this.textoTiempo.set(`${div(seg, 60)}:${String(seg % 60).padStart(2, '0')}`);

    this.barraTemp.width = Math.round((e.temp / TEMP_MAX) * this.anchoBarraTemp);

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

    if (!visible) {
      return;
    }

    /*
     * setSize() y no .width. Asignar el ancho a pelo cambia el número pero no
     * recalcula el origen, que se quedó con el que tenía la caja al crearse
     * —10 px, o sea 5 de origen— así que la caja crecía entera hacia la
     * derecha y el texto quedaba pegado a su borde izquierdo en vez de en el
     * medio. setSize() actualiza la geometría y el origen a la vez.
     */
    const ancho = this.avisoMotor.anchoActual + 6;

    if (this.avisoFondo.width !== ancho) {
      this.avisoFondo.setSize(ancho, ALTO_AVISO);
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

    const resultado: ResultadoCarrera = {
      token: this.datos.token,
      nombre: this.datos.nombre,
      distancia: distanciaMetros(e),
      items: e.items,
      impulsores: e.impulsores,
      caidas: e.caidas,
      sobrecalentamientos: e.sobrecalentamientos,
      entradas: codificar(this.registro),
      ticks: e.tick,
    };

    // El podio espera a que se vea el último fotograma con el reloj en 0:00.
    this.time.delayedCall(MS_CONGELADO_FINAL, () => this.datos.alTerminar(resultado));
  }
}
