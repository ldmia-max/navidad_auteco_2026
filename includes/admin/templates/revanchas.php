<?php
/**
 * Pantalla de revanchas.
 *
 * Variables de NavidadTVS_Revanchas::render(): $database, $revanchas, $hoy,
 * $aviso, $detalle.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Revanchas', 'navidad-tvs' ); ?></h1>

	<?php if ( 'creada' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $detalle ); ?></p></div>
	<?php elseif ( 'borrada' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Revancha retirada.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'error' === $aviso ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( '' !== $detalle ? $detalle : __( 'No se pudo conceder la revancha.', 'navidad-tvs' ) ); ?></p></div>
	<?php endif; ?>

	<p class="description" style="max-width:820px">
		<?php esc_html_e( 'Una revancha es una fecha extra en la que alguien vuelve a jugar. Da un intento más en esa jornada, no barra libre: dentro del mismo día nadie juega dos veces.', 'navidad-tvs' ); ?>
	</p>

	<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">

		<div style="flex:1 1 380px">
			<h2><?php esc_html_e( 'Jornada de revancha para todos', 'navidad-tvs' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Ese día vuelve a jugar todo el que no haya ganado todavía y cuya jornada ya haya pasado. Quien ya ganó un premio no entra, y quien aún no ha tenido su día tampoco: no ha perdido nada que devolverle.', 'navidad-tvs' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'navidad_tvs_revancha_general' ); ?>
				<input type="hidden" name="action" value="navidad_tvs_revancha_general">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ntvs-rg-fecha"><?php esc_html_e( 'Fecha', 'navidad-tvs' ); ?></label></th>
						<td><input type="date" id="ntvs-rg-fecha" name="fecha" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="ntvs-rg-motivo"><?php esc_html_e( 'Nota', 'navidad-tvs' ); ?></label></th>
						<td>
							<input type="text" id="ntvs-rg-motivo" name="motivo" class="regular-text" maxlength="255"
								placeholder="<?php esc_attr_e( 'Por ejemplo: segunda oportunidad de campaña', 'navidad-tvs' ); ?>">
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Abrir jornada de revancha', 'navidad-tvs' ), 'primary', 'submit', false ); ?>
			</form>
		</div>

		<div style="flex:1 1 380px">
			<h2><?php esc_html_e( 'Revancha para una sola persona', 'navidad-tvs' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Para atender un reclamo concreto. El motivo es obligatorio: devolverle el intento a alguien es una decisión que hay que poder explicar después.', 'navidad-tvs' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'navidad_tvs_revancha_individual' ); ?>
				<input type="hidden" name="action" value="navidad_tvs_revancha_individual">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ntvs-ri-busqueda"><?php esc_html_e( 'Cédula o celular', 'navidad-tvs' ); ?></label></th>
						<td>
							<input type="text" id="ntvs-ri-busqueda" name="busqueda" class="regular-text" required
								placeholder="<?php esc_attr_e( '1032456789 o 3504567217', 'navidad-tvs' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ntvs-ri-fecha"><?php esc_html_e( 'Fecha', 'navidad-tvs' ); ?></label></th>
						<td><input type="date" id="ntvs-ri-fecha" name="fecha" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="ntvs-ri-motivo"><?php esc_html_e( 'Motivo', 'navidad-tvs' ); ?></label></th>
						<td>
							<input type="text" id="ntvs-ri-motivo" name="motivo" class="regular-text" required maxlength="255"
								placeholder="<?php esc_attr_e( 'Por ejemplo: derecho de petición 123', 'navidad-tvs' ); ?>">
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Devolver el intento', 'navidad-tvs' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	</div>

	<hr>

	<h2><?php esc_html_e( 'Revanchas concedidas', 'navidad-tvs' ); ?></h2>

	<?php if ( empty( $revanchas ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Todavía no hay ninguna.', 'navidad-tvs' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:110px"><?php esc_html_e( 'Fecha', 'navidad-tvs' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Alcance', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Persona', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Motivo', 'navidad-tvs' ); ?></th>
					<th style="width:160px"><?php esc_html_e( 'Concedida', 'navidad-tvs' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $revanchas as $ntvs_r ) : ?>
					<?php $ntvs_general = 0 === (int) $ntvs_r['participante_id']; ?>
					<tr<?php echo $ntvs_r['fecha'] === $hoy ? ' style="background:#e7f6e7"' : ''; ?>>
						<td>
							<?php echo esc_html( $ntvs_r['fecha'] ); ?>
							<?php if ( $ntvs_r['fecha'] === $hoy ) : ?>
								<br><strong><?php esc_html_e( 'es hoy', 'navidad-tvs' ); ?></strong>
							<?php elseif ( $ntvs_r['fecha'] < $hoy ) : ?>
								<br><span class="description"><?php esc_html_e( 'ya pasó', 'navidad-tvs' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $ntvs_general ) : ?>
								<strong><?php esc_html_e( 'Todos', 'navidad-tvs' ); ?></strong>
							<?php else : ?>
								<?php esc_html_e( 'Una persona', 'navidad-tvs' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $ntvs_general ) : ?>
								<span class="description"><?php esc_html_e( 'quien no haya ganado', 'navidad-tvs' ); ?></span>
							<?php elseif ( empty( $ntvs_r['telefono'] ) ) : ?>
								<span class="description"><?php esc_html_e( 'ya no está en el padrón', 'navidad-tvs' ); ?></span>
							<?php else : ?>
								<code><?php echo esc_html( $ntvs_r['telefono'] ); ?></code>
								· <code><?php echo esc_html( $ntvs_r['cedula'] ); ?></code>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $ntvs_r['motivo'] ); ?></td>
						<td><?php echo esc_html( $ntvs_r['creada_en'] ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'navidad_tvs_borrar_revancha' ); ?>
								<input type="hidden" name="action" value="navidad_tvs_borrar_revancha">
								<input type="hidden" name="id" value="<?php echo (int) $ntvs_r['id']; ?>">
								<button type="submit" class="button button-small"
									onclick="return confirm('<?php echo esc_js( __( '¿Retirar esta revancha?', 'navidad-tvs' ) ); ?>');">
									<?php esc_html_e( 'Retirar', 'navidad-tvs' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php esc_html_e( 'Retirar una revancha de un día que ya pasó no borra las carreras que se jugaron: esas quedan en el ranking con su jornada.', 'navidad-tvs' ); ?>
		</p>
	<?php endif; ?>
</div>
