<?php
/**
 * Formulario de configuración del concurso.
 *
 * @var NavidadTVS_Settings $settings Configuración.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aviso = isset( $_GET['aviso'] ) ? sanitize_key( wp_unslash( $_GET['aviso'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$dias_actuales = (array) $settings->get( 'dias_habiles' );

$nombres_dias = array(
	1 => __( 'Lunes', 'navidad-tvs' ),
	2 => __( 'Martes', 'navidad-tvs' ),
	3 => __( 'Miércoles', 'navidad-tvs' ),
	4 => __( 'Jueves', 'navidad-tvs' ),
	5 => __( 'Viernes', 'navidad-tvs' ),
	6 => __( 'Sábado', 'navidad-tvs' ),
	7 => __( 'Domingo', 'navidad-tvs' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Configuración del concurso', 'navidad-tvs' ); ?></h1>

	<?php if ( 'guardado' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuración guardada.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'horario_invalido' === $aviso ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'La hora de cierre debe ser posterior a la de apertura. Se conservó la anterior.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'sin_dias' === $aviso ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Hay que dejar al menos un día hábil. Se conservaron los anteriores.', 'navidad-tvs' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'navidad_tvs_guardar_config' ); ?>
		<input type="hidden" name="action" value="navidad_tvs_guardar_config">

		<h2><?php esc_html_e( 'Ventana de participación', 'navidad-tvs' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Siempre en hora de Colombia (America/Bogota) y medida en el servidor. Cambiar la hora del celular no abre la ventana.', 'navidad-tvs' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="hora_inicio"><?php esc_html_e( 'Hora de apertura', 'navidad-tvs' ); ?></label></th>
				<td><input type="time" id="hora_inicio" name="hora_inicio" value="<?php echo esc_attr( $settings->get( 'hora_inicio' ) ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="hora_fin"><?php esc_html_e( 'Hora de cierre', 'navidad-tvs' ); ?></label></th>
				<td><input type="time" id="hora_fin" name="hora_fin" value="<?php echo esc_attr( $settings->get( 'hora_fin' ) ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Días hábiles', 'navidad-tvs' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $nombres_dias as $n => $nombre ) : ?>
							<label style="display:inline-block;margin-right:14px">
								<input type="checkbox" name="dias_habiles[]" value="<?php echo esc_attr( $n ); ?>"
									<?php checked( in_array( $n, $dias_actuales, true ) ); ?>>
								<?php echo esc_html( $nombre ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Premios', 'navidad-tvs' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="premios_por_jornada"><?php esc_html_e( 'Ganadores por jornada', 'navidad-tvs' ); ?></label></th>
				<td>
					<input type="number" id="premios_por_jornada" name="premios_por_jornada" min="1" max="50"
						value="<?php echo esc_attr( (int) $settings->get( 'premios_por_jornada' ) ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Si hay empate en el último puesto, todos los empatados reciben premio.', 'navidad-tvs' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Operación', 'navidad-tvs' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Congelar concurso', 'navidad-tvs' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="concurso_congelado" value="1" <?php checked( (bool) $settings->get( 'concurso_congelado' ) ); ?>>
						<?php esc_html_e( 'Impedir toda participación', 'navidad-tvs' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Corta el acceso aunque la hora esté dentro de la ventana, sin desactivar el plugin ni perder datos.', 'navidad-tvs' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="terminos_version"><?php esc_html_e( 'Versión de los términos', 'navidad-tvs' ); ?></label></th>
				<td>
					<input type="text" id="terminos_version" name="terminos_version" class="small-text"
						value="<?php echo esc_attr( $settings->get( 'terminos_version' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Se guarda junto a cada consentimiento. Súbela cada vez que cambie el texto publicado, para saber qué aceptó cada participante.', 'navidad-tvs' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Páginas', 'navidad-tvs' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pagina_terminos"><?php esc_html_e( 'Términos y condiciones', 'navidad-tvs' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'             => 'pagina_terminos',
							'id'               => 'pagina_terminos',
							'selected'         => (int) $settings->get( 'pagina_terminos' ),
							'show_option_none' => __( '— Sin asignar —', 'navidad-tvs' ),
							'option_none_value' => 0,
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pagina_faq"><?php esc_html_e( 'Preguntas frecuentes', 'navidad-tvs' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'             => 'pagina_faq',
							'id'               => 'pagina_faq',
							'selected'         => (int) $settings->get( 'pagina_faq' ),
							'show_option_none' => __( '— Sin asignar —', 'navidad-tvs' ),
							'option_none_value' => 0,
						)
					);
					?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Cloudflare Turnstile', 'navidad-tvs' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Opcional. Si se dejan vacías, la verificación se omite; sirve para desarrollo, pero en producción conviene activarla para frenar la enumeración de teléfonos.', 'navidad-tvs' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="turnstile_site_key"><?php esc_html_e( 'Site key', 'navidad-tvs' ); ?></label></th>
				<td><input type="text" id="turnstile_site_key" name="turnstile_site_key" class="regular-text"
					value="<?php echo esc_attr( $settings->get( 'turnstile_site_key' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="turnstile_secret_key"><?php esc_html_e( 'Secret key', 'navidad-tvs' ); ?></label></th>
				<td><input type="password" id="turnstile_secret_key" name="turnstile_secret_key" class="regular-text"
					value="<?php echo esc_attr( $settings->get( 'turnstile_secret_key' ) ); ?>" autocomplete="off"></td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<h2><?php esc_html_e( 'Cómo publicar la página del juego', 'navidad-tvs' ); ?></h2>
	<p><?php esc_html_e( 'Crea una página y pega este shortcode. Es la única página que lleva el estilo arcade.', 'navidad-tvs' ); ?></p>
	<p><code>[<?php echo esc_html( NavidadTVS_Shortcode::TAG ); ?>]</code></p>
</div>
