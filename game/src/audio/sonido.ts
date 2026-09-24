/**
 * Sonido chiptune sintetizado en el navegador.
 *
 * No hay archivos de audio. Se generan ondas cuadradas y triangulares con la
 * Web Audio API, que es literalmente cómo sonaban las consolas de 8 y 16 bits.
 *
 * Tres razones para hacerlo así y no con .ogg y .m4a:
 *
 * - Cero bytes que descargar en la ventana de 90 minutos, donde todo el
 *   tráfico del día llega junto.
 * - Se acabó el problema de códecs: Safari no reproduce ogg y habría que
 *   servir cada pista dos veces.
 * - El motor puede cambiar de tono con la velocidad, que con un archivo
 *   grabado no se puede.
 *
 * iOS no deja sonar nada hasta que el usuario toca la pantalla, así que el
 * contexto se crea y se desbloquea en el primer gesto.
 */

type TipoOnda = 'square' | 'triangle' | 'sawtooth' | 'sine';

export class Sonido {
  private ctx: AudioContext | null = null;
  private maestro: GainNode | null = null;

  /** Oscilador del motor: suena continuo y cambia de tono con la velocidad. */
  private motorOsc: OscillatorNode | null = null;
  private motorGain: GainNode | null = null;

  private silenciado = false;
  private musicaTimer: number | null = null;

  /**
   * Crea el contexto de audio. Hay que llamarlo desde un gesto del usuario.
   *
   * @returns true si el audio quedó disponible.
   */
  iniciar(): boolean {
    if (this.ctx) {
      void this.ctx.resume();
      return true;
    }

    try {
      const Ctor =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;

      if (!Ctor) {
        return false;
      }

      this.ctx = new Ctor();
      this.maestro = this.ctx.createGain();
      this.maestro.gain.value = 0.35;
      this.maestro.connect(this.ctx.destination);

      void this.ctx.resume();
      return true;
    } catch {
      // Sin audio se juega igual. No es motivo para romper nada.
      return false;
    }
  }

  silenciar(valor: boolean): void {
    this.silenciado = valor;
    if (this.maestro) {
      this.maestro.gain.value = valor ? 0 : 0.35;
    }
  }

  estaSilenciado(): boolean {
    return this.silenciado;
  }

  /**
   * Toca una nota corta.
   *
   * @param frecuencia Hz.
   * @param duracion   Segundos.
   * @param tipo       Forma de onda.
   * @param volumen    0 a 1.
   * @param retraso    Segundos desde ahora.
   */
  nota(frecuencia: number, duracion: number, tipo: TipoOnda = 'square', volumen = 0.3, retraso = 0): void {
    if (!this.ctx || !this.maestro) {
      return;
    }

    const t0 = this.ctx.currentTime + retraso;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = tipo;
    osc.frequency.setValueAtTime(frecuencia, t0);

    // Envolvente corta: ataque casi instantáneo y caída, como un canal de
    // pulso de las consolas de la época.
    gain.gain.setValueAtTime(0, t0);
    gain.gain.linearRampToValueAtTime(volumen, t0 + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duracion);

    osc.connect(gain);
    gain.connect(this.maestro);

    osc.start(t0);
    osc.stop(t0 + duracion + 0.02);
  }

  /** Barrido de frecuencia, para saltos y aterrizajes. */
  barrido(desde: number, hasta: number, duracion: number, tipo: TipoOnda = 'square', volumen = 0.25): void {
    if (!this.ctx || !this.maestro) {
      return;
    }

    const t0 = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = tipo;
    osc.frequency.setValueAtTime(desde, t0);
    osc.frequency.exponentialRampToValueAtTime(Math.max(20, hasta), t0 + duracion);

    gain.gain.setValueAtTime(volumen, t0);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duracion);

    osc.connect(gain);
    gain.connect(this.maestro);

    osc.start(t0);
    osc.stop(t0 + duracion + 0.02);
  }

  /** Ruido blanco filtrado, para golpes y derrapes. */
  ruido(duracion: number, volumen = 0.3, frecuenciaFiltro = 1200): void {
    if (!this.ctx || !this.maestro) {
      return;
    }

    const muestras = Math.floor(this.ctx.sampleRate * duracion);
    const buffer = this.ctx.createBuffer(1, muestras, this.ctx.sampleRate);
    const datos = buffer.getChannelData(0);

    for (let i = 0; i < muestras; i++) {
      datos[i] = Math.random() * 2 - 1;
    }

    const fuente = this.ctx.createBufferSource();
    fuente.buffer = buffer;

    const filtro = this.ctx.createBiquadFilter();
    filtro.type = 'lowpass';
    filtro.frequency.value = frecuenciaFiltro;

    const gain = this.ctx.createGain();
    const t0 = this.ctx.currentTime;
    gain.gain.setValueAtTime(volumen, t0);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + duracion);

    fuente.connect(filtro);
    filtro.connect(gain);
    gain.connect(this.maestro);

    fuente.start(t0);
  }

  // -------------------------------------------------------------------------
  // Motor
  // -------------------------------------------------------------------------

  /** Arranca el zumbido continuo del motor. */
  arrancarMotor(): void {
    if (!this.ctx || !this.maestro || this.motorOsc) {
      return;
    }

    this.motorOsc = this.ctx.createOscillator();
    this.motorGain = this.ctx.createGain();

    this.motorOsc.type = 'sawtooth';
    this.motorOsc.frequency.value = 60;
    this.motorGain.gain.value = 0.06;

    this.motorOsc.connect(this.motorGain);
    this.motorGain.connect(this.maestro);
    this.motorOsc.start();
  }

  /**
   * Ajusta el motor a la velocidad actual.
   *
   * @param velocidadPorMil Velocidad como milésimas del máximo, 0 a 1000.
   * @param sobrecalentado  Si el motor está parado por temperatura.
   */
  ajustarMotor(velocidadPorMil: number, sobrecalentado: boolean): void {
    if (!this.motorOsc || !this.motorGain || !this.ctx) {
      return;
    }

    const destino = sobrecalentado ? 35 : 55 + (velocidadPorMil * 145) / 1000;
    const volumen = sobrecalentado ? 0.02 : 0.04 + (velocidadPorMil * 0.05) / 1000;

    // Rampa corta en vez de salto: un cambio brusco de frecuencia chasquea.
    const t = this.ctx.currentTime;
    this.motorOsc.frequency.linearRampToValueAtTime(destino, t + 0.08);
    this.motorGain.gain.linearRampToValueAtTime(volumen, t + 0.08);
  }

  pararMotor(): void {
    if (this.motorOsc) {
      try {
        this.motorOsc.stop();
      } catch {
        // Ya estaba parado.
      }
      this.motorOsc.disconnect();
      this.motorOsc = null;
    }
    if (this.motorGain) {
      this.motorGain.disconnect();
      this.motorGain = null;
    }
  }

  // -------------------------------------------------------------------------
  // Efectos del juego
  // -------------------------------------------------------------------------

  cuentaRegresiva(numero: number): void {
    // Tres pitidos graves y uno agudo al arrancar, como en cualquier semáforo
    // de carreras.
    this.nota(numero > 0 ? 440 : 880, numero > 0 ? 0.12 : 0.35, 'square', 0.35);
  }

  recogerItem(): void {
    // Arpegio ascendente: se reconoce al instante como "algo bueno".
    this.nota(880, 0.07, 'square', 0.28);
    this.nota(1174, 0.07, 'square', 0.28, 0.06);
    this.nota(1568, 0.12, 'square', 0.28, 0.12);
  }

  /**
   * Impulsor pisado.
   *
   * Barrido ascendente corto y una nota encima: tiene que oírse como un
   * empujón, no como el salto que había antes, que subía y se quedaba
   * colgado esperando el aterrizaje.
   */
  impulsor(): void {
    this.barrido(320, 900, 0.14, 'square', 0.22);
    this.nota(1046, 0.09, 'triangle', 0.2, 0.05);
  }

  caida(): void {
    this.ruido(0.35, 0.35, 900);
    this.barrido(400, 60, 0.4, 'sawtooth', 0.22);
  }

  sobrecalentar(): void {
    // Dos notas descendentes y ruido: suena a avería.
    this.nota(300, 0.2, 'square', 0.3);
    this.nota(200, 0.3, 'square', 0.3, 0.18);
    this.ruido(0.5, 0.15, 500);
  }

  avisoCalor(): void {
    this.nota(1400, 0.05, 'square', 0.12);
  }

  lodo(): void {
    this.ruido(0.25, 0.18, 400);
  }

  finCarrera(): void {
    this.nota(523, 0.15, 'square', 0.3);
    this.nota(659, 0.15, 'square', 0.3, 0.15);
    this.nota(784, 0.15, 'square', 0.3, 0.3);
    this.nota(1047, 0.45, 'square', 0.3, 0.45);
  }

  /**
   * Fanfarria del podio.
   *
   * Cuatro compases de aire navideño, en ondas cuadradas.
   */
  fanfarriaPodio(): void {
    const melodia: Array<[number, number]> = [
      [659, 0.18],
      [659, 0.18],
      [659, 0.36],
      [659, 0.18],
      [659, 0.18],
      [659, 0.36],
      [659, 0.18],
      [784, 0.18],
      [523, 0.18],
      [587, 0.18],
      [659, 0.72],
    ];

    let t = 0;
    for (const [frecuencia, duracion] of melodia) {
      this.nota(frecuencia, duracion * 0.9, 'square', 0.22, t);
      // Bajo una octava abajo, en triangular.
      this.nota(frecuencia / 2, duracion * 0.9, 'triangle', 0.12, t);
      t += duracion;
    }
  }

  /** Corta todo y libera el contexto. */
  detener(): void {
    this.pararMotor();

    if (this.musicaTimer !== null) {
      window.clearTimeout(this.musicaTimer);
      this.musicaTimer = null;
    }
  }
}

/** Instancia compartida: un solo contexto de audio por página. */
export const sonido = new Sonido();
