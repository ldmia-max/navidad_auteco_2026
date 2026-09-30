<?php
/**
 * Página de importación del padrón.
 *
 * Variables disponibles desde NavidadTVS_Admin::render_importar():
 *
 * @var array|false          $resultado Resultado de la última importación.
 * @var NavidadTVS_Database  $database  Acceso a datos.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jornadas_padron = $database->resumen_por_jornada( 30 );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Importar padrón', 'navidad-tvs' ); ?></h1>

	<?php if ( is_array( $resultado ) && ! empty( $resultado['error'] ) ) : ?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'No se pudo importar.', 'navidad-tvs' ); ?></strong></p>
			<p><?php echo esc_html( $resultado['error'] ); ?></p>
		</div>
	<?php elseif ( is_array( $resultado ) && isset( $resultado['insertadas'] ) ) : ?>
		<?php
		$insertadas = (int) $resultado['insertadas'];
		$rechazadas = (int) $resultado['rechazadas'];
		$clase      = $rechazadas > 0 ? ( $insertadas > 0 ? 'notice-warning' : 'notice-error' ) : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr( $clase ); ?>">
			<p>
				<strong><?php echo esc_html( $resultado['archivo'] ); ?></strong><br>
				<?php
				printf(
					/* translators: 1: filas importadas, 2: filas rechazadas */
					esc_html__( 'Importadas: %1$s · Rechazadas: %2$s', 'navidad-tvs' ),
					'<strong>' . esc_html( number_format_i18n( $insertadas ) ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( $rechazadas ) ) . '</strong>'
				);
				?>
			</p>

			<?php if ( ! empty( $resultado['jornadas'] ) ) : ?>
				<p>
					<?php esc_html_e( 'Jornadas del archivo:', 'navidad-tvs' ); ?>
					<?php
					$partes = array();
					foreach ( $resultado['jornadas'] as $fecha => $cuantos ) {
						$partes[] = $fecha . ' (' . number_format_i18n( $cuantos ) . ')';
					}
					echo esc_html( implode( ' · ', $partes ) );
					?>
				</p>
				<?php if ( count( $resultado['jornadas'] ) > 1 ) : ?>
					<p class="description">
						<?php esc_html_e( 'Este archivo trae más de una jornada. Normalmente el área comercial envía un archivo por día; vale la pena confirmar que es correcto.', 'navidad-tvs' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $rechazadas > 0 ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=navidad_tvs_descargar_rechazos' ), 'navidad_tvs_descargar_rechazos' ) ); ?>">
						<?php esc_html_e( 'Descargar reporte de rechazos (CSV)', 'navidad-tvs' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * Aqui iba la tabla con las filas rechazadas una por una.
		 *
		 * Se quito a peticion del cliente: con un padron de varios cientos de
		 * filas, una lista de rechazos llena la pantalla y no se puede hacer nada
		 * con ella, porque para corregir hay que volver al CSV de origen. El
		 * numero de rechazadas sigue arriba, en el aviso, y el reporte completo
		 * se descarga con el boton: eso si sirve, porque se abre al lado del
		 * archivo original.
		 *
		 * OJO: el array $resultado['rechazos'] NO se toco. Lo necesita
		 * descargar_rechazos() para armar el CSV; lo que desaparece es la vista,
		 * no el dato.
		 */
		?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Cargar archivo', 'navidad-tvs' ); ?></h2>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'navidad_tvs_importar_padron' ); ?>
		<input type="hidden" name="action" value="navidad_tvs_importar_padron">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="archivo"><?php esc_html_e( 'Archivo CSV', 'navidad-tvs' ); ?></label>
				</th>
				<td>
					<input type="file" id="archivo" name="archivo" accept=".csv,text/csv" required>
					<p class="description">
						<?php esc_html_e( 'Separador ; o , — se detecta solo. Acepta archivos guardados desde Excel como CSV UTF-8.', 'navidad-tvs' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Importar padrón', 'navidad-tvs' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Formato esperado', 'navidad-tvs' ); ?></h2>
	<p><?php esc_html_e( 'La primera fila son las cabeceras. Las obligatorias son:', 'navidad-tvs' ); ?></p>
	<p><code>telefono;cedula;placa;fecha_concurso;marca</code></p>
	<p><?php esc_html_e( 'Opcionales, se guardan si vienen:', 'navidad-tvs' ); ?></p>
	<p><code>fecha_matricula;fecha_acta;ciudad_propietario;departamento_propietario;razon_social_establecimiento</code></p>

	<h3><?php esc_html_e( 'Reglas de validación', 'navidad-tvs' ); ?></h3>
	<ul class="ul-disc">
		<li>
			<?php
			printf(
				/* translators: %s: marca admitida */
				esc_html__( 'Solo se importan las filas de marca %s. Las demás se rechazan con su motivo.', 'navidad-tvs' ),
				'<strong>' . esc_html( NAVIDAD_TVS_MARCA ) . '</strong>'
			);
			?>
		</li>
		<li><?php esc_html_e( 'El teléfono debe ser un celular colombiano: 10 dígitos que empiecen por 3. Se acepta con o sin el indicativo 57.', 'navidad-tvs' ); ?></li>
		<li><?php esc_html_e( 'No se admiten teléfonos, cédulas ni placas repetidos, ni dentro del archivo ni contra lo ya importado.', 'navidad-tvs' ); ?></li>
		<li><?php esc_html_e( 'La fecha de concurso es la única jornada en la que esa persona puede jugar.', 'navidad-tvs' ); ?></li>
		<li><?php esc_html_e( 'La importación es acumulativa: no borra lo anterior ni afecta los resultados ya registrados.', 'navidad-tvs' ); ?></li>
	</ul>

	<?php if ( ! empty( $jornadas_padron ) ) : ?>
		<h2><?php esc_html_e( 'Padrón actual por jornada', 'navidad-tvs' ); ?></h2>
		<table class="widefat striped" style="max-width:520px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Jornada', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Habilitados', 'navidad-tvs' ); ?></th>
					<th><?php esc_html_e( 'Participaron', 'navidad-tvs' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $jornadas_padron as $j ) : ?>
				<tr>
					<td><?php echo esc_html( $j['fecha_concurso'] ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $j['total'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $j['jugaron'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
