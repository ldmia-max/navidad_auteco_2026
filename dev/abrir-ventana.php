<?php
// Abre o cierra la ventana de participacion para probar en local.
//   ... eval-file dev/abrir-ventana.php abrir
//   ... eval-file dev/abrir-ventana.php abrir 09:00 20:00
//   ... eval-file dev/abrir-ventana.php cerrar
//
// "abrir" deja la ventana de 8:00 a 18:00 hora Colombia, todos los dias de la
// semana, que es el horario de trabajo mientras dura el desarrollo. Antes ponia
// una franja relativa a la hora actual y habia que volver a correrlo cada rato.
//
// "cerrar" restaura el horario real del concurso: 12:00-13:30, lunes a sabado.
// Hay que correrlo antes de entregar, o la ventana de produccion queda abierta
// todo el dia y los domingos.
$accion   = isset( $args[0] ) ? $args[0] : 'abrir';
$settings = NavidadTVS_Plugin::instancia()->settings;

if ( 'cerrar' === $accion ) {
	$settings->set( array( 'hora_inicio' => '12:00', 'hora_fin' => '13:30', 'dias_habiles' => array( 1, 2, 3, 4, 5, 6 ) ) );
	echo "Ventana restaurada al horario del concurso: 12:00-13:30, lunes a sabado.\n";
} else {
	$inicio = isset( $args[1] ) ? $args[1] : '08:00';
	$fin    = isset( $args[2] ) ? $args[2] : '18:00';

	if ( ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $inicio ) || ! preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $fin ) ) {
		echo "Las horas van en formato HH:MM de 24 horas. Ejemplo: abrir 08:00 18:00\n";
		return;
	}

	$settings->set(
		array(
			'hora_inicio'  => $inicio,
			'hora_fin'     => $fin,
			'dias_habiles' => array( 1, 2, 3, 4, 5, 6, 7 ),
		)
	);

	$ahora = NavidadTVS_Plugin::ahora();
	printf(
		"Ventana de desarrollo: %s a %s, todos los dias (hora Colombia ahora: %s).\n",
		$settings->get( 'hora_inicio' ),
		$settings->get( 'hora_fin' ),
		$ahora->format( 'H:i' )
	);
	echo "Recordar: correr 'abrir-ventana.php cerrar' antes de entregar.\n";
}
