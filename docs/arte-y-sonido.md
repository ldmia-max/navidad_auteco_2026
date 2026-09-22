# Arte y sonido del juego

Cómo está hecho lo que se ve y se oye, y cómo cambiarlo.

---

## 1. El pixel art se escribe, no se dibuja

Todos los sprites viven en [`game/src/render/sprites.ts`](../game/src/render/sprites.ts)
como arreglos de texto. Cada carácter es un color:

```ts
export const ITEM_TVS: Sprite = [
  '...wwwwwwww...',
  '..wwwwwwwwww..',
  'ww..aaaaaaa.ww',
  ...
];
```

La correspondencia carácter → color está en `LEYENDA`, dentro de
[`pixeles.ts`](../game/src/render/pixeles.ts): `w` blanco, `a` azul, `r` rojo,
`n` negro, `.` transparente, y así.

Se hizo de esta forma por tres motivos:

- **Se lee lo que se dibuja.** No hace falta abrir un editor de imágenes para
  saber qué hay en un sprite.
- **Un diff muestra qué píxel cambió.** Con un PNG, el diff solo dice "cambió
  el archivo".
- **El tema puede recolorearlo todo** sin tocar los sprites, porque los colores
  se nombran en vez de escribirse en hexadecimal.

### Cambiar un sprite

Se edita el texto y listo. Antes de commitear:

```bash
cd game && npm run arte
```

Eso comprueba que todas las filas midan lo mismo, que no haya caracteres fuera
de la leyenda y que se respeten los tamaños de los que depende el juego (por
ejemplo, la moto y el wheelie tienen que medir igual o la moto daría un salto
al cambiar de textura).

### Verlo sin abrir el navegador

```bash
cd game && npm run arte:png
```

Genera en `game/salida-arte/` una hoja con todos los sprites y maquetas de la
pantalla de carrera y del podio. Sirve para revisar el arte y para adjuntar
capturas cuando se discute un cambio.

---

## 2. Tipografía

El juego no usa fuentes del sistema. Hay una tipografía de píxeles de 5×7 en
[`fuente.ts`](../game/src/render/fuente.ts), con mayúsculas, dígitos y unos
pocos signos.

Una fuente del sistema se reescala con suavizado y rompe el aspecto de 16 bits;
además cada dispositivo tiene la suya, así que el mismo texto ocuparía distinto
ancho en cada teléfono y el panel se descuadraría.

Dentro del lienzo solo hay texto sin tildes: `normalizar()` las quita, porque un
cuadro en medio de una palabra parece un error y "PARTICIPACION" se lee
perfectamente. Los textos con tildes viven en el HTML, donde sí hay tipografía
de verdad.

---

## 3. Tema: colores y textos sin recompilar

[`assets/game/theme.json`](../assets/game/theme.json) controla la paleta y los
textos del juego. El juego lo carga al arrancar y lo fusiona encima de los
valores por defecto.

```json
{
  "paleta": { "azul": "#1F3A72", "rojo": "#E01D2D" },
  "textos": { "cartelTribuna": "CONCURSO TVS" }
}
```

Sirve para ajustar un color de marca o cambiar el cartel de la tribuna sin
tocar TypeScript ni tener Node en el servidor: se edita el archivo y se recarga.

Es tolerante a propósito. Un archivo incompleto, con una clave mal escrita o
que no se pueda descargar no deja el juego sin colores: lo que no se entienda
se ignora y se usa el valor por defecto. Quedarse sin jugar por un archivo de
colores sería absurdo.

El archivo fuente está en `game/public/theme.json` y `npm run build` lo copia a
`assets/game/`.

---

## 4. Sonido

No hay archivos de audio. El sonido se sintetiza en el navegador con la Web
Audio API, que es literalmente cómo sonaban las consolas de 8 y 16 bits:
[`sonido.ts`](../game/src/audio/sonido.ts).

Tres razones:

- **Cero bytes que descargar** en la ventana de 90 minutos, donde todo el
  tráfico del día llega junto.
- **Se acabó el problema de códecs.** Safari no reproduce `.ogg`, así que con
  archivos habría que servir cada pista dos veces, en ogg y en m4a.
- **El motor cambia de tono con la velocidad**, cosa que con un archivo grabado
  no se puede.

Lo que suena: el motor (continuo, sube de tono con la velocidad), la cuenta
regresiva, recoger un logo, saltar, aterrizar, caerse, el aviso de temperatura,
el sobrecalentamiento, el final de carrera y la fanfarria del podio.

**iOS no deja sonar nada fuera de un gesto del usuario.** El contexto de audio
se crea en el clic de "Iniciar carrera", que es el último gesto antes de la
carrera.

Se puede silenciar desde la consola con `navidadTvsSilenciar(true)`.

---

## 5. Composición de la pantalla

320×180 internos, escalados a un múltiplo entero. Las bandas, de arriba abajo:

| Banda | Franja | Parallax |
|---|---|---|
| Cielo | 0–82 | fijo |
| Nubes | 0–18 | 0,04 |
| Cerros y pinos | 18–46 | 0,14 |
| Tribuna, luces y cartel | 46–74 | 0,36 |
| Césped | 74–82 | 0,7 |
| Pista, 4 carriles de 16 px | 82–146 | 1,0 |
| Panel DIST / TEMP / TIME | 146–180 | fijo |

Las bandas no se pisan. En una versión anterior la tribuna arrancaba dentro de
los cerros y se comía los pinos enteros.

Los carriles son de 16 px porque la moto mide 20: con los 13 de la primera
versión ocupaba carril y medio y costaba saber en cuál iba. En el original la
moto y el carril miden casi lo mismo.

### Tamaño en pantalla

El lienzo se escala por un múltiplo **entero** de 320×180, calculado contra la
ventana y con tope en ×6:

| Pantalla | Factor | Tamaño |
|---|---|---|
| Monitor 1080p | ×5 | 1600×900 |
| Portátil 1366×768 | ×4 | 1280×720 |
| Tablet 1024×768 | ×3 | 960×540 |
| Teléfono en horizontal | ×2 | 640×360 |

**Subir la resolución interna no agranda nada: la encoge.** A 480×270 un
teléfono de 667 px solo daría para ×1, y el resultado sería más pequeño en
pantalla que los 640×360 de ahora. Lo que agranda es el factor de escala.

Por eso el panel del juego se sale del ancho máximo de lectura de la página
(720 px): con ese límite, en un monitor de 1920 el juego se quedaba clavado en
×2, o sea 640×360, y había que forzar la vista.

---

## 6. Cambio de carril

La moto se desliza entre carriles durante 7 cuadros y se inclina mientras lo
hace, como en el original.

**Es solo visual.** La simulación cambia de carril en un tick y así tiene que
seguir: si el cambio tardara en surtir efecto, alteraría las colisiones y
habría que replicar la interpolación exacta en el puerto PHP de E6. La moto se
desliza en pantalla mientras la física ya la considera en el carril nuevo.

Si al probar se siente que la animación "miente" —que uno se choca cuando
visualmente ya había salido— la salida es acortar la animación, no moverla a la
simulación.

---

## 7. Errores que ya se cometieron

Quedan anotados porque son fáciles de repetir:

- **Rellenar un obstáculo con el color del suelo.** La primera rampa usaba el
  color de la pista y era literalmente invisible sobre ella.
- **Pintar el piloto del color del fondo.** En el podio, el traje azul sobre
  fondo azul dejaba flotando el casco y las botas.
- **Bordear un sprite entero de blanco.** Los pinos con nieve en cada fila se
  leían como triángulos huecos, no como árboles.
- **Un sprite simétrico arriba y abajo.** La primera copa parecía un reloj de
  arena.
- **`Interpolate.ColorWithColor()` devuelve `{r,g,b,a}`,** no un color
  empaquetado. Pedirle `.color` da `undefined` y la banda sale negra.

Casi todos se detectan mirando `npm run arte:png`. Conviene hacerlo antes de
dar por bueno un cambio de arte.
