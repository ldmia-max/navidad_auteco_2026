<?php
/**
 * Página de estado del concurso.
 *
 * Variables disponibles desde NavidadTVS_Admin::render_estado():
 *
 * @var string               $hoy      Fecha de hoy en la zona del concurso.
 * @var bool                 $abierta  Si la ventana de participación está abierta.
 * @var NavidadTVS_Settings  $settings Configuración.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$database     = NavidadTVS_Plugin::instancia()->database;
$tablas       = $database->estado_tablas();
$total_padron = $database->contar_participantes();
$padron_hoy   = $database->contar_participantes( $hoy );
$scores_hoy   = $database->contar_scores( $hoy );

$nombres_dias = array(
	1 => __( 'Lun', 'navidad-tvs' ),
	2 => __( 'Mar', 'navidad-tvs' ),
	3 => __( 'Mié', 'navidad-tvs' ),
	4 => __( 'Jue', 'navidad-tvs' ),
	5 => __( 'Vie', 'navidad-tvs' ),
	6 => __( 'Sáb', 'navidad-tvs' ),
	7 => __( 'Dom', 'navidad-tvs' ),
);

$dias_activos = array();
foreach ( (array) $settings->get( 'dias_habiles' ) as $d ) {
	if ( isset( $nombres_dias[ $d ] ) ) {
		$dias_activos[] = $nombres_dias[ $d ];
	}
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Concurso Navideño TVS', 'navidad-tvs' ); ?></h1>

	<?php if ( $settings->get( 'concurso_congelado' ) ) : ?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'El concurso está congelado.', 'navidad-tvs' ); ?></strong>
			<?php esc_html_e( 'Nadie puede participar, aunque la hora esté dentro de la ventana.', 'navidad-tvs' ); ?></p>
		</div>
	<?php elseif ( $abierta ) : ?>
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'La ventana de participación está abierta.', 'navidad-tvs' ); ?></strong></p>
		</div>
	<?php else : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'La ventana de participación está cerrada en este momento.', 'navidad-tvs' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Jornada de hoy', 'navidad-tvs' ); ?></h2>
	<table class="widefat striped" style="max-width:640px">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fecha (hora Colombia)', 'navidad-tvs' ); ?></th>
				<td><?php echo esc_html( $hoy ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Horario', 'navidad-tvs' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: 1: hora de inicio, 2: hora de fin, 3: días hábiles */
						esc_html__( '%1$s a %2$s · %3$s', 'navidad-tvs' ),
						esc_html( $settings->get( 'hora_inicio' ) ),
						esc_html( $settings->get( 'hora_fin' ) ),
						esc_html( implode( ', ', $dias_activos ) )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Habilitados hoy', 'navidad-tvs' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $padron_hoy ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ya participaron hoy', 'navidad-tvs' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $scores_hoy ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Premios por jornada', 'navidad-tvs' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( (int) $settings->get( 'premios_por_jornada' ) ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Padrón', 'navidad-tvs' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: %s: total de participantes en el padrón */
			esc_html__( 'Total de participantes importados: %s', 'navidad-tvs' ),
			'<strong>' . esc_html( number_format_i18n( $total_padron ) ) . '</strong>'
		);
		?>
	</p>

	<h2><?php esc_html_e( 'Estado de las tablas', 'navidad-tvs' ); ?></h2>
	<table class="widefat striped" style="max-width:640px">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Tabla', 'navidad-tvs' ); ?></th>
				<th><?php esc_html_e( 'Estado', 'navidad-tvs' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $tablas as $nombre => $existe ) : ?>
			<tr>
				<td><code><?php echo esc_html( $nombre ); ?></code></td>
				<td>
					<?php if ( $existe ) : ?>
						<span style="color:#1a7f37">&#10003; <?php esc_html_e( 'creada', 'navidad-tvs' ); ?></span>
					<?php else : ?>
						<span style="color:#b32d2e">&#10007; <?php esc_html_e( 'falta', 'navidad-tvs' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php
		printf(
			/* translators: %s: versión del plugin */
			esc_html__( 'Versión del plugin: %s', 'navidad-tvs' ),
			esc_html( NAVIDAD_TVS_VERSION )
		);
		?>
	</p>
</div>
