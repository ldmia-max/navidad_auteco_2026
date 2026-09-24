# Mecánica y balanceo — Concurso Navideño Auteco TVS

Especificación funcional del juego. Es la fuente de verdad para E4 (motor en
TypeScript) y E6 (reejecución en PHP). **Las dos implementaciones deben
producir el mismo resultado bit a bit**, así que cualquier cambio de número en
este documento se aplica en los dos lados y se corre la suite de paridad.

---

## 1. Regla del concurso

- Carrera de **90 segundos exactos**.
- Gana quien recorra **más metros**.
- El techo de velocidad **sube cada 20 segundos**: la carrera empieza
  manejable y termina exigiendo.
- El contador avanza de 1 en 1 metro.
- Las **llaves** suman **+50 m** directos al contador.
- Cada **impulsor pisado suma +1 m**, con el mismo rótulo flotante que la
  llave. Es un premio simbólico —unos 19 m en una carrera buena— que sirve
  para que el impulsor se sienta recompensado, no para decidir el ranking.
- Un solo intento por participante. No hay reintentos.
- Los **4 mayores del día** son ganadores. Si hay empate, todos los empatados
  reciben premio.

---

## 2. Determinismo

El score no se calcula en el navegador. El cliente envía el log de inputs y el
servidor reejecuta la carrera. Para que ambos coincidan:

| Regla | Motivo |
|---|---|
| **Nada de flotantes.** Toda la física en enteros | `0.1 + 0.2` no da lo mismo en JS que en PHP en todos los casos, y el error se acumula en 5400 ticks |
| División siempre truncada: `Math.trunc(a / b)` en TS, `intdiv($a, $b)` en PHP | Ambas truncan hacia cero; el operador `/` de JS no |
| Nada de `Math.random()` ni `rand()` | La aleatoriedad viene del PRNG sembrado |
| Exactamente **5400 ticks** por carrera (90 s × 60) | Un tick de diferencia cambia el resultado |
| El render no toca el estado de la simulación | Un equipo a 144 Hz debe recorrer lo mismo que uno a 30 fps |

### Unidades internas

| Magnitud | Unidad interna | Ejemplo |
|---|---|---|
| Posición | milímetros (mm) | 2 400 000 mm = 2400 m |
| Velocidad | mm por segundo | 22 000 = 22 m/s |
| Aceleración | mm/s por segundo | 12 000 = 12 m/s² |
| Temperatura | centésimas de grado arbitrario, 0–10 000 | 10 000 = sobrecalentado |
| Tick | 1/60 s | 5400 ticks = 90 s |

Por tick: `posicion += intdiv(velocidad, 60)` y
`velocidad += intdiv(aceleracion, 60)`.

Los valores caben de sobra en enteros de 53 bits, que es el rango exacto de un
`number` de JavaScript, así que no hay pérdida de precisión en ninguno de los
dos lenguajes.

### PRNG

`mulberry32` sembrado con el `seed` que emite el servidor. Aritmética sin signo
de 32 bits: en TypeScript se cierra cada operación con `>>> 0`, en PHP con
`& 0xFFFFFFFF`. Devuelve enteros, nunca un flotante entre 0 y 1; para un rango
se usa el módulo.

---

## 3. Controles

| Acción | Teclado | Táctil |
|---|---|---|
| Acelerador normal | `Z` | Mitad derecha, abajo |
| Turbo | `X` | Mitad derecha, arriba |
| Cambiar de carril | `↑` `↓` | Mitad izquierda, arriba/abajo |

No hay botón de freno, igual que en el original: soltar el acelerador frena por
fricción y además enfría el motor al doble de velocidad. La moto nunca
retrocede.

Arriba y abajo valen para dos cosas según dónde esté la moto. Eso deja el mando
en dos botones y una cruz, que es lo que cabe cómodo en la pantalla de un
celular; las cuatro zonas son cuadrantes de media pantalla, muy por encima del
mínimo de 48×48 px.

Orientación horizontal forzada.

---

## 4. Física de la moto

### Velocidad

| Parámetro | Valor | Equivalente |
|---|---|---|
| Velocidad máxima con acelerador normal | 22 000 mm/s | 22 m/s ≈ 79 km/h |
| Velocidad máxima con turbo | 32 000 mm/s | 32 m/s ≈ 115 km/h |
| Aceleración normal | 12 000 mm/s² | 12 m/s² |
| Aceleración con turbo | 18 000 mm/s² | 18 m/s² |
| Desaceleración por fricción (sin acelerar) | 8 000 mm/s² | 8 m/s² |
| Pérdida de potencia con el motor sobrecalentado | 40 000 mm/s² | 40 m/s² |

La velocidad se satura en el máximo correspondiente. Si se suelta el turbo
estando por encima de 22 000, decae por fricción hasta ese techo.

### Temperatura del motor

Es la mecánica que separa a los buenos jugadores. El turbo da +45 % de
velocidad punta, pero calienta.

| Estado | Cambio por tick | Tiempo de recorrido completo |
|---|---|---|
| Turbo activo | **+42** | 0 → 10 000 en ~4,0 s |
| Acelerador normal | **−28** | 10 000 → 0 en ~6,0 s |
| Sin acelerar | **−56** | 10 000 → 0 en ~3,0 s |

Al llegar a 10 000 el motor **se sobrecalienta** y la moto se detiene:

- La moto pierde potencia: la velocidad decae a 40 000 mm/s² hasta detenerse.
- Dura **150 ticks (2,5 s)**.
- Durante la parada la temperatura baja a 0.
- Al terminar, se recupera el control con velocidad 0.

### Escalada de velocidad

Cada 20 segundos sube el **techo** de velocidad, el del acelerador y el del
turbo por igual, en 3 m/s. A los 60 s ya está arriba del todo.

| Tramo | Acelerador | Turbo |
|---|---|---|
| 0–20 s | 22 m/s · 79 km/h | 32 m/s · 115 km/h |
| 20–40 s | 25 m/s · 90 km/h | 35 m/s · 126 km/h |
| 40–60 s | 28 m/s · 101 km/h | 38 m/s · 137 km/h |
| 60–90 s | 31 m/s · 112 km/h | 41 m/s · 148 km/h |

Al final de la carrera, ir con el acelerador pelado cuesta lo que al principio
costaba ir con turbo. Solo depende del tick, así que dos participantes en el
mismo segundo tienen exactamente el mismo techo y el servidor lo reejecuta sin
arrastrar nada.

**Sube el techo, no un piso de velocidad**, y la diferencia no es cosmética.
Con un piso que empujara a la moto, soltar el acelerador dejaría de costar
velocidad, enfriar el motor saldría gratis y abusar del turbo pasaría a ser la
mejor jugada: se caería el equilibrio sobre el que está montado el concurso.
Además regalaría los mismos metros a todo el mundo, incluido quien no toca
nada, y con 150 participantes peleando por 4 puestos lo que hace falta es
separación, no un suelo común.

#### Lo que de verdad limita la velocidad es la cámara

El jugador ve **30,75 m de pista por delante** (`VISTA_MM`, en medidas.ts). Ese
es todo su presupuesto de reacción: cuanto más rápido va, menos tiempo pasa
entre que un cono asoma por el borde y le llega encima.

| | Antes | Con la escalada |
|---|---|---|
| Tiempo para ver un cono y esquivarlo, a tope de turbo | 0,96 s | **0,75 s** |

Ese recorte *es* la dificultad que se buscaba, y 0,75 s sigue estando por
encima de lo que cuesta reaccionar (unos 0,3 s) más un cambio de carril (8
ticks, 0,13 s). El banco de pruebas lo mide simulando una carrera entera a tope
de turbo y anotando el peor caso; si bajara de **0,70 s**, falla.

Por eso la separación mínima entre conos crece con la pista, de 30 a 42 m: lo
que se protege no son los metros sino los segundos, y 42 m a 41 m/s vuelven a
ser el segundo largo que había al principio a 32 m/s.

### Verificación del balanceo

Medido con `cd game && npm run sim`, promediando ocho pistas distintas. Las
estrategias son automáticas y **no cambian de carril**, así que recogen pocos
logos y chocan con lo que les toca de frente: son el suelo, no el techo.

| Estrategia | Distancia media | Impulsores | Caídas | Veces que se sobrecalienta |
|---|---|---|---|---|
| Sin tocar nada | 0 m | 0,0 | 0,0 | 0,0 |
| Solo acelerador | **2133 m** | 19,4 | 4,6 | 0,0 |
| Turbo continuo | **1696 m** | 15,3 | 2,8 | 12,9 |
| Pulsos de 2 s turbo / 3 s normal | **2352 m** | 21,3 | 5,5 | 0,4 |
| Pulsos de 2 s turbo / 2 s suelto | 2276 m | 20,5 | 5,0 | 0,0 |
| Pulsos de 1 s turbo / 2 s normal | 2383 m | 21,1 | 5,6 | 0,0 |

Abusar del turbo castiga de verdad: 1696 m frente a los 2133 m de no usarlo.
Dosificarlo sube a 2383 m. Son 250 metros de diferencia entre jugar mal y jugar
bien, sin contar las llaves ni los carriles, y con resolución de 1 metro:
espacio de sobra para ordenar 150 participantes.

La escalada subió todas las marcas unos 250 m y las caídas de 4 a 5 por
carrera, **sin tocar el orden entre estrategias**, que es lo único que el
concurso necesita que se mantenga. Lo mismo había pasado al cambiar las rampas
por impulsores: los 4 m/s del impulsor valen casi lo mismo que valía el impulso
de aterrizaje más los dos segundos de inmunidad por el aire.

Una carrera completa se simula en **0,14 ms**. Validar las 150 de una jornada
cuesta centésimas de segundo, así que en E6 se puede reejecutar el 100 % de las
partidas y no solo las de los finalistas.

---

## 5. Pista

### Estructura

- **4 carriles**, cambio vertical como en Excitebike, con 8 ticks de espera
  entre un cambio y el siguiente.
- Se generan 3600 m de pista, por encima del tope de plausibilidad, para que
  nadie se quede sin pista ni en una carrera perfecta.
- Los obstáculos se colocan recorriendo la pista con huecos de 11 a 26 m
  sorteados con el PRNG. Los primeros **30 m van limpios**: con el primer hueco
  encima, el primer obstáculo aparece entre el segundo 3 y el 4.
- **Nunca se bloquean los cuatro carriles a la vez**: un grupo ocupa uno o dos
  como mucho. Si el jugador no tuviera salida, la caída no mediría habilidad y
  el concurso sería impugnable.
- **Entre dos conos hay siempre 30 m como mínimo.** La densidad de la pista y
  su dificultad se regulan por separado: el impulsor premia y el aceite solo
  frena, así que la pista puede ir llena sin ser injusta. El único que tumba es
  el cono, y ahí sí hace falta margen de reacción: 30 m son casi un segundo
  yendo a tope de turbo. Cuando el sorteo pide un cono demasiado pronto, sale
  un impulsor en su lugar.
- La dificultad sube con la distancia: la probabilidad de que un obstáculo sea
  un cono pasa del 10 % al 35 % entre el principio y el final.

### Obstáculos

| Obstáculo | Efecto | Recuperación |
|---|---|---|
| **Impulsor** | Placa en el suelo: **+4 m/s** de golpe y **+1 m** al contador | — |
| **Charco de aceite** | Velocidad al 60 % mientras se está encima | Inmediata al salir |
| **Cono de vía** | Caída del piloto | 120 ticks (2 s) inmóvil |

El impulsor puede dejar la moto por encima del techo del acelerador normal
(22 m/s). Es deliberado: la fricción se lo va comiendo, así que se gana un
tramo rápido y no una ventaja permanente. El tope sigue siendo el del turbo
(32 m/s), para que encadenar impulsores no dispare la velocidad.

**Ya no hay saltos.** Los impulsores sustituyeron a las rampas y la moto no
despega del suelo en ningún momento: desaparecieron la altura, la gravedad y
la inmunidad que daba ir por el aire.

La caída es la penalización más cara: 2 segundos parado a 22 m/s son 44 metros
perdidos, más el tiempo de volver a acelerar.

### Llaves

- Aparecen en promedio **1 cada 200 m**, en carriles alternados para obligar a
  moverse.
- Valen **+50 m** cada una, sumadas directo al contador.
- Se dibujan con el diseño de llave que mandó el cliente.

**Recogerlas depende de cambiar de carril.** Las pruebas automáticas, que se
quedan siempre en el mismo carril, recogen 2 o 3 por carrera. Sobre una pista de
3600 m hay unas 17 llaves, de las que unas 11 quedan dentro del alcance de una
carrera buena. Quien se mueva bien puede llevarse la mayoría: ahí hay unos 400
metros de diferencia que separan a quien solo acelera de quien además conduce.

---

## 6. Flujo de la carrera

1. El participante ingresa y ve el **modal de instrucciones**: controles,
   jugabilidad, aviso de girar el dispositivo y advertencia de usar conexión
   estable porque el intento es único.
2. Cierra el modal y aparece el botón **Iniciar carrera**.
3. Al pulsarlo: el servidor emite `seed` y nonce, y arranca un **countdown
   3-2-1** en pantalla. El cronómetro no corre durante el countdown.
4. Carrera de 90 s. El panel inferior muestra `DIST`, `TEMP` y `TIME`.
5. Al agotarse el tiempo la pantalla se congela **1,2 s**: el reloj queda en
   `0:00` y la moto queda en su pose normal, aunque el tiempo se acabara con
   el piloto tumbado. Sin esa pausa el último valor legible del reloj era
   `0:01` y el fotograma de `0:00` pasaba de largo.
6. El cliente envía el log de inputs. El servidor reejecuta, calcula la
   distancia y la persiste.
7. Pantalla final: piloto en podio, nombre y distancia recorrida debajo, y el
   mensaje de agradecimiento.

---

## 7. Log de inputs

Un byte por tick, con los botones como bits:

| Bit | Acción |
|---|---|
| 0 | Acelerador |
| 1 | Turbo |
| 2 | Arriba |
| 3 | Abajo |
| 4–7 | Reservados |

El registro guarda **lo que el jugador pulsó**, no lo que eso significaba en
ese instante; interpretarlo es trabajo de la simulación. Así el mismo registro
sirve para reejecutar la carrera sin arrastrar contexto.

5400 bytes por carrera, comprimidos por repeticiones y codificados en base64. En
la práctica quedan en unos **160 bytes**, porque los botones cambian pocas veces
por segundo, no sesenta.

---

## 8. Validaciones de plausibilidad

Segunda malla, por si algo se escapa de la reejecución:

- Distancia máxima teórica en 90 s: **4200 m**. Cualquier cosa por encima se
  rechaza sin más análisis. Va deliberadamente alto: el filtro de verdad es
  reejecutar la carrera, y esta primera malla solo tiene que atrapar lo absurdo
  sin poder rechazar jamás algo que la física permita. Sale del techo físico
  —unos 3300 m de recorrido yendo a tope de turbo los noventa segundos, que el
  sobrecalentamiento hace imposible— más todas las llaves del camino.
- Número de ítems recogidos no puede superar los presentes en la pista de ese
  seed.
- La duración real de la sesión (entre la emisión del nonce y la llegada del
  score) debe estar entre 90 y 130 segundos.
- El log debe tener exactamente 5400 ticks al descomprimirse.

---

## 9. Pendientes de ajuste

Los números de este documento son el punto de partida. Se afinan en E4 con
pruebas reales de juego, sobre todo:

- Densidad de ítems, que es lo que más mueve el rango de distancias. La de
  obstáculos ya se subió una vez (huecos de 18–45 m a 11–26 m) a petición del
  cliente.
- Duración de la parada por sobrecalentamiento (2,5 s puede resultar muy duro en
  móvil).
- Cuánto sube el techo en cada escalón (3 m/s) y cada cuánto (20 s).
- Separación mínima entre conos (30 a 42 m): es el mando de dificultad real ahora
  que la densidad general y el castigo van por separado.
