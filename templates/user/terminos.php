<?php
/**
 * Términos y condiciones.
 *
 * Las cláusulas se numeran por CSS a partir de un <ol>, no a mano: así
 * insertar una cláusula en medio no obliga a renumerar el resto, que es
 * exactamente cuando se cuelan los errores en un documento legal.
 *
 * Variables: $clausulas, $version, $enlaces.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="ntvs-publico ntvs-terminos">

	<header class="ntvs-cabecera">
		<h1 class="ntvs-h1"><?php esc_html_e( 'Términos y condiciones', 'navidad-tvs' ); ?></h1>
		<p class="ntvs-cabecera__bajada">
			<?php esc_html_e( 'Concurso Navideño TVS', 'navidad-tvs' ); ?>
			<span class="ntvs-version">
				<?php
				printf(
					/* translators: %s: número de versión del documento. */
					esc_html__( 'Versión %s', 'navidad-tvs' ),
					esc_html( $version )
				);
				?>
			</span>
		</p>
	</header>

	<ol class="ntvs-clausulas">
		<?php foreach ( $clausulas as $clausula ) : ?>
			<li class="ntvs-clausula">
				<h2 class="ntvs-clausula__titulo"><?php echo esc_html( $clausula['titulo'] ); ?></h2>
				<?php foreach ( $clausula['parrafos'] as $parrafo ) : ?>
					<p><?php echo esc_html( $parrafo ); ?></p>
				<?php endforeach; ?>
			</li>
		<?php endforeach; ?>
	</ol>

	<nav class="ntvs-pie" aria-label="<?php esc_attr_e( 'Más información', 'navidad-tvs' ); ?>">
		<?php if ( '' !== $enlaces['juego'] ) : ?>
			<a href="<?php echo esc_url( $enlaces['juego'] ); ?>"><?php esc_html_e( 'Entrar al juego', 'navidad-tvs' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $enlaces['faq'] ) : ?>
			<a href="<?php echo esc_url( $enlaces['faq'] ); ?>"><?php esc_html_e( 'Preguntas frecuentes', 'navidad-tvs' ); ?></a>
		<?php endif; ?>
	</nav>

</div>
