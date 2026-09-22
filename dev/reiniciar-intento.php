<?php
/**
 * Devuelve el intento a un participante, para poder volver a probar el juego.
 *
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/reiniciar-intento.php 3001234567
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/reiniciar-intento.php todos
 *
 * Borra sesiones y resultados. Es una herramienta de desarrollo: en producción
 * devolver un intento equivale a regalar una participación, y los términos y
 * condiciones dicen que no hay reintentos.
 *
 * @package NavidadTVS
 */

global $wpdb;

$db       = NavidadTVS_Plugin::instancia()->database;
$objetivo = isset( $args[0] ) ? (string) $args[0] : '';

if ( '' === $objetivo ) {
	echo "Falta el teléfono, o la palabra 'todos'.\n";
	return;
}

if ( 'todos' === strtolower( $objetivo ) ) {
	$scores   = $wpdb->query( "DELETE FROM {$db->tabla_scores}" );
	$sesiones = $wpdb->query( "DELETE FROM {$db->tabla_sesiones}" );
	printf( "Reiniciados todos los intentos: %d resultados y %d sesiones borradas.\n", (int) $scores, (int) $sesiones );
	return;
}

$telefono = NavidadTVS_Plugin::normalizar_telefono( $objetivo );

if ( '' === $telefono ) {
	printf( "«%s» no es un celular colombiano válido.\n", $objetivo );
	return;
}

$participante = $db->buscar_participante_por_telefono( $telefono );

if ( null === $participante ) {
	printf( "El teléfono %s no está en el padrón.\n", $telefono );
	return;
}

$id       = (int) $participante['id'];
$scores   = $wpdb->query( $wpdb->prepare( "DELETE FROM {$db->tabla_scores} WHERE participante_id = %d", $id ) );
$sesiones = $wpdb->query( $wpdb->prepare( "DELETE FROM {$db->tabla_sesiones} WHERE participante_id = %d", $id ) );

printf(
	"Intento devuelto a %s (jornada %s): %d resultados y %d sesiones borradas.\n",
	$telefono,
	$participante['fecha_concurso'],
	(int) $scores,
	(int) $sesiones
);
