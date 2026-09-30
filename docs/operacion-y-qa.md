# Operación y QA

Lo que hay que dejar hecho en el servidor antes de abrir, y lo que hay que
probar a mano en dispositivos reales.

El plugin trae lo que le corresponde a un plugin. Lo de este documento es lo
otro: configuración del servidor y pruebas con un teléfono en la mano. Ninguna
de las dos cosas se puede resolver desde el código.

---

## 1. Lo que ya viene puesto

No hay que hacer nada con esto; se lista para saber qué está cubierto.

| Qué | Dónde |
|---|---|
| `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` en las páginas del concurso | `includes/class-seguridad.php` |
| `Cache-Control: no-store` en los endpoints del concurso | igual |
| Límite de intentos por IP y por teléfono | `includes/class-rate-limit.php` |
| Ventana horaria y congelamiento, validados con hora del servidor | `navidad-tvs.php`, `includes/class-acceso.php` |
| Un solo intento, garantizado por índice único en la base | `includes/class-database.php` |
| La distancia la calcula el servidor reejecutando el log | `includes/class-validador.php` |
| Malla de plausibilidad por duración y por tope de metros | igual |

Las cabeceras se ponen **solo en las páginas del concurso**, no en todo el
sitio. Un plugin no tiene por qué cambiarle las cabeceras al blog.

---

## 2. Lo que hay que hacer en el servidor

### 2.1 Comprimir el JavaScript del juego — pendiente

El bundle pesa **1,48 MB sin comprimir y unos 360 KB con gzip**. Hoy el VPS
comprime el CSS y el JSON pero no el JavaScript, así que cada participante se
baja un megabyte de más. En una conexión móvil mala eso son varios segundos
antes de poder jugar.

Falta `text/javascript` en la lista de tipos de `mod_deflate`:

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/javascript application/javascript
</IfModule>
```

Para comprobarlo:

```bash
curl -s -I -H 'Accept-Encoding: gzip' \
  https://juegoauteco.losdemarketing.com/wp-content/plugins/navidad-tvs/assets/game/juego.js \
  | grep -i content-encoding
```

Tiene que responder `content-encoding: gzip`.

### 2.2 Dejar el servidor caliente antes de las 12:00

**Esto es lo que más se va a notar el primer día.** Medido con
`dev/prueba-carga.php`:

| | 50 peticiones simultáneas | Mediana |
|---|---|---|
| Servidor recién despertado | 6,0 s | 5,0 s |
| El mismo servidor, ya en marcha | 0,12 s | 0,09 s |

No es el plugin: una petición suelta tarda 40 ms, de los cuales 13 son
nuestros. Lo que se paga es que Apache va apagando procesos cuando no hay
tráfico, y la ventana abre a las 12:00 en punto sobre un servidor que lleva
horas quieto. **La primera oleada de participantes es justo la que se come esos
cinco segundos.**

Dos formas de evitarlo, y conviene hacer las dos:

1. Un cron que pida el endpoint de estado cada dos minutos desde las 11:40:

   ```cron
   */2 11-13 * * 1-6 curl -s -o /dev/null https://juegoauteco.losdemarketing.com/wp-json/concurso/v1/estado
   ```

2. Subir los procesos que Apache mantiene arrancados:

   ```apache
   StartServers        8
   MinSpareServers     8
   MaxSpareServers    20
   ```

### 2.3 HSTS

Va en el servidor y no en el plugin: solo tiene sentido si **todo** el dominio
va por HTTPS, y emitirla desde PHP en un sitio que aún sirva HTTP deja a los
visitantes sin poder entrar mientras dure la caché.

```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 2.4 Content-Security-Policy

Es la cabecera más útil de todas y también la que más rompe. El tema y
Elementor inyectan estilos y scripts en línea, así que una CSP escrita a ciegas
deja la página en blanco. Hay que medirla contra el sitio real.

El camino seguro es empezar en modo informe, mirar qué se queja durante unos
días y solo entonces aplicarla:

```apache
Header always set Content-Security-Policy-Report-Only "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com"
```

**Ojo con una cosa**: el juego se descarga con XHR y se inyecta como script en
línea, para poder enseñar la barra de progreso. Con una CSP sin
`'unsafe-inline'` eso se bloquea. No se rompe nada —`acceso.js` detecta que la
función del juego no quedó definida y vuelve a la etiqueta `<script src>`—
pero se pierde la barra de progreso. Si se aplica una CSP estricta, conviene
saber que ese es el precio.

### 2.5 Endurecimiento de WordPress

Lo de siempre, que no es del plugin pero sí de la instalación:

- `define( 'DISALLOW_FILE_EDIT', true );` en `wp-config.php`.
- Desactivar XML-RPC si nada lo usa.
- Usuarios administradores con contraseña larga y doble factor.
- Mantener WordPress, el tema y Elementor al día.
- Copia de la base **antes** de cada jornada. Los resultados no se pueden
  reconstruir: el log de entradas vive en la tabla de scores.

### 2.6 Claves de Turnstile

Siguen vacías. Con la clave secreta en blanco, la comprobación **se salta
entera** (`includes/class-acceso.php`). Funciona, pero deja la puerta abierta a
un script que pruebe teléfonos. Ponerlas en Ajustes → Concurso TVS.

---

## 3. QA en dispositivos reales

**Esto no lo puede hacer nadie desde el código y no está hecho.** Hace falta
un teléfono en la mano, y de más de un tipo.

Lo que sí está comprobado por otra vía: la lógica, la concurrencia, la
reejecución de las carreras y el ciclo completo con el padrón real
anonimizado, con los scripts de `dev/`.

### Cómo llenar esto

Una fila por dispositivo. Anotar lo que se vea, no lo que debería verse.

| # | Qué probar | Cómo saber que está bien |
|---|---|---|
| 1 | Cargar la página del juego | Entra sin errores, se ve la portada completa |
| 2 | Pantalla de acceso | Los dos campos se ven enteros, el teclado del teléfono no tapa el botón |
| 3 | Teclado numérico | Al tocar el celular sale el teclado de números, no el de letras |
| 4 | Pantalla de ayuda | Cabe sin scroll, o con un scroll corto. La tabla no se sale a lo ancho |
| 5 | Cuenta atrás del botón | Va de 10 a 0, el botón está rojo y bloqueado, al llegar a 0 se pone verde y responde |
| 6 | Barra de descarga | Se ve avanzar mientras baja el juego, con su porcentaje |
| 7 | Tamaño del lienzo | El juego se ve lo más grande posible sin que el mando quede fuera de pantalla |
| 8 | Acelerador | Responde al primer toque, sin retraso perceptible |
| 9 | Turbo | Sube la temperatura, y al soltarlo baja |
| 10 | Cambio de carril | Los dos botones responden, la moto cambia de carril |
| 11 | Jugar a ciegas | Se pueden pulsar los botones sin mirarlos, mirando la pista |
| 12 | Fluidez | La moto no da tirones. Anotar si se siente a menos de 30 imágenes por segundo |
| 13 | Carrera completa | Los 90 segundos enteros sin que se caiga ni se congele |
| 14 | Girar el teléfono a la mitad | El juego sigue jugable; anotar qué pasa |
| 15 | Llamada entrante o bloquear la pantalla | Al volver, el juego sigue o avisa; anotar qué pasa |
| 16 | Resultado | Sale la distancia y el aviso verde de registrado |
| 17 | Sin conexión al terminar | Poner el teléfono en modo avión al acabar: tiene que salir el botón de reintentar, y al volver la conexión debe enviarse |
| 18 | Segundo intento | Con el mismo número, no deja entrar y lo dice claro |

### Dispositivos mínimos

| Dispositivo | Por qué |
|---|---|
| Android de gama baja | Es el piso: si ahí va a 30 imágenes por segundo, va en todo |
| Android de gama media | Es lo que va a usar la mayoría |
| iPhone con Safari | Safari se comporta distinto con el audio y con la vibración |
| Tableta | Ancho intermedio: ni la vista de teléfono ni la de escritorio |
| Escritorio con teclado | La vista de ayuda enseña las teclas Z, X y las flechas |
| Escritorio en ventana estrecha | Al encoger por debajo de 768 px debe cambiar a la vista de teléfono |

### Lo que ya se sabe y hay que confirmar

- **Teléfonos de menos de 408 px de ancho** se quedan con el lienzo a 200×200,
  sin ampliar, porque el escalado va en múltiplos enteros. Confirmar si se ve
  demasiado pequeño en un teléfono así; si molesta, hay que decidir si se
  permite escalado no entero, que emborrona el arte de píxeles.
- **La vibración y el bloqueo de orientación** solo funcionan bajo HTTPS en
  varios navegadores móviles. Probar contra el VPS, no contra una IP local.

---

## 4. Scripts de verificación

Todos escriben en la base y borran lo suyo al terminar. Ninguno toca filas
reales: las de prueba llevan placa `ZZZ…`.

```bash
# Etapas
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e1.php
# ... e2, e3, e4, e6, e8
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-participantes.php

# Carga: 50 accesos simultáneos, o los que se le pidan
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/prueba-carga.php
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/prueba-carga.php 200

# Ciclo completo con el padrón real anonimizado
docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/ciclo-completo.php
```

`ciclo-completo.php` sustituye teléfono, cédula y placa por valores
sintéticos antes de tocar nada, y escribe el archivo anonimizado en el
directorio temporal del sistema, nunca dentro del repositorio. Conserva ciudad,
departamento y establecimiento, que es donde está la forma rara que interesa
probar y no identifican a una persona.
