<?php
/**
 * Página de acceso al juego, con la ventana de participación abierta.
 *
 * Variables desde NavidadTVS_Shortcode::render():
 *
 * @var array  $estado   Estado de la ventana.
 * @var string $url_tyc  Enlace a términos y condiciones.
 * @var string $url_faq  Enlace a preguntas frecuentes.
 * @var string $site_key Clave pública de Turnstile, o cadena vacía.
 * @var int    $duracion Duración de la carrera en segundos.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ntvs" id="ntvs-app">

	<!-- Portada ------------------------------------------------------- -->
	<div class="ntvs-portada">
		<div class="ntvs-portada__tribuna" aria-hidden="true">
			<div class="ntvs-luces"></div>
			<div class="ntvs-cartel"><?php esc_html_e( 'NAVIDAD TVS', 'navidad-tvs' ); ?></div>
		</div>
		<div class="ntvs-portada__pista" aria-hidden="true"></div>
		<h1 class="ntvs-titulo"><?php esc_html_e( 'Concurso Navideño', 'navidad-tvs' ); ?></h1>
		<p class="ntvs-subtitulo">
			<?php
			printf(
				/* translators: %d: duración de la carrera en segundos */
				esc_html__( '%d segundos. La mayor distancia gana.', 'navidad-tvs' ),
				(int) $duracion
			);
			?>
		</p>
	</div>

	<!-- Formulario ----------------------------------------------------- -->
	<div class="ntvs-panel" id="ntvs-panel-acceso">
		<p class="ntvs-panel__intro"><?php esc_html_e( 'Ingresa tus datos para entrar al juego', 'navidad-tvs' ); ?></p>

		<form class="ntvs-form" id="ntvs-form" novalidate>

			<div class="ntvs-campo">
				<label class="ntvs-label" for="ntvs-nombre"><?php esc_html_e( 'Tu nombre', 'navidad-tvs' ); ?></label>
				<input
					class="ntvs-input"
					type="text"
					id="ntvs-nombre"
					name="nombre"
					maxlength="60"
					autocomplete="name"
					autocapitalize="words"
					required>
			</div>

			<div class="ntvs-campo">
				<label class="ntvs-label" for="ntvs-telefono"><?php esc_html_e( 'Tu celular', 'navidad-tvs' ); ?></label>
				<input
					class="ntvs-input"
					type="tel"
					id="ntvs-telefono"
					name="telefono"
					inputmode="numeric"
					pattern="[0-9]*"
					maxlength="14"
					placeholder="3001234567"
					autocomplete="tel-national"
					required>
				<p class="ntvs-ayuda"><?php esc_html_e( 'Diez dígitos, el mismo que registraste al comprar tu moto.', 'navidad-tvs' ); ?></p>
			</div>

			<div class="ntvs-campo ntvs-campo--check">
				<label class="ntvs-check">
					<input type="checkbox" id="ntvs-acepta" name="acepta" value="1" required>
					<span>
						<?php
						printf(
							/* translators: %s: enlace a los términos y condiciones */
							esc_html__( 'Acepto los %s y el tratamiento de mis datos personales.', 'navidad-tvs' ),
							'<a href="' . esc_url( $url_tyc ) . '" target="_blank" rel="noopener">' . esc_html__( 'términos y condiciones', 'navidad-tvs' ) . '</a>'
						);
						?>
					</span>
				</label>
			</div>

			<?php if ( '' !== $site_key ) : ?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="dark"></div>
			<?php endif; ?>

			<p class="ntvs-error" id="ntvs-error" role="alert" aria-live="assertive" hidden></p>

			<button class="ntvs-boton" type="submit" id="ntvs-enviar">
				<?php esc_html_e( 'Entrar al juego', 'navidad-tvs' ); ?>
			</button>
		</form>

		<p class="ntvs-aviso">
			<?php esc_html_e( 'Tienes un solo intento. Juega desde una conexión estable, preferiblemente WiFi.', 'navidad-tvs' ); ?>
		</p>

		<p class="ntvs-enlaces">
			<a href="<?php echo esc_url( $url_faq ); ?>"><?php esc_html_e( 'Preguntas frecuentes', 'navidad-tvs' ); ?></a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $url_tyc ); ?>"><?php esc_html_e( 'Términos y condiciones', 'navidad-tvs' ); ?></a>
		</p>
	</div>

	<!-- Instrucciones (tras validar el acceso) -------------------------- -->
	<div class="ntvs-panel" id="ntvs-panel-instrucciones" hidden>
		<p class="ntvs-bienvenida">
			<?php esc_html_e( '¡Listo', 'navidad-tvs' ); ?>
			<span id="ntvs-nombre-jugador"></span>!
		</p>

		<h2 class="ntvs-h2"><?php esc_html_e( 'Cómo se juega', 'navidad-tvs' ); ?></h2>

		<ul class="ntvs-instrucciones">
			<li>
				<strong><?php esc_html_e( 'Acelerador', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Zona inferior derecha, o la tecla Z.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Turbo', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Zona superior derecha, o la tecla X. Vas más rápido, pero calienta el motor.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Cambiar de carril', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Zona izquierda, arriba o abajo. En el teclado, las flechas.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Temperatura', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'El turbo la sube. Si llega al tope, el motor se sobrecalienta y la moto se detiene dos segundos y medio. Suelta el turbo para enfriarlo; soltar el acelerador enfría el doble de rápido.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Llaves', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Cada una que recojas suma 50 metros.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Impulsores', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Las flechas verdes del pavimento te dan un empujón y suman 1 metro.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Conos', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Esquívalos cambiando de carril. Si le pegas a uno te caes y pierdes dos segundos.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Charcos de aceite', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'No tumban, pero te frenan mientras los pisas.', 'navidad-tvs' ); ?>
			</li>
		</ul>

		<p class="ntvs-rotar" id="ntvs-rotar" hidden>
			<?php esc_html_e( 'Gira tu dispositivo en horizontal para jugar.', 'navidad-tvs' ); ?>
		</p>

		<p class="ntvs-aviso ntvs-aviso--fuerte">
			<?php esc_html_e( 'Al pulsar el botón empieza una cuenta regresiva y luego la carrera. Es tu único intento.', 'navidad-tvs' ); ?>
		</p>

		<button class="ntvs-boton" type="button" id="ntvs-iniciar">
			<?php esc_html_e( 'Iniciar carrera', 'navidad-tvs' ); ?>
		</button>
	</div>

	<!-- Juego ------------------------------------------------------------ -->
	<div class="ntvs-panel ntvs-panel--juego" id="ntvs-panel-juego" hidden>
		<div id="ntvs-game-root" class="ntvs-game-root"></div>

		<!-- Resultado. La pantalla del podio llega en E5 y la validación en E6. -->
		<div class="ntvs-resultado" id="ntvs-resultado" hidden>
			<p class="ntvs-resultado__titulo"><?php esc_html_e( 'Carrera terminada', 'navidad-tvs' ); ?></p>
			<p class="ntvs-resultado__nombre" id="ntvs-res-nombre"></p>
			<p class="ntvs-resultado__distancia" id="ntvs-res-distancia"></p>

			<ul class="ntvs-resultado__detalle">
				<li><?php esc_html_e( 'Llaves', 'navidad-tvs' ); ?> <span id="ntvs-res-logos"></span></li>
				<li><?php esc_html_e( 'Caídas', 'navidad-tvs' ); ?> <span id="ntvs-res-caidas"></span></li>
				<li><?php esc_html_e( 'Veces que se sobrecalentó', 'navidad-tvs' ); ?> <span id="ntvs-res-sobrecal"></span></li>
				<li><?php esc_html_e( 'Registro de la carrera', 'navidad-tvs' ); ?> <span id="ntvs-res-bytes"></span> B</li>
			</ul>

			<p class="ntvs-resultado__gracias"><?php esc_html_e( '¡Gracias por participar!', 'navidad-tvs' ); ?></p>

			<p class="ntvs-pendiente">
				<?php esc_html_e( 'Resultado provisional calculado en tu dispositivo. El envío y la validación en el servidor llegan en la etapa E6.', 'navidad-tvs' ); ?>
			</p>
		</div>
	</div>

</div>
