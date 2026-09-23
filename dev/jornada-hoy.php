<?php
/**
 * Mueve una jornada del padrón a la fecha de hoy, para poder probar.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/jornada-hoy.php
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/jornada-hoy.php 2026-12-01
 *
 * Sin argumento mueve la jornada más grande. El padrón de ejemplo se importó
 * con la fecha del día en que se cargó, así que al día siguiente nadie es
 * elegible y el formulario responde "no habilitado" a todo el mundo, que es lo
 * correcto pero impide probar.
 *
 * Herramienta de desarrollo. En producción cambiar la jornada de alguien
 * equivale a moverle el día de participación.
 *
 * @package NavidadTVS
 */

global $wpdb;

$db  = NavidadTVS_Plugin::instancia()->database;
$hoy = NavidadTVS_Plugin::hoy();

$origen = isset( $args[0] ) ? sanitize_text_field( $args[0] ) : '';

if ( '' === $origen ) {
	$origen = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT fecha_concurso
			   FROM {$db->tabla_participantes}
			  WHERE fecha_concurso <> %s
			  GROUP BY fecha_concurso
			  ORDER BY COUNT(*) DESC
			  LIMIT 1",
			$hoy
		)
	);
}

if ( ! $origen ) {
	$cuantos = (int) $db->contar_participantes( $hoy );
	printf( "No hay otra jornada que mover. Hoy (%s) ya tiene %d habilitados.\n", $hoy, $cuantos );
	return;
}

$movidos = $wpdb->query(
	$wpdb->prepare(
		"UPDATE {$db->tabla_participantes} SET fecha_concurso = %s WHERE fecha_concurso = %s",
		$hoy,
		$origen
	)
);

printf( "Jornada %s movida a hoy (%s): %d participantes.\n", $origen, $hoy, (int) $movidos );

// Un teléfono de muestra para poder entrar sin buscarlo a mano.
$telefono = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT telefono FROM {$db->tabla_participantes} WHERE fecha_concurso = %s LIMIT 1",
		$hoy
	)
);

if ( $telefono ) {
	printf( "Teléfono de prueba: %s\n", $telefono );
}
