<?php
/**
 * Ranking del concurso.
 *
 * Variables de NavidadTVS_Ranking::render(): $database, $settings, $filas,
 * $total, $pagina, $paginas, $por_pagina, $desde, $hasta, $todos, $corte,
 * $una_jornada, $aviso, $detalle.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ntvs_pagina = NavidadTVS_Admin::SLUG . '-ranking';
$ntvs_base   = array( 'page' => $ntvs_pagina );

if ( '' !== $desde ) {
	$ntvs_base['desde'] = $desde;
}
if ( '' !== $hasta ) {
	$ntvs_base['hasta'] = $hasta;
}
if ( $todos ) {
	$ntvs_base['incluir_invalidos'] = '1';
}

// La posición arranca donde termina la página anterior.
$ntvs_posicion = ( $pagina - 1 ) * $por_pagina;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Ranking', 'navidad-tvs' ); ?></h1>

	<?php if ( 'descalificado' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Resultado descalificado.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'rehabilitado' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Resultado rehabilitado.', 'navidad-tvs' ); ?></p></div>
	<?php elseif ( 'ganadores' === $aviso ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Ganadores guardados.', 'navidad-tvs' ); ?> <?php echo esc_html( $detalle ); ?></p>
		</div>
	<?php elseif ( 'error' === $aviso ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( '' !== $detalle ? $detalle : __( 'No se pudo aplicar el cambio.', 'navidad-tvs' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( $settings->get( 'concurso_congelado' ) ) : ?>
		<div class="notice notice-warning inline">
			<p><strong><?php esc_html_e( 'El concurso está congelado: nadie puede jugar.', 'navidad-tvs' ); ?></strong></p>
		</div>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( $ntvs_pagina ); ?>">
		<p>
			<label for="ntvs-desde"><?php esc_html_e( 'Desde', 'navidad-tvs' ); ?></label>
			<input type="date" id="ntvs-desde" name="desde" value="<?php echo esc_attr( $desde ); ?>">

			<label for="ntvs-hasta"><?php esc_html_e( 'Hasta', 'navidad-tvs' ); ?></label>
			<input type="date" id="ntvs-hasta" name="hasta" value="<?php echo esc_attr( $hasta ); ?>">

			<label>
				<input type="checkbox" name="incluir_invalidos" value="1" <?php checked( $todos ); ?>>
				<?php esc_html_e( 'Incluir descalificados', 'navidad-tvs' ); ?>
			</label>

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'navidad-tvs' ); ?></button>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => $ntvs_pagina ), admin_url( 'admin.php' ) ) ); ?>">
				<?php esc_html_e( 'Quitar filtros', 'navidad-tvs' ); ?>
			</a>
		</p>
	</form>

	<p>
		<strong>
			<?php
			printf(
				/* translators: %s: cantidad de resultados */
				esc_html__( '%s resultados', 'navidad-tvs' ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</strong>
		<?php if ( '' !== $desde || '' !== $hasta ) : ?>
			<?php
			printf(
				/* translators: 1: fecha desde, 2: fecha hasta */
				esc_html__( ' entre %1$s y %2$s', 'navidad-tvs' ),
				esc_html( '' !== $desde ? $desde : '—' ),
				esc_html( '' !== $hasta ? $hasta : '—' )
			);
			?>
		<?php endif; ?>
	</p>

	<p>
		<a class="button" href="<?php
		echo esc_url(
			wp_nonce_url(
				add_query_arg(
					array_merge( $ntvs_base, array( 'action' => 'navidad_tvs_exportar_ranking', 'page' => null ) ),
					admin_url( 'admin-post.php' )
				),
				'navidad_tvs_exportar_ranking'
			)
		);
		?>"><?php esc_html_e( 'Exportar este ranking (CSV)', 'navidad-tvs' ); ?></a>

		<a class="button" href="<?php
		echo esc_url(
			wp_nonce_url(
				add_query_arg(
					array_merge( $ntvs_base, array( 'action' => 'navidad_tvs_exportar_padron', 'page' => null, 'incluir_invalidos' => null ) ),
					admin_url( 'admin-post.php' )
				),
				'navidad_tvs_exportar_padron'
			)
		);
		?>"><?php esc_html_e( 'Exportar padrón con participación (CSV)', 'navidad-tvs' ); ?></a>

		<a class="button" href="<?php
		echo esc_url(
			wp_nonce_url(
				add_query_arg(
					array_merge( $ntvs_base, array( 'action' => 'navidad_tvs_exportar_ganadores', 'page' => null, 'incluir_invalidos' => null ) ),
					admin_url( 'admin-post.php' )
				),
				'navidad_tvs_exportar_ganadores'
			)
		);
		?>"><?php esc_html_e( 'Exportar ganadores (CSV)', 'navidad-tvs' ); ?></a>
	</p>

	<?php
	/*
	 * Los dos informes de campaña van sin filtro de fechas a propósito: son
	 * el cierre, y un cierre parcial no cierra nada.
	 */
	?>
	<p>
		<a class="button" href="<?php
		echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'navidad_tvs_informe_general' ), admin_url( 'admin-post.php' ) ), 'navidad_tvs_informe_general' ) );
		?>"><?php esc_html_e( 'Informe general de la campaña (CSV)', 'navidad-tvs' ); ?></a>

		<a class="button" href="<?php
		echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'navidad_tvs_informe_ejecutivo' ), admin_url( 'admin-post.php' ) ), 'navidad_tvs_informe_ejecutivo' ) );
		?>"><?php esc_html_e( 'Informe ejecutivo (CSV)', 'navidad-tvs' ); ?></a>

		<span class="description">
			<?php esc_html_e( 'Estos dos cubren toda la campaña, sin filtro. El general lleva una fila por persona; el ejecutivo, los totales y el desglose por día listos para graficar.', 'navidad-tvs' ); ?>
		</span>
	</p>

	<?php if ( $una_jornada && null !== $corte ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: cantidad de premios, 2: distancia de corte */
				esc_html__( 'Puestos premiados de la jornada: los %1$d primeros. El corte está en %2$s m, y por reglamento entran TODOS los que igualen esa marca.', 'navidad-tvs' ),
				(int) $settings->get( 'premios_por_jornada', 4 ),
				esc_html( number_format_i18n( $corte ) )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( empty( $filas ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Todavía no hay resultados con esos filtros.', 'navidad-tvs' ); ?></p>
		</div>
	<?php else : ?>
		<?php
		/*
		 * La tabla es un formulario porque marcar ganadores se hace aquí.
		 *
		 * Es el único sitio donde tiene sentido: el reglamento premia a los
		 * cuatro primeros y a los empatados con el cuarto, así que la decisión
		 * se toma mirando la lista ordenada, no fila por fila.
		 *
		 * El campo oculto "mostrados" lleva los identificadores que hay en
		 * pantalla. Sin él, guardar en la página 2 borraría los ganadores de
		 * la 1, porque una casilla sin marcar no viaja en el POST y no habría
		 * forma de distinguir "la desmarcó" de "no estaba en pantalla".
		 */
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'navidad_tvs_ganadores' ); ?>
			<input type="hidden" name="action" value="navidad_tvs_ganadores">
			<input type="hidden" name="desde" value="<?php echo esc_attr( $desde ); ?>">
			<input type="hidden" name="hasta" value="<?php echo esc_attr( $hasta ); ?>">
			<input type="hidden" name="paged" value="<?php echo (int) $pagina; ?>">
			<?php if ( $todos ) : ?>
				<input type="hidden" name="incluir_invalidos" value="1">
			<?php endif; ?>

		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:50px"><?php esc_html_e( '#', 'navidad-tvs' ); ?></th>
					<th style="width:60px"><?php esc_html_e( 'ID', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Nombre', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Cédula', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Teléfono', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Ciudad', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Departamento', 'navidad-tvs' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Distancia', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Jornada', 'navidad-tvs' ); ?></th>
					<th style="width:210px"><?php esc_html_e( 'Ganador', 'navidad-tvs' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $filas as $ntvs_f ) : ?>
					<?php
					$ntvs_posicion++;
					$ntvs_premiado = null !== $corte && (int) $ntvs_f['distancia_m'] >= $corte && $ntvs_f['valido'];
					$ntvs_gana     = ! empty( $ntvs_f['ganador'] );
					?>
					<tr<?php echo $ntvs_gana ? ' style="background:#e7f6e7"' : ( $ntvs_f['valido'] ? '' : ' style="opacity:.55"' ); ?>>
						<td>
							<?php echo esc_html( $ntvs_posicion ); ?>
							<?php if ( $ntvs_premiado ) : ?>
								<span title="<?php esc_attr_e( 'Dentro de los puestos premiados', 'navidad-tvs' ); ?>">★</span>
							<?php endif; ?>
						</td>
						<td><?php echo (int) $ntvs_f['id']; ?></td>
						<td><?php echo esc_html( $ntvs_f['nombre'] ); ?></td>
						<td><code><?php echo esc_html( $ntvs_f['cedula'] ); ?></code></td>
						<td><code><?php echo esc_html( $ntvs_f['telefono'] ); ?></code></td>
						<td><?php echo esc_html( $ntvs_f['ciudad_propietario'] ); ?></td>
						<td><?php echo esc_html( $ntvs_f['departamento_propietario'] ); ?></td>
						<td style="text-align:right">
							<strong><?php echo esc_html( number_format_i18n( (int) $ntvs_f['distancia_m'] ) ); ?></strong> m
						</td>
						<td>
							<?php echo esc_html( $ntvs_f['fecha_concurso'] ); ?>
							<?php if ( ! $ntvs_f['valido'] ) : ?>
								<br><span class="description"><?php esc_html_e( 'Descalificado', 'navidad-tvs' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<input type="hidden" name="mostrados[]" value="<?php echo (int) $ntvs_f['id']; ?>">
							<?php if ( $ntvs_f['valido'] ) : ?>
								<label>
									<input type="checkbox" name="ganador[]" value="<?php echo (int) $ntvs_f['id']; ?>"
										<?php checked( ! empty( $ntvs_f['ganador'] ) ); ?>>
									<?php esc_html_e( 'Gana', 'navidad-tvs' ); ?>
								</label>
								<input type="text" name="nota[<?php echo (int) $ntvs_f['id']; ?>]" class="small-text"
									value="<?php echo esc_attr( $ntvs_f['ganador_nota'] ); ?>"
									placeholder="<?php esc_attr_e( 'premio', 'navidad-tvs' ); ?>" maxlength="255">
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'descalificado', 'navidad-tvs' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a class="button button-small" href="<?php
							echo esc_url( add_query_arg( array_merge( $ntvs_base, array( 'ver' => (int) $ntvs_f['id'] ) ), admin_url( 'admin.php' ) ) );
							?>"><?php esc_html_e( 'Auditar', 'navidad-tvs' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

			<?php submit_button( __( 'Guardar ganadores de esta página', 'navidad-tvs' ) ); ?>
		</form>

		<?php if ( $paginas > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( array_merge( $ntvs_base, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
							'format'    => '',
							'current'   => $pagina,
							'total'     => $paginas,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
