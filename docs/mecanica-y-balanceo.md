# Mecánica y balanceo — Concurso Navideño Auteco TVS

Especificación funcional del juego. Es la fuente de verdad para E4 (motor en
TypeScript) y E6 (reejecución en PHP). **Las dos implementaciones deben
producir el mismo resultado bit a bit**, así que cualquier cambio de número en
este documento se aplica en los dos lados y se corre la suite de paridad.

---

## 1. Regla del concurso

- Carrera de **90 segundos exactos**.
- Gana quien recorra **más metros**.
- El contador avanza de 1 en 1 metro.
- Los ítems con el logo TVS suman **+50 m** directos al contador.
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
| Acelerador normal | `Z` | Zona inferior derecha |
| Turbo | `X` | Zona superior derecha |
| Cambiar de carril | `↑` `↓` | Zona izquierda, arriba/abajo |
| Inclinación en el aire | `←` `→` | Zona izquierda, izquierda/derecha |

La moto nunca retrocede. Sin acelerador, desacelera por fricción.

Zonas táctiles de 48×48 px reales como mínimo. Orientación horizontal forzada.

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
| Desaceleración frenando | 20 000 mm/s² | 20 m/s² |

La velocidad se satura en el máximo correspondiente. Si se suelta el turbo
estando por encima de 22 000, decae por fricción hasta ese techo.

### Temperatura del motor

Es la mecánica que separa a los buenos jugadores. El turbo da +45 % de
velocidad punta, pero calienta.

| Estado | Cambio por tick | Tiempo de recorrido completo |
|---|---|---|
| Turbo activo | **+42** | 0 → 10 000 en ~4,0 s |
| Acelerador normal | **−28** | 10 000 → 0 en ~6,0 s |
| Sin acelerar o frenando | **−56** | 10 000 → 0 en ~3,0 s |

Al llegar a 10 000 el motor **se cala**:

- La moto pierde potencia: la velocidad decae a 40 000 mm/s² hasta detenerse.
- Dura **150 ticks (2,5 s)**.
- Durante el calado la temperatura baja a 0.
- Al terminar, se recupera el control con velocidad 0.

### Verificación del balanceo

| Estrategia | Velocidad media | Distancia en 90 s |
|---|---|---|
| Solo acelerador normal | 22 m/s | ~1980 m |
| Turbo continuo (se cala cada 6,5 s) | 21,2 m/s | ~1900 m — **peor que no usarlo** |
| Pulsos de 2 s turbo / 3 s normal (temperatura neutra) | 26 m/s | ~2340 m |
| Pulsos optimizados con enfriamiento al frenar | ~27 m/s | ~2430 m |

Abusar del turbo castiga. Dosificarlo premia. Es exactamente la curva que se
busca, y produce un rango de ~1200 m (jugador malo) a ~2700 m (experto), con
resolución de 1 metro: espacio de sobra para ordenar 150 participantes sin
empates masivos.

---

## 5. Pista

### Estructura

- **4 carriles**, cambio vertical como en Excitebike.
- La pista se genera en **segmentos de 100 m** encadenados.
- Cada segmento sale de una plantilla elegida con el PRNG.
- Dificultad creciente: los primeros 300 m son limpios para que el jugador se
  acomode; de ahí en adelante la probabilidad de plantillas difíciles sube.

### Obstáculos

| Obstáculo | Efecto | Recuperación |
|---|---|---|
| **Rampa** | Salto. En el aire se controla la inclinación | Aterrizaje limpio (±15°): sin penalización y pequeño impulso. Fuera de rango: caída |
| **Lodo** | Velocidad al 60 % mientras se está encima | Inmediata al salir |
| **Valla / bache** | Caída del piloto | 120 ticks (2 s) inmóvil |

La caída es la penalización más cara: 2 segundos parado a 22 m/s son 44 metros
perdidos, más el tiempo de volver a acelerar.

### Ítems TVS

- Aparecen en promedio **1 cada 200 m**, en carriles alternados para obligar a
  moverse.
- Valen **+50 m** cada uno, sumados directo al contador.
- Un jugador bueno recoge unos 8–12 por carrera (+400 a +600 m).
- Se renderizan como la letra del logo TVS, sin el caballo.

---

## 6. Flujo de la carrera

1. El participante ingresa y ve el **modal de instrucciones**: controles,
   jugabilidad, aviso de girar el dispositivo y advertencia de usar conexión
   estable porque el intento es único.
2. Cierra el modal y aparece el botón **Iniciar carrera**.
3. Al pulsarlo: el servidor emite `seed` y nonce, y arranca un **countdown
   3-2-1** en pantalla. El cronómetro no corre durante el countdown.
4. Carrera de 90 s. El panel inferior muestra `DIST`, `TEMP` y `TIME`.
5. Al terminar, el cliente envía el log de inputs. El servidor reejecuta,
   calcula la distancia y la persiste.
6. Pantalla final: piloto en podio, nombre y distancia recorrida debajo, y el
   mensaje de agradecimiento.

---

## 7. Log de inputs

Un byte por tick, con los botones como bits:

| Bit | Acción |
|---|---|
| 0 | Acelerador |
| 1 | Turbo |
| 2 | Carril arriba |
| 3 | Carril abajo |
| 4 | Inclinación adelante |
| 5 | Inclinación atrás |

5400 bytes por carrera, comprimidos con run-length y codificados en base64.
En la práctica queda por debajo de 1 KB, porque los estados de botón cambian
pocas veces por segundo.

---

## 8. Validaciones de plausibilidad

Segunda malla, por si algo se escapa de la reejecución:

- Distancia máxima teórica en 90 s: **3100 m**. Cualquier cosa por encima se
  rechaza sin más análisis.
- Número de ítems recogidos no puede superar los presentes en la pista de ese
  seed.
- La duración real de la sesión (entre la emisión del nonce y la llegada del
  score) debe estar entre 90 y 130 segundos.
- El log debe tener exactamente 5400 ticks al descomprimirse.

---

## 9. Pendientes de ajuste

Los números de este documento son el punto de partida. Se afinan en E4 con
pruebas reales de juego, sobre todo:

- Densidad de ítems y obstáculos, que es lo que más mueve el rango de
  distancias.
- Duración del calado por sobrecalentamiento (2,5 s puede resultar muy duro en
  móvil).
- Ventana de aterrizaje limpio (±15°), que define qué tan castigadas quedan las
  rampas en pantalla pequeña.
