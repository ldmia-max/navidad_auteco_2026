<?php
/**
 * Auditoría de un intento.
 *
 * No es un vídeo de la carrera: es la prueba de que el resultado se reproduce.
 * Se vuelve a correr la simulación con el mismo seed y el mismo log de
 * entradas, y se compara con lo que quedó guardado.
 *
 * Variables de NavidadTVS_Ranking::render(): $score, $auditoria, $desde,
 * $hasta, $todos, $aviso, $detalle.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ntvs_pagina = NavidadTVS_Admin::SLUG . '-ranking';
$ntvs_volver = array( 'page' => $ntvs_pagina );

if ( '' !== $desde ) {
	$ntvs_volver['desde'] = $desde;
}
if ( '' !== $hasta ) {
	$ntvs_volver['hasta'] = $hasta;
}
if ( $todos ) {
	$ntvs_volver['incluir_invalidos'] = '1';
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Auditoría del intento', 'navidad-tvs' ); ?></h1>

	<p>
		<a class="button" href="<?php echo esc_url( add_query_arg( $ntvs_volver, admin_url( 'admin.php' ) ) ); ?>">
			&laquo; <?php esc_html_e( 'Volver al ranking', 'navidad-tvs' ); ?>
		</a>
	</p>

	<?php if ( 'descalificado' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Resultado descalificado.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'rehabilitado' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Resultado rehabilitado.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'error' === $aviso ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( '' !== $detalle ? $detalle : __( 'No se pudo aplicar el cambio.', 'navidad-tvs' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $score ) : ?>
		<div class="notice notice-error inline">
			<p><?php esc_html_e( 'Ese resultado no existe.', 'navidad-tvs' ); ?></p>
		</div>
	<?php else : ?>

		<h2><?php esc_html_e( 'Participante', 'navidad-tvs' ); ?></h2>
		<table class="widefat striped" style="max-width:720px">
			<tbody>
				<tr><th style="width:220px"><?php esc_html_e( 'Nombre digitado', 'navidad-tvs' ); ?></th><td><?php echo esc_html( $score['nombre'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Cédula', 'navidad-tvs' ); ?></th><td><code><?php echo esc_html( $score['cedula'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Teléfono', 'navidad-tvs' ); ?></th><td><code><?php echo esc_html( $score['telefono'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Ciudad', 'navidad-tvs' ); ?></th><td><?php echo esc_html( $score['ciudad_propietario'] . ' / ' . $score['departamento_propietario'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Jornada', 'navidad-tvs' ); ?></th><td><?php echo esc_html( $score['fecha_concurso'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Registrado', 'navidad-tvs' ); ?></th><td><?php echo esc_html( $score['creado_en'] ); ?> UTC</td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Resultado', 'navidad-tvs' ); ?></h2>
		<table class="widefat striped" style="max-width:720px">
			<tbody>
				<tr>
					<th style="width:220px"><?php esc_html_e( 'Distancia oficial', 'navidad-tvs' ); ?></th>
					<td><strong><?php echo esc_html( number_format_i18n( (int) $score['distancia_m'] ) ); ?> m</strong></td>
				</tr>
				<tr><th><?php esc_html_e( 'Recorrido sin bonos', 'navidad-tvs' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $score['distancia_base_m'] ) ); ?> m</td></tr>
				<tr><th><?php esc_html_e( 'Llaves', 'navidad-tvs' ); ?></th><td><?php echo (int) $score['items_recogidos']; ?></td></tr>
				<tr><th><?php esc_html_e( 'Impulsores', 'navidad-tvs' ); ?></th><td><?php echo (int) $score['impulsores']; ?></td></tr>
				<tr><th><?php esc_html_e( 'Caídas', 'navidad-tvs' ); ?></th><td><?php echo (int) $score['caidas']; ?></td></tr>
				<tr><th><?php esc_html_e( 'Sobrecalentamientos', 'navidad-tvs' ); ?></th><td><?php echo (int) $score['sobrecalentamientos']; ?></td></tr>
				<tr><th><?php esc_html_e( 'Duración', 'navidad-tvs' ); ?></th><td><?php echo (int) $score['duracion_s']; ?> s</td></tr>
				<tr><th><?php esc_html_e( 'Seed de la pista', 'navidad-tvs' ); ?></th><td><code><?php echo esc_html( $score['seed'] ); ?></code></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Reejecución', 'navidad-tvs' ); ?></h2>

		<?php if ( isset( $auditoria['error'] ) ) : ?>
			<div class="notice notice-error inline">
				<p>
					<strong><?php esc_html_e( 'El log de entradas no se pudo leer.', 'navidad-tvs' ); ?></strong><br>
					<?php echo esc_html( $auditoria['error'] ); ?>
				</p>
			</div>
		<?php elseif ( $auditoria['reproducible'] ) : ?>
			<div class="notice notice-success inline">
				<p>
					<strong><?php esc_html_e( 'El resultado es reproducible.', 'navidad-tvs' ); ?></strong><br>
					<?php
					printf(
						/* translators: 1: ticks simulados, 2: milisegundos */
						esc_html__( 'Se volvieron a simular los %1$s ticks con el mismo seed y el mismo log, y sale exactamente lo mismo que está guardado (%2$s ms).', 'navidad-tvs' ),
						esc_html( number_format_i18n( (int) $auditoria['ticks'] ) ),
						esc_html( number_format_i18n( $auditoria['ms'], 1 ) )
					);
					?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-error inline">
				<p>
					<strong><?php esc_html_e( 'El resultado NO se reproduce.', 'navidad-tvs' ); ?></strong><br>
					<?php esc_html_e( 'O el motor cambió entre la carrera y hoy, o alguien tocó la fila. Conviene revisarlo antes de premiar.', 'navidad-tvs' ); ?>
				</p>
				<table class="widefat striped" style="max-width:520px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campo', 'navidad-tvs' ); ?></th>
							<th><?php esc_html_e( 'Guardado', 'navidad-tvs' ); ?></th>
							<th><?php esc_html_e( 'Recalculado', 'navidad-tvs' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $auditoria['diferencias'] as $ntvs_campo => $ntvs_dif ) : ?>
							<tr>
								<td><code><?php echo esc_html( $ntvs_campo ); ?></code></td>
								<td><?php echo (int) $ntvs_dif['guardado']; ?></td>
								<td><?php echo (int) $ntvs_dif['recalculado']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( ! isset( $auditoria['error'] ) ) : ?>
			<p class="description" style="max-width:720px">
				<?php
				printf(
					/* translators: 1: distancia que reportó el navegador, 2: diferencia */
					esc_html__( 'El navegador reportó %1$s m, %2$s m respecto a la cifra oficial. Ese dato no decide nada y se guarda solo para auditar: una diferencia pequeña suele ser un bundle viejo en caché.', 'navidad-tvs' ),
					esc_html( number_format_i18n( (int) $score['distancia_cliente_m'] ) ),
					esc_html( sprintf( '%+d', (int) $auditoria['desfase_cliente'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Validez', 'navidad-tvs' ); ?></h2>

		<?php if ( $score['valido'] ) : ?>
			<p><?php esc_html_e( 'Este resultado cuenta para el ranking.', 'navidad-tvs' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'navidad_tvs_descalificar' ); ?>
				<input type="hidden" name="action" value="navidad_tvs_descalificar">
				<input type="hidden" name="id" value="<?php echo (int) $score['id']; ?>">
				<p>
					<label for="ntvs-motivo"><strong><?php esc_html_e( 'Motivo de la descalificación', 'navidad-tvs' ); ?></strong></label><br>
					<input type="text" id="ntvs-motivo" name="motivo" class="large-text" maxlength="255" required
						placeholder="<?php esc_attr_e( 'Por ejemplo: la reejecución no reproduce la distancia', 'navidad-tvs' ); ?>">
					<span class="description">
						<?php esc_html_e( 'Obligatorio. Una descalificación sin razón escrita no se puede defender delante del participante.', 'navidad-tvs' ); ?>
					</span>
				</p>
				<p>
					<button type="submit" class="button button-secondary"
						onclick="return confirm('<?php echo esc_js( __( 'El resultado dejará de contar para el ranking. Se puede deshacer. ¿Continuar?', 'navidad-tvs' ) ); ?>');">
						<?php esc_html_e( 'Descalificar este resultado', 'navidad-tvs' ); ?>
					</button>
				</p>
			</form>
		<?php else : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'Descalificado.', 'navidad-tvs' ); ?></strong>
					<?php if ( '' !== $score['motivo_descalificacion'] ) : ?>
						<br><?php echo esc_html( $score['motivo_descalificacion'] ); ?>
					<?php endif; ?>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'navidad_tvs_descalificar' ); ?>
				<input type="hidden" name="action" value="navidad_tvs_descalificar">
				<input type="hidden" name="id" value="<?php echo (int) $score['id']; ?>">
				<input type="hidden" name="rehabilitar" value="1">
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'Rehabilitar este resultado', 'navidad-tvs' ); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<?php
		/*
		 * El log de entradas se enseña, pero plegado. Es lo que permite
		 * reejecutar la carrera en cualquier momento, así que tiene que poder
		 * copiarse; y ocupa varios miles de caracteres, así que no puede estar
		 * desplegado por defecto.
		 */
		?>
		<h2><?php esc_html_e( 'Log de entradas', 'navidad-tvs' ); ?></h2>
		<details>
			<summary><?php esc_html_e( 'Ver el log en base64', 'navidad-tvs' ); ?></summary>
			<p class="description">
				<?php esc_html_e( 'Con este texto y el seed de arriba se puede volver a correr la carrera entera, hoy o dentro de un año.', 'navidad-tvs' ); ?>
			</p>
			<textarea class="large-text code" rows="6" readonly onclick="this.select()"><?php echo esc_textarea( $score['inputs'] ); ?></textarea>
		</details>
	<?php endif; ?>
</div>
