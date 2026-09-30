<?php
/**
 * Pantalla de búsqueda y edición del padrón.
 *
 * Variables que llegan de NavidadTVS_Participantes::render():
 * $database, $busqueda, $resultados, $participante, $intento, $aviso, $detalle.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ntvs_pagina = NavidadTVS_Admin::SLUG . '-participantes';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Participantes', 'navidad-tvs' ); ?></h1>

	<?php if ( 'guardado' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Cambios guardados.', 'navidad-tvs' ); ?></p>
		</div>
	<?php elseif ( 'error' === $aviso ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( '' !== $detalle ? $detalle : __( 'No se pudo guardar.', 'navidad-tvs' ) ); ?></p>
		</div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Busca por teléfono, cédula o placa. El padrón no guarda el nombre: lo digita el participante al entrar y es solo informativo.', 'navidad-tvs' ); ?>
	</p>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( $ntvs_pagina ); ?>">
		<p>
			<label class="screen-reader-text" for="ntvs-buscar"><?php esc_html_e( 'Buscar', 'navidad-tvs' ); ?></label>
			<input type="search" id="ntvs-buscar" name="buscar" class="regular-text"
				value="<?php echo esc_attr( $busqueda ); ?>"
				placeholder="<?php esc_attr_e( '3504567217, 1032456789 o ABC12D', 'navidad-tvs' ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Buscar', 'navidad-tvs' ); ?></button>
		</p>
	</form>

	<?php if ( '' !== $busqueda ) : ?>
		<?php if ( empty( $resultados ) ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'No hay nadie en el padrón con ese dato. Revisa que el archivo del día se haya importado.', 'navidad-tvs' ); ?>
				</p>
			</div>
		<?php else : ?>
			<h2><?php esc_html_e( 'Resultados', 'navidad-tvs' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Teléfono', 'navidad-tvs' ); ?></th>
						<th><?php esc_html_e( 'Cédula', 'navidad-tvs' ); ?></th>
						<th><?php esc_html_e( 'Placa', 'navidad-tvs' ); ?></th>
						<th><?php esc_html_e( 'Jornada', 'navidad-tvs' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'navidad-tvs' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $resultados as $ntvs_fila ) : ?>
						<tr>
							<td><code><?php echo esc_html( $ntvs_fila['telefono'] ); ?></code></td>
							<td><code><?php echo esc_html( $ntvs_fila['cedula'] ); ?></code></td>
							<td><code><?php echo esc_html( $ntvs_fila['placa'] ); ?></code></td>
							<td><?php echo esc_html( $ntvs_fila['fecha_concurso'] ); ?></td>
							<td><?php echo esc_html( $ntvs_fila['estado'] ); ?></td>
							<td>
								<a class="button" href="<?php
								echo esc_url(
									add_query_arg(
										array(
											'page'   => $ntvs_pagina,
											'buscar' => $busqueda,
											'editar' => (int) $ntvs_fila['id'],
										),
										admin_url( 'admin.php' )
									)
								);
								?>"><?php esc_html_e( 'Editar', 'navidad-tvs' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $participante ) : ?>
		<hr>
		<h2>
			<?php
			printf(
				/* translators: %s: teléfono del participante */
				esc_html__( 'Editar %s', 'navidad-tvs' ),
				'<code>' . esc_html( $participante['telefono'] ) . '</code>'
			);
			?>
		</h2>

		<?php
		/*
		 * El diagnóstico va ANTES del formulario, no después.
		 *
		 * Quien abre esta pantalla casi siempre viene de "fulano no puede
		 * entrar". Si lo primero que ve son los campos, cambia la fecha y se
		 * va; y si el problema era otro —ya jugó, está descalificado— habrá
		 * tocado el padrón sin arreglar nada.
		 */
		?>
		<?php if ( $intento['puede'] ) : ?>
			<div class="notice notice-success inline">
				<p><strong><?php esc_html_e( 'Puede jugar hoy.', 'navidad-tvs' ); ?></strong></p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Hoy no puede jugar, por esto:', 'navidad-tvs' ); ?></strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $intento['motivos'] as $ntvs_motivo ) : ?>
						<li><?php echo esc_html( $ntvs_motivo ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( ! empty( $intento['ya_jugo'] ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'Devolverle el intento implicaría borrar un resultado ya registrado, y eso no se hace desde esta pantalla.', 'navidad-tvs' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'navidad_tvs_guardar_participante' ); ?>
			<input type="hidden" name="action" value="navidad_tvs_guardar_participante">
			<input type="hidden" name="id" value="<?php echo (int) $participante['id']; ?>">
			<input type="hidden" name="buscar" value="<?php echo esc_attr( $busqueda ); ?>">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ntvs-f-telefono"><?php esc_html_e( 'Teléfono', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="text" id="ntvs-f-telefono" name="telefono" class="regular-text"
							value="<?php echo esc_attr( $participante['telefono'] ); ?>" required>
						<p class="description"><?php esc_html_e( 'Diez dígitos, empezando por 3. Es con lo que la persona entra al juego.', 'navidad-tvs' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-cedula"><?php esc_html_e( 'Cédula', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="text" id="ntvs-f-cedula" name="cedula" class="regular-text"
							value="<?php echo esc_attr( $participante['cedula'] ); ?>" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-placa"><?php esc_html_e( 'Placa', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="text" id="ntvs-f-placa" name="placa" class="regular-text"
							value="<?php echo esc_attr( $participante['placa'] ); ?>" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-fecha"><?php esc_html_e( 'Jornada', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="date" id="ntvs-f-fecha" name="fecha_concurso"
							value="<?php echo esc_attr( $participante['fecha_concurso'] ); ?>" required>
						<p class="description">
							<?php
							printf(
								/* translators: %s: fecha de hoy en Colombia */
								esc_html__( 'El día en que le toca jugar. Hoy en Colombia es %s.', 'navidad-tvs' ),
								'<code>' . esc_html( NavidadTVS_Plugin::hoy() ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-estado"><?php esc_html_e( 'Estado', 'navidad-tvs' ); ?></label></th>
					<td>
						<select id="ntvs-f-estado" name="estado">
							<option value="habilitado" <?php selected( $participante['estado'], 'habilitado' ); ?>>
								<?php esc_html_e( 'Habilitado', 'navidad-tvs' ); ?>
							</option>
							<option value="descalificado" <?php selected( $participante['estado'], 'descalificado' ); ?>>
								<?php esc_html_e( 'Descalificado', 'navidad-tvs' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-ciudad"><?php esc_html_e( 'Ciudad', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="text" id="ntvs-f-ciudad" name="ciudad_propietario" class="regular-text"
							value="<?php echo esc_attr( $participante['ciudad_propietario'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-departamento"><?php esc_html_e( 'Departamento', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="text" id="ntvs-f-departamento" name="departamento_propietario" class="regular-text"
							value="<?php echo esc_attr( $participante['departamento_propietario'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ntvs-f-establecimiento"><?php esc_html_e( 'Establecimiento', 'navidad-tvs' ); ?></label></th>
					<td>
						<input type="text" id="ntvs-f-establecimiento" name="razon_social_establecimiento" class="large-text"
							value="<?php echo esc_attr( $participante['razon_social_establecimiento'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Marca', 'navidad-tvs' ); ?></th>
					<td>
						<p><code><?php echo esc_html( $participante['marca'] ); ?></code></p>
						<p class="description">
							<?php esc_html_e( 'No se edita: el concurso es solo para TVS y cambiarla a mano habilitaría a alguien que no compró la moto.', 'navidad-tvs' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Guardar cambios', 'navidad-tvs' ) ); ?>
		</form>
	<?php endif; ?>
</div>
