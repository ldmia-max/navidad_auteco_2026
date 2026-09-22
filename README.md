# Concurso Navideño Auteco TVS 2026

Plugin de WordPress con un juego arcade-retro estilo Excitebike para la campaña
navideña de Auteco TVS. Los compradores de motocicleta TVS corren 90 segundos y
ganan los cuatro participantes que más metros recorran en cada jornada.

## Cómo funciona

1. El área comercial entrega un CSV diario con los compradores habilitados para
   esa jornada.
2. El CSV se importa desde el panel de WordPress.
3. Entre las 12:00 p. m. y la 1:30 p. m., de lunes a sábado, el participante
   ingresa su celular y su nombre en el sitio del concurso.
4. Si está habilitado y es su jornada, juega **una sola carrera de 90
   segundos**.
5. El servidor valida el resultado reejecutando la partida y lo registra.
6. El ranking del día queda disponible en el panel, exportable a CSV.

## Stack

- WordPress como contenedor y backoffice (PHP 8.2, MySQL)
- Juego en Phaser 3 + TypeScript, compilado con Vite
- Docker Compose para desarrollo local

## Estructura

```
navidad-tvs.php        Archivo principal del plugin
includes/              Lógica PHP (base de datos, REST, física, admin)
assets/                CSS, JS y el bundle compilado del juego
templates/             Vistas de frontend y admin
game/                  Fuente TypeScript del juego (no se empaqueta)
docs/                  Plan, mecánica, textos legales
docker/                Configuración del entorno local
dev/                   Scripts de desarrollo y seeds
dist/                  Zips generados para instalar en WordPress
ayudas/                Datos reales del padrón — ignorado por git
```

## Desarrollo

```bash
docker compose up -d          # WordPress en http://localhost:8080
cd game && npm install && npm run dev
```

## Empaquetar

```powershell
.\build-zip.ps1               # genera dist\navidad-tvs-<version>.zip
```

```bash
./build-zip.sh
```

## Documentación

- [Plan de desarrollo](docs/plan-desarrollo.md) — etapas y estado de avance
- [Mecánica y balanceo](docs/mecanica-y-balanceo.md) — física, obstáculos,
  temperatura del motor
- [Arte y sonido](docs/arte-y-sonido.md) — cómo está hecho el pixel art y cómo
  cambiar colores y textos
- [Términos y condiciones (ejemplo)](docs/terminos-y-condiciones-ejemplo.md)
- [Preguntas frecuentes (ejemplo)](docs/faq-ejemplo.md)
- [DOCKER.md](DOCKER.md) — entorno local, WP-CLI y verificación del esquema
- [CLAUDE.md](CLAUDE.md) — convenciones y reglas del proyecto

## Aviso sobre datos personales

El padrón de participantes contiene teléfonos, cédulas y placas reales. La
carpeta `ayudas/` está excluida del control de versiones y debe permanecer así.
