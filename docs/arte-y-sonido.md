# Arte y sonido del juego

Cómo está hecho lo que se ve y se oye, y cómo cambiarlo.

---

## 1. El pixel art se escribe, no se dibuja

Todos los sprites viven en [`game/src/render/sprites.ts`](../game/src/render/sprites.ts)
como arreglos de texto. Cada carácter es un color:

```ts
export const CONO: Sprite = [
  '....OO....',
  '...OOOO...',
  '...TTTT...',
  ...
];
```

La correspondencia carácter → color está en `LEYENDA`, dentro de
[`pixeles.ts`](../game/src/render/pixeles.ts): `w` blanco, `n` negro, `O`
naranja del cono, `.` transparente, y así. Los colores del escenario usan
letras que los recuerdan; los de los diseños del cliente van agrupados por
elemento, porque si no serían media docena de grises y naranjas
indistinguibles.

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
de la leyenda y que se respeten los tamaños de los que depende el juego: la
moto y su caída tienen que medir igual o la moto daría un salto al cambiar de
textura, y nada de lo que va sobre la pista puede pasar de los 16 px que mide
un carril.

### La matriz de diseño: 24×24

**Todo se dibuja en una matriz de 24 × 24 píxeles**: la moto, el cono, la
llave, el impulsor. Un diseño entregado en esa matriz entra sin remuestrear, y
eso es lo único que garantiza que no se distorsione: cada píxel dibujado es un
píxel del juego.

| | |
|---|---|
| Matriz | **24 de ancho × 24 de alto**, también para los objetos pequeños |
| Apoyo de la moto | Las ruedas tocan el borde **inferior**; las poses se alinean por abajo |
| Distancia entre ejes | **~14 px** entre los centros de las dos ruedas |
| Alto útil de lo que va en la pista | **16 px como máximo**, que es lo que mide un carril |
| Colores | Pocos y planos. Los que no estén en la paleta se agregan al tema |
| Fondo | Transparente, o un color plano que no aparezca en el dibujo |

Un objeto pequeño no tiene que llenar la matriz: se dibuja centrado y se
importa con `--recortar`, que quita las filas y columnas de transparente que lo
rodean sin tocar un solo píxel del dibujo. Así el cono llega como 10×14 y la
llave como 18×10, y los dos caben en un carril.

Para exportar hay dos formas válidas:

- **A tamaño real**, un PNG de 24×24. Es la mejor: no hay nada que interpretar.
- **Ampliado por un múltiplo exacto** y con vecino más cercano, nunca con
  suavizado. A ×10 son 240×240. Sirve para dibujar cómodo, y el importador lo
  reduce sin perder nada porque la rejilla cae justa.

Lo que hay que evitar es ampliar por un factor no entero o guardar en un
formato que comprima con pérdida: ahí es donde aparecen los cientos de colores
intermedios y las líneas de un píxel se pierden.

**Por qué importa la distancia entre ejes.** Cuando hubo dos poses de moto,
llegaron dibujadas a escalas distintas y una salía un 21 % más grande, así que
la moto crecía al cambiar de pose. El lienzo no sirve para comparar, porque
cambia con la postura; la distancia entre los centros de las ruedas sí.

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
| `--tamano 24x24` | Tamaño del sprite de salida |
| `--fondo auto` | Detecta el color de lienzo y lo vuelve transparente |
| `--offset -4,-4` | Dónde empieza la rejilla, desde el origen de la imagen |
| `--mapa "ffffff=.,ff6f00=O,..."` | Limita el emparejado a los colores con que se dibujó |
| `--recortar` | Quita el marco de transparente que rodea al dibujo |

Lo que más cuesta acertar es la rejilla. Si se desplaza medio píxel, las líneas
de un solo píxel —los radios de una rueda, la horquilla— caen entre dos celdas
y el voto mayoritario las borra. La forma de encontrarla es buscar el encaje
que deja las celdas más uniformes: la rejilla correcta da celdas de un solo
color, porque cada celda es un píxel del dibujo original.

Encontrarla no se hace a ojo:

```bash
cd game && npm run arte:rejilla -- ruta/al/diseño.png
```

prueba todos los tamaños y desplazamientos posibles, puntúa cada uno por lo
uniformes que quedan las celdas e imprime el comando de importación ya armado.
Los cuatro diseños actuales dan **24×24 con origen en (−4, −4) y un 100 % de
uniformidad**, que es lo que se consigue cuando el diseño se dibuja
directamente en la matriz del proyecto.

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
  "textos": { "cartelTribuna": "NAVIDAD TVS" }
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
regresiva, recoger una llave, pisar un impulsor, caerse, el aviso de temperatura,
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
el gris del asfalto.

Los dos avisos parpadean, pero a distinta velocidad. El de motor parado, al
doble: cuando ya no se puede hacer nada, el aviso tiene que verse más urgente
que cuando todavía hay tiempo de soltar el turbo.

**Al recoger una llave** sube un `+50` flotando desde la moto, como las monedas
del Mario: sube catorce píxeles en tres cuartos de segundo y se desvanece en la
segunda mitad del recorrido. Los impulsores usan el mismo rótulo con un `+1`.
Hay cuatro que se reciclan, y si dos coinciden en el mismo tick el segundo sale
una línea más arriba para que se lean los dos.

El texto sale de `theme.json`, así que cambiar el `+50` por otra cosa no obliga
a recompilar.

---

## 7. Los impulsores sustituyeron al salto

Hubo rampas y vuelo. Ya no: las rampas son ahora **impulsores**, placas verdes
pintadas en el asfalto que dan un empujón de 4 m/s y suman un metro. La moto no
despega del suelo en ningún momento, así que desaparecieron la pose de salto,
la sombra, la altura y la gravedad.

El cambio vino del cliente y fue estético, pero se cuidó que el balanceo no se
moviera: los 4 m/s del impulsor valen casi lo mismo que valían el impulso de
aterrizaje de la rampa más los dos segundos de inmunidad que daba ir por el
aire. Las marcas de las estrategias automáticas se movieron menos de diez
metros.

Lo que sí cambió es que **ya no hay forma de saltarse un obstáculo**: todo se
esquiva cambiando de carril. Por eso el mínimo de 30 m entre conos, que antes
era holgado, ahora es la única red de seguridad que tiene el jugador.

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
vienen al mismo tamaño. Pasó con la moto: una pose, a su rejilla natural de
28×31, tenía una distancia entre ejes de **16,6 píxeles** frente a los **13,7**
de la otra. Un 21 % más grande, y la moto crecía al cambiar de pose.

La forma de comprobarlo no es mirar el lienzo, que cambia con la postura, sino
medir algo invariante: la distancia entre los centros de las dos ruedas. Se
localizan con un relleno por inundación sobre los píxeles negros y se toman los
dos grupos más grandes.

Desde que el cliente dibuja directamente en la matriz de 24×24 el problema no
ha vuelto a aparecer, porque las dos poses comparten lienzo. Queda anotado por
si algún día llega un diseño fuera de la matriz.

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
- **Estimar a ojo lo que se puede medir.** Una pose de salto parecía de 45° y
  era de 35,6°. Medirlo cuesta un minuto; interpretarlo, un rediseño.
- **Dar por hecho que dos diseños vienen a la misma escala.** No vienen.
- **Quitar un color de fondo en toda la imagen.** El bonus TVS es una placa
  blanca sobre lienzo blanco: borrar "el blanco" se llevaba también el interior
  de la placa. El importador inunda desde el borde, así distingue el lienzo del
  blanco encerrado por el contorno.

Casi todos se detectan mirando `npm run arte:png`. Conviene hacerlo antes de
dar por bueno un cambio de arte.
