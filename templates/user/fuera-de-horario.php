<?php
/**
 * Aviso de fuera de horario.
 *
 * El formulario no se renderiza en absoluto: no está oculto con CSS, no existe
 * en el HTML. Aunque alguien lo reconstruya a mano, el endpoint rechaza por
 * hora de servidor.
 *
 * Variables desde NavidadTVS_Shortcode::render():
 *
 * @var array  $estado  Estado de la ventana, con 'mensaje' y 'horario'.
 * @var string $url_tyc Enlace a términos y condiciones.
 * @var string $url_faq Enlace a preguntas frecuentes.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ntvs ntvs--cerrado">

	<div class="ntvs-portada">
		<div class="ntvs-portada__tribuna" aria-hidden="true">
			<div class="ntvs-luces"></div>
			<div class="ntvs-cartel"><?php esc_html_e( 'CONCURSO TVS', 'navidad-tvs' ); ?></div>
		</div>
		<div class="ntvs-portada__pista" aria-hidden="true"></div>
		<h1 class="ntvs-titulo"><?php esc_html_e( 'Concurso Navideño', 'navidad-tvs' ); ?></h1>
	</div>

	<div class="ntvs-panel ntvs-panel--aviso">
		<p class="ntvs-cerrado-titulo"><?php esc_html_e( 'Fuera de horario', 'navidad-tvs' ); ?></p>

		<p class="ntvs-horario">
			<?php
			printf(
				/* translators: %s: rango horario, por ejemplo "de 12:00 p. m. a 1:30 p. m." */
				esc_html__( 'Se juega %s, hora de Colombia, de lunes a sábado.', 'navidad-tvs' ),
				esc_html( $estado['horario'] )
			);
			?>
		</p>

		<?php if ( ! empty( $estado['mensaje'] ) ) : ?>
			<p class="ntvs-proxima"><?php echo esc_html( $estado['mensaje'] ); ?></p>
		<?php endif; ?>

		<p class="ntvs-enlaces">
			<a href="<?php echo esc_url( $url_faq ); ?>"><?php esc_html_e( 'Preguntas frecuentes', 'navidad-tvs' ); ?></a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $url_tyc ); ?>"><?php esc_html_e( 'Términos y condiciones', 'navidad-tvs' ); ?></a>
		</p>
	</div>

</div>
