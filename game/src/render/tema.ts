/**
 * Tema del juego: colores y textos.
 *
 * Los valores por defecto están aquí, pero el juego intenta cargar un
 * theme.json junto al bundle y lo fusiona encima. Así cambiar el cartel de la
 * tribuna o ajustar un color no obliga a recompilar ni a tener Node instalado
 * en el servidor: basta con editar un archivo.
 *
 * Los tres colores de marca salen del manual de TVS
 * (imagenes_apoyo/paleta_colores.jpeg). El resto se deriva de ellos y se
 * mantiene corto a propósito: parte de lo que hace que algo parezca de 16 bits
 * es que haya pocos colores, no muchos.
 */

export interface Paleta {
  // Marca
  azul: number;
  rojo: number;
  blanco: number;

  // Derivados
  azulClaro: number;
  azulProfundo: number;
  rojoOscuro: number;
  crema: number;
  negro: number;
  gris: number;
  grisClaro: number;
  amarillo: number;
  piel: number;
  /** Azul del traje y el chasis de la moto deportiva. */
  azulMoto: number;
  /** Rojo del diseño de la moto. Más encendido que el rojo del manual TVS. */
  rojoMoto: number;
  /** Plata de la horquilla y el escape. */
  plata: number;
  /** Plata clara de los reflejos y de la franja del cono. */
  plataClara: number;
  /** Amarillo del faro. */
  amarilloFaro: number;

  // Llave del bonus
  amarilloLlave: number;
  amarilloLlaveClaro: number;
  naranjaLlave: number;

  /**
   * Naranja del cono de vía. También el puño izquierdo de la moto, que el
   * diseño trae en ese mismo hexadecimal exacto.
   */
  naranjaCono: number;
  naranjaConoOscuro: number;

  // Impulsor
  verdeImpulsor: number;
  verdeImpulsorOscuro: number;

  // Escenario
  cielo: number;
  cieloAlto: number;
  verde: number;
  verdeOscuro: number;
  verdeClaro: number;
  /**
   * Asfalto del carril.
   *
   * Tiene un techo: cuanto más se aclare, menos se despega la moto de la
   * pista. Con el diseño gris llegó a ser crítico —el chasis era #808080— y
   * con el rojo y azul de ahora hay más margen, pero el criterio sigue siendo
   * el mismo: la moto tiene que verse de un vistazo.
   */
  pista: number;
  /** Asfalto del carril alterno, apenas más claro. */
  pistaAlt: number;
  /** Junta entre carriles y borde de la calzada. */
  pistaBorde: number;
  /** Negro del charco de aceite. */
  aceite: number;
  /** Tornasol del aceite bajo la luz. */
  aceiteBrillo: number;

  // Panel
  tempFria: number;
  tempCaliente: number;
}

export interface Textos {
  cartelTribuna: string;
  rotuloDistancia: string;
  rotuloTemperatura: string;
  rotuloTiempo: string;
  avisoCaliente: string;
  avisoSobrecalentado: string;
  cuentaYa: string;
  podioTitulo: string;
  podioGracias: string;
  unidadMetros: string;
  /** Lo que sube flotando al recoger un bonus. */
  bonus: string;
  /** Lo que sube flotando al pisar un impulsor. */
  bonusImpulsor: string;
}

export interface Tema {
  paleta: Paleta;
  textos: Textos;
}

export const TEMA_POR_DEFECTO: Tema = {
  paleta: {
    azul: 0x1f3a72,
    rojo: 0xe01d2d,
    blanco: 0xffffff,

    azulClaro: 0x4a90d9,
    azulProfundo: 0x0b1730,
    rojoOscuro: 0x8f0f1c,
    crema: 0xf2e9d8,
    negro: 0x0a0a0a,
    gris: 0x5a5a5a,
    grisClaro: 0x9a9a9a,
    amarillo: 0xf5c518,
    piel: 0xe8b38a,
    azulMoto: 0x0070c0,
    rojoMoto: 0xff0000,
    plata: 0xa6a6a6,
    plataClara: 0xe0e0e0,
    amarilloFaro: 0xffff00,

    amarilloLlave: 0xffdb01,
    amarilloLlaveClaro: 0xffef8f,
    naranjaLlave: 0xff9f00,

    naranjaCono: 0xff6f00,
    naranjaConoOscuro: 0xd25a00,

    verdeImpulsor: 0x66ff33,
    verdeImpulsorOscuro: 0x12501a,

    cielo: 0x4a90d9,
    cieloAlto: 0x2f6fb5,
    verde: 0x3aa93a,
    verdeOscuro: 0x227722,
    verdeClaro: 0x5fc75f,
    pista: 0x5e5e5e,
    pistaAlt: 0x686868,
    pistaBorde: 0x3e3e3e,
    aceite: 0x14141c,
    aceiteBrillo: 0x4a3a6a,

    tempFria: 0x3aa93a,
    tempCaliente: 0xe01d2d,
  },
  textos: {
    cartelTribuna: 'NAVIDAD TVS',
    rotuloDistancia: 'DIST',
    rotuloTemperatura: 'TEMP',
    rotuloTiempo: 'TIME',
    avisoCaliente: 'MOTOR CALIENTE',
    avisoSobrecalentado: 'MOTOR SOBRECALENTADO',
    cuentaYa: 'YA!',
    podioTitulo: 'NAVIDAD TVS',
    podioGracias: 'GRACIAS POR PARTICIPAR',
    unidadMetros: 'M',
    bonus: '+50',
    bonusImpulsor: '+1',
  },
};

/** Acepta "#1F3A72", "1F3A72" o un número, y devuelve un número. */
function aColor(valor: unknown, sino: number): number {
  if (typeof valor === 'number' && Number.isFinite(valor)) {
    return valor;
  }

  if (typeof valor === 'string') {
    const limpio = valor.trim().replace(/^#/, '');
    if (/^[0-9a-fA-F]{6}$/.test(limpio)) {
      return parseInt(limpio, 16);
    }
  }

  return sino;
}

/**
 * Fusiona lo que venga del JSON sobre los valores por defecto.
 *
 * Tolerante a propósito: un theme.json incompleto o con una clave mal escrita
 * no puede dejar el juego sin colores en plena jornada. Lo que no se entienda
 * se ignora y se usa el valor por defecto.
 */
export function fusionarTema(crudo: unknown): Tema {
  const tema: Tema = {
    paleta: { ...TEMA_POR_DEFECTO.paleta },
    textos: { ...TEMA_POR_DEFECTO.textos },
  };

  if (!crudo || typeof crudo !== 'object') {
    return tema;
  }

  const obj = crudo as Record<string, unknown>;

  if (obj.paleta && typeof obj.paleta === 'object') {
    const p = obj.paleta as Record<string, unknown>;
    for (const clave of Object.keys(tema.paleta) as Array<keyof Paleta>) {
      if (clave in p) {
        tema.paleta[clave] = aColor(p[clave], tema.paleta[clave]);
      }
    }
  }

  if (obj.textos && typeof obj.textos === 'object') {
    const t = obj.textos as Record<string, unknown>;
    for (const clave of Object.keys(tema.textos) as Array<keyof Textos>) {
      if (typeof t[clave] === 'string') {
        tema.textos[clave] = t[clave] as string;
      }
    }
  }

  return tema;
}

/**
 * Carga el tema desde una URL.
 *
 * Si falla, devuelve el tema por defecto. Quedarse sin jugar porque no se pudo
 * bajar un archivo de colores sería absurdo.
 */
export async function cargarTema(url?: string): Promise<Tema> {
  if (!url) {
    return TEMA_POR_DEFECTO;
  }

  try {
    const respuesta = await fetch(url, { cache: 'no-cache' });

    if (!respuesta.ok) {
      return TEMA_POR_DEFECTO;
    }

    return fusionarTema(await respuesta.json());
  } catch {
    console.warn('[navidad-tvs] No se pudo cargar el tema; se usan los colores por defecto.');
    return TEMA_POR_DEFECTO;
  }
}
