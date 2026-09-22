<?php
// Abre o cierra la ventana de participacion para probar en local.
//   ... eval-file dev/abrir-ventana.php abrir
//   ... eval-file dev/abrir-ventana.php cerrar
$accion   = isset( $args[0] ) ? $args[0] : 'abrir';
$settings = NavidadTVS_Plugin::instancia()->settings;

if ( 'cerrar' === $accion ) {
	$settings->set( array( 'hora_inicio' => '12:00', 'hora_fin' => '13:30', 'dias_habiles' => array( 1, 2, 3, 4, 5, 6 ) ) );
	echo "Ventana restaurada a 12:00-13:30, lunes a sabado.\n";
} else {
	$ahora = NavidadTVS_Plugin::ahora();
	$settings->set(
		array(
			'hora_inicio'  => $ahora->modify( '-60 minutes' )->format( 'H:i' ),
			'hora_fin'     => $ahora->modify( '+180 minutes' )->format( 'H:i' ),
			'dias_habiles' => array( 1, 2, 3, 4, 5, 6, 7 ),
		)
	);
	printf( "Ventana abierta: %s a %s (hora Colombia: %s)\n", $settings->get( 'hora_inicio' ), $settings->get( 'hora_fin' ), $ahora->format( 'H:i' ) );
}
