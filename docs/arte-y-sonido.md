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

### La matriz de diseño: 24×24

**Las poses de la moto se dibujan en una matriz de 24 × 24 píxeles.** Un diseño
entregado en esa matriz entra sin remuestrear, y eso es lo único que garantiza
que no se distorsione: cada píxel dibujado es un píxel del juego.

| | |
|---|---|
| Matriz | **24 de ancho × 24 de alto** |
| Apoyo | Las ruedas tocan el borde **inferior**; las tres poses se alinean por abajo |
| Distancia entre ejes | **~14 px** entre los centros de las dos ruedas |
| Colores | Los seis del diseño: blanco, azul `#3F48CC`, plata `#C3C3C3`, rojo `#D02A16`, negro y el fondo |
| Fondo | Transparente, o un color plano que no aparezca en la moto |

Para exportar hay dos formas válidas:

- **A tamaño real**, un PNG de 24×24. Es la mejor: no hay nada que interpretar.
- **Ampliado por un múltiplo exacto** y con vecino más cercano, nunca con
  suavizado. A ×10 son 240×240. Sirve para dibujar cómodo, y el importador lo
  reduce sin perder nada porque la rejilla cae justa.

Lo que hay que evitar es ampliar por un factor no entero o guardar en un
formato que comprima con pérdida: ahí es donde aparecen los cientos de colores
intermedios y las líneas de un píxel se pierden.

**Por qué importa la distancia entre ejes.** Las dos primeras poses llegaron
dibujadas a escalas distintas y la de salto salía un 21 % más grande, así que
la moto crecía al despegar. El lienzo no sirve para comparar, porque cambia con
el giro; la distancia entre los centros de las ruedas sí.

### Traer un diseño desde un PNG

Cuando alguien entrega un diseño hecho en un editor de pixel art, no se
transcribe a ojo:

```bash
cd game && npm run arte:importar -- ruta/al/diseño.png MOTO
```

Lee la imagen píxel por píxel, empareja cada color con el más cercano de la
paleta e imprime el bloque listo para pegar en `sprites.ts`. Así el sprite
queda exactamente como el diseño, no como una interpretación de él.

Avisa de dos cosas que arruinan un sprite: que la imagen traiga demasiados
colores (señal de que se dibujó grande y se redujo, y los bordes quedaron con
antialias) y que algún color no tenga equivalente cercano en la paleta, lo que
obliga a decidir si se agrega al tema.

**Qué entregar:** PNG a tamaño real, sin antialias, con colores planos y fondo
transparente. Exportado como RGB o RGBA de 8 bits, que es lo que sale por
defecto de cualquier editor de pixel art.

**Si el diseño llega ampliado y comprimido**, que es lo habitual, hay tres
opciones que lo resuelven:

| Opción | Para qué |
|---|---|
| `--tamano 24x23` | Tamaño del sprite de salida |
| `--fondo auto` | Detecta el color de lienzo y lo vuelve transparente |
| `--offset 6,2` | Dónde empieza la rejilla, desde el origen de la imagen |
| `--mapa "ffffff=w,3f48cc=B,..."` | Limita el emparejado a los colores con que se dibujó |

Lo que más cuesta acertar es la rejilla. Si se desplaza medio píxel, las líneas
de un solo píxel —los radios de una rueda, la horquilla— caen entre dos celdas
y el voto mayoritario las borra. La forma de encontrarla es buscar el encaje
que deja las celdas más uniformes: la rejilla correcta da celdas de un solo
color, porque cada celda es un píxel del dibujo original.

Para el diseño de la moto, ese encaje resultó ser 24×23 celdas de 10,00 px con
origen en (6, 2), con un 98,3 % de uniformidad.

El `--mapa` importa más de lo que parece. Sin él, un borde entre el naranja del
lienzo y el negro de una rueda produce un marrón intermedio que la paleta
empareja con "pista" o "piel", y el sprite aparece salpicado de colores que
nadie dibujó.

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

## 6. Avisos y rótulos flotantes

**El aviso del motor** va en letras rojas sobre una caja negra, y parpadea. La
caja hace falta: el rojo solo no se lee ni sobre el verde del césped ni sobre
la pista naranja.

Los dos avisos parpadean, pero a distinta velocidad. El de motor parado, al
doble: cuando ya no se puede hacer nada, el aviso tiene que verse más urgente
que cuando todavía hay tiempo de soltar el turbo.

**Al recoger un bonus** sube un `+50` flotando desde la moto, como las monedas
del Mario: sube catorce píxeles en tres cuartos de segundo y se desvanece en la
segunda mitad del recorrido. Hay cuatro rótulos que se reciclan, de sobra
porque los bonus están a unos 200 m unos de otros.

El texto sale de `theme.json`, así que cambiar el `+50` por otra cosa no obliga
a recompilar.

---

## 7. El salto

La moto sube a la rampa girada, **se mantiene girada todo el vuelo** y vuelve a
la posición normal al tocar el suelo. La pose de salto se muestra tal como se
dibujó, sin girarla más.

No hay control de inclinación en el aire. Hubo una versión con ángulo de
aterrizaje —presionar arriba y abajo para enderezar antes de caer— y se quitó
al fijar el dibujo durante el vuelo: si el ángulo no se puede ver, castigar por
él produce caídas que el jugador no entiende, y con un solo intento eso no se
puede permitir.

El salto queda entonces como una oportunidad, no como un riesgo: se pasa por
encima de lo que venga y se cae de pie con un pequeño impulso.

---

## 8. Cambio de carril

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

## 9. Dos diseños tienen que estar a la misma escala

Cuando llegan varias poses del mismo objeto dibujadas por separado, casi nunca
vienen al mismo tamaño. Es lo que pasó con la moto: la pose de salto, a su
rejilla natural de 28×31, tenía una distancia entre ejes de **16,6 píxeles**
frente a los **13,7** de la pose de rodar. Un 21 % más grande, y la moto crecía
en pleno salto.

La forma de comprobarlo no es mirar el lienzo, que cambia con el giro, sino
medir algo invariante: la distancia entre los centros de las dos ruedas. Se
localizan con un relleno por inundación sobre los píxeles negros y se toman los
dos grupos más grandes.

La corrección fue importar el salto a 23×26 en vez de a su rejilla natural, con
lo que la distancia queda en 13,9. Se pierde algo de detalle al remuestrear,
pero mucho menos de lo que molesta una moto que cambia de tamaño.

Lo mismo con el **ángulo**: el diseño del salto parecía de 45° a ojo y resultó
ser de **35,6°** al medirlo entre los ejes. Ese número importa porque la escena
resta el giro dibujado antes de aplicar el de la física; con 45 la moto salía
del salto ya inclinada hacia abajo.

---

## 10. Errores que ya se cometieron

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
- **Estimar un ángulo a ojo.** El salto parecía de 45° y era de 35,6°. Medirlo
  cuesta un minuto y evita que la moto salga del salto ya cabeceando.
- **Dar por hecho que dos diseños vienen a la misma escala.** No vienen.
- **Quitar un color de fondo en toda la imagen.** El bonus TVS es una placa
  blanca sobre lienzo blanco: borrar "el blanco" se llevaba también el interior
  de la placa. El importador inunda desde el borde, así distingue el lienzo del
  blanco encerrado por el contorno.

Casi todos se detectan mirando `npm run arte:png`. Conviene hacerlo antes de
dar por bueno un cambio de arte.
