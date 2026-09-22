/**
 * Generador pseudoaleatorio mulberry32.
 *
 * La pista sale de aquí, sembrada con el seed que emite el servidor. Nada de
 * Math.random(): si la pista no fuera reproducible, el servidor no podría
 * reejecutar la carrera para validarla.
 *
 * Toda la aritmética es de 32 bits sin signo. En TypeScript eso se consigue
 * cerrando cada operación con >>> 0; el puerto PHP de E6 tiene que hacer lo
 * mismo con & 0xFFFFFFFF y una multiplicación de 32 bits equivalente a
 * Math.imul.
 */
export class Prng {
  private estado: number;

  constructor(seed: number) {
    this.estado = seed >>> 0;
  }

  /** Devuelve un entero sin signo de 32 bits. Nunca un flotante entre 0 y 1. */
  siguiente(): number {
    this.estado = (this.estado + 0x6d2b79f5) >>> 0;
    let t = this.estado;
    t = Math.imul(t ^ (t >>> 15), t | 1) >>> 0;
    t = (t ^ (t + Math.imul(t ^ (t >>> 7), t | 61))) >>> 0;
    return (t ^ (t >>> 14)) >>> 0;
  }

  /**
   * Entero entre min y max, ambos incluidos.
   *
   * El módulo introduce un sesgo mínimo hacia los valores bajos del rango.
   * Para colocar obstáculos en una pista es irrelevante, y evitarlo costaría
   * un bucle de rechazo que habría que replicar exactamente en PHP.
   */
  rango(min: number, max: number): number {
    return min + (this.siguiente() % (max - min + 1));
  }

  /** True con probabilidad porMil/1000. */
  probabilidad(porMil: number): boolean {
    return this.siguiente() % 1000 < porMil;
  }
}
