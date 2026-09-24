<?php
/**
 * Portada del concurso.
 *
 * Variables: $enlaces, $horario, $abierta, $premios.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ntvs-publico ntvs-home">

	<header class="ntvs-hero">
		<p class="ntvs-hero__marca"><?php esc_html_e( 'Auteco TVS', 'navidad-tvs' ); ?></p>
		<h1 class="ntvs-hero__titulo"><?php esc_html_e( 'Concurso Navideño TVS', 'navidad-tvs' ); ?></h1>
		<p class="ntvs-hero__bajada">
			<?php esc_html_e( 'Compraste tu moto TVS. Ahora corre 90 segundos y llega lo más lejos que puedas.', 'navidad-tvs' ); ?>
		</p>

		<p class="ntvs-hero__estado <?php echo $abierta ? 'ntvs-hero__estado--abierta' : ''; ?>">
			<?php
			echo $abierta
				? esc_html__( 'La jornada de hoy está abierta.', 'navidad-tvs' )
				: esc_html__( 'La jornada está cerrada en este momento.', 'navidad-tvs' );
			?>
		</p>

		<?php if ( '' !== $enlaces['juego'] ) : ?>
			<a class="ntvs-cta" href="<?php echo esc_url( $enlaces['juego'] ); ?>">
				<?php esc_html_e( 'Entrar al juego', 'navidad-tvs' ); ?>
			</a>
		<?php endif; ?>

		<p class="ntvs-hero__horario"><?php echo esc_html( $horario ); ?></p>
	</header>

	<section class="ntvs-bloque" aria-labelledby="ntvs-como">
		<h2 class="ntvs-h2" id="ntvs-como"><?php esc_html_e( 'Cómo funciona', 'navidad-tvs' ); ?></h2>

		<ol class="ntvs-pasos">
			<li class="ntvs-paso">
				<span class="ntvs-paso__num" aria-hidden="true">1</span>
				<h3 class="ntvs-paso__titulo"><?php esc_html_e( 'Entra en tu jornada', 'navidad-tvs' ); ?></h3>
				<p><?php esc_html_e( 'A cada comprador se le asigna un día según la fecha de su compra. Digita tu celular y tu nombre para saber si hoy te toca.', 'navidad-tvs' ); ?></p>
			</li>
			<li class="ntvs-paso">
				<span class="ntvs-paso__num" aria-hidden="true">2</span>
				<h3 class="ntvs-paso__titulo"><?php esc_html_e( 'Corre 90 segundos', 'navidad-tvs' ); ?></h3>
				<p><?php esc_html_e( 'Acelera, dosifica el turbo para que el motor no se sobrecaliente, esquiva los conos y recoge llaves: cada una suma 50 metros.', 'navidad-tvs' ); ?></p>
			</li>
			<li class="ntvs-paso">
				<span class="ntvs-paso__num" aria-hidden="true">3</span>
				<h3 class="ntvs-paso__titulo"><?php esc_html_e( 'Llega lo más lejos posible', 'navidad-tvs' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %d: cuántos premios hay por jornada. */
						esc_html__( 'Ganan los %d participantes con mayor distancia de cada jornada. Si hay empate, todos los empatados reciben premio.', 'navidad-tvs' ),
						(int) $premios
					);
					?>
				</p>
			</li>
		</ol>
	</section>

	<section class="ntvs-bloque ntvs-bloque--aviso" aria-labelledby="ntvs-ojo">
		<h2 class="ntvs-h2" id="ntvs-ojo"><?php esc_html_e( 'Antes de jugar, ten esto claro', 'navidad-tvs' ); ?></h2>

		<ul class="ntvs-claves">
			<li>
				<strong><?php esc_html_e( 'Tienes un solo intento.', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'No se repone por ningún motivo, tampoco si se cae la conexión. Juega desde una red estable, preferiblemente WiFi, y no cierres la página hasta que veas que tu resultado quedó registrado.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Solo en tu jornada.', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'Cada participante juega el día que le fue asignado. No se puede cambiar.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Solo motos TVS.', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'La actividad es exclusiva para compradores de la marca TVS.', 'navidad-tvs' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'En horizontal.', 'navidad-tvs' ); ?></strong>
				<?php esc_html_e( 'El juego necesita el celular acostado. Si lo tienes vertical, la pantalla te lo pedirá.', 'navidad-tvs' ); ?>
			</li>
		</ul>
	</section>

	<?php if ( '' !== $enlaces['faq'] || '' !== $enlaces['terminos'] ) : ?>
		<nav class="ntvs-pie" aria-label="<?php esc_attr_e( 'Más información', 'navidad-tvs' ); ?>">
			<?php if ( '' !== $enlaces['faq'] ) : ?>
				<a href="<?php echo esc_url( $enlaces['faq'] ); ?>"><?php esc_html_e( 'Preguntas frecuentes', 'navidad-tvs' ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $enlaces['terminos'] ) : ?>
				<a href="<?php echo esc_url( $enlaces['terminos'] ); ?>"><?php esc_html_e( 'Términos y condiciones', 'navidad-tvs' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

</div>
