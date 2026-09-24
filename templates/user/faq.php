<?php
/**
 * Preguntas frecuentes.
 *
 * Los acordeones son <details>/<summary> nativos: se abren sin JavaScript, el
 * buscador del navegador encuentra el texto de dentro y el lector de pantalla
 * los anuncia solos. Un acordeón hecho a mano con divs y clases necesita
 * teclado, aria-expanded y JavaScript para hacer lo mismo peor.
 *
 * Variables: $grupos, $enlaces.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ntvs-publico ntvs-faq">

	<header class="ntvs-cabecera">
		<h1 class="ntvs-h1"><?php esc_html_e( 'Preguntas frecuentes', 'navidad-tvs' ); ?></h1>
		<p class="ntvs-cabecera__bajada"><?php esc_html_e( 'Concurso Navideño TVS', 'navidad-tvs' ); ?></p>
	</header>

	<?php foreach ( $grupos as $i => $grupo ) : ?>
		<section class="ntvs-bloque" aria-labelledby="ntvs-faq-<?php echo (int) $i; ?>">
			<h2 class="ntvs-h2" id="ntvs-faq-<?php echo (int) $i; ?>"><?php echo esc_html( $grupo['titulo'] ); ?></h2>

			<?php foreach ( $grupo['preguntas'] as $par ) : ?>
				<details class="ntvs-acordeon">
					<summary class="ntvs-acordeon__p"><?php echo esc_html( $par['p'] ); ?></summary>
					<div class="ntvs-acordeon__r"><p><?php echo esc_html( $par['r'] ); ?></p></div>
				</details>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>

	<nav class="ntvs-pie" aria-label="<?php esc_attr_e( 'Más información', 'navidad-tvs' ); ?>">
		<?php if ( '' !== $enlaces['juego'] ) : ?>
			<a href="<?php echo esc_url( $enlaces['juego'] ); ?>"><?php esc_html_e( 'Entrar al juego', 'navidad-tvs' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $enlaces['terminos'] ) : ?>
			<a href="<?php echo esc_url( $enlaces['terminos'] ); ?>"><?php esc_html_e( 'Términos y condiciones', 'navidad-tvs' ); ?></a>
		<?php endif; ?>
	</nav>

</div>
