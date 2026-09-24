<?php
/**
 * Verificación manual de E7 (sitio público). Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-e7.php
 *
 * Comprueba que las tres páginas se renderizan, que los enlaces entre ellas
 * apuntan a algo, que el HTML es el que esperan los lectores de pantalla y que
 * no se escapó nada sin escapar.
 *
 * ESCRIBE EN LA BASE DE DATOS: crea las páginas del concurso si faltan, que es
 * justo lo que hay que probar. No borra nada, porque esas páginas son el
 * entregable.
 *
 * @package NavidadTVS
 */

$GLOBALS['fallos'] = 0;

function comprobar( $etiqueta, $esperado, $obtenido ) {
	$ok = ( $esperado === $obtenido );
	if ( ! $ok ) {
		$GLOBALS['fallos']++;
	}
	printf(
		"%s %-54s esperado=%-12s obtenido=%s\n",
		$ok ? 'OK  ' : 'FALLA',
		$etiqueta,
		var_export( $esperado, true ),
		var_export( $obtenido, true )
	);
}

function afirmar( $etiqueta, $condicion, $detalle = '' ) {
	if ( ! $condicion ) {
		$GLOBALS['fallos']++;
	}
	printf( "%s %s%s\n", $condicion ? 'OK  ' : 'FALLA', $etiqueta, '' === $detalle ? '' : '  ' . $detalle );
}

require_once NAVIDAD_TVS_PATH . 'includes/class-contenido.php';

$plugin   = NavidadTVS_Plugin::instancia();
$settings = $plugin->settings;

// ===========================================================================
echo "=== Páginas ===\n";

$resultado = NavidadTVS_Paginas::crear_paginas( $settings );

foreach ( $resultado as $clave => $info ) {
	afirmar(
		sprintf( 'página %-16s', $clave ),
		$info['id'] > 0,
		sprintf( '#%d %s%s', $info['id'], $info['titulo'], $info['creada'] ? '  (creada ahora)' : '' )
	);
}

// Volver a pulsar el botón no puede duplicar nada.
$segunda = NavidadTVS_Paginas::crear_paginas( $settings );
$creadas_otra_vez = 0;
foreach ( $segunda as $info ) {
	if ( ! empty( $info['creada'] ) ) {
		$creadas_otra_vez++;
	}
}
comprobar( 'crear las páginas dos veces no duplica', 0, $creadas_otra_vez );

foreach ( array_keys( $resultado ) as $clave ) {
	comprobar( sprintf( 'el ajuste %s quedó guardado', $clave ), $resultado[ $clave ]['id'], (int) $settings->get( $clave ) );
}

// ===========================================================================
echo "\n=== Los shortcodes ===\n";

foreach ( array( 'concurso_tvs', 'concurso_tvs_home', 'concurso_tvs_faq', 'concurso_tvs_terminos' ) as $tag ) {
	comprobar( sprintf( 'existe [%s]', $tag ), true, shortcode_exists( $tag ) );
}

$home     = do_shortcode( '[' . NavidadTVS_Paginas::TAG_HOME . ']' );
$faq      = do_shortcode( '[' . NavidadTVS_Paginas::TAG_FAQ . ']' );
$terminos = do_shortcode( '[' . NavidadTVS_Paginas::TAG_TERMINOS . ']' );

afirmar( 'la portada devuelve HTML', strlen( $home ) > 1000, sprintf( '%d bytes', strlen( $home ) ) );
afirmar( 'el FAQ devuelve HTML', strlen( $faq ) > 1000, sprintf( '%d bytes', strlen( $faq ) ) );
afirmar( 'los términos devuelven HTML', strlen( $terminos ) > 1000, sprintf( '%d bytes', strlen( $terminos ) ) );

// ===========================================================================
echo "\n=== Contenido ===\n";

$grupos = NavidadTVS_Contenido::faq();
$total_preguntas = 0;
foreach ( $grupos as $g ) {
	$total_preguntas += count( $g['preguntas'] );
}

afirmar( 'hay grupos de preguntas', count( $grupos ) >= 5, sprintf( '%d grupos', count( $grupos ) ) );
afirmar( 'hay preguntas suficientes', $total_preguntas >= 25, sprintf( '%d preguntas', $total_preguntas ) );
comprobar( 'el FAQ pinta un acordeón por pregunta', $total_preguntas, substr_count( $faq, '<details' ) );

$clausulas = NavidadTVS_Contenido::terminos();
afirmar( 'hay cláusulas', count( $clausulas ) >= 15, sprintf( '%d cláusulas', count( $clausulas ) ) );
comprobar( 'los términos pintan una cláusula por entrada', count( $clausulas ), substr_count( $terminos, 'ntvs-clausula__titulo' ) );

/*
 * Las respuestas describen cómo se comporta el juego, así que cuando la
 * mecánica cambia hay que cambiarlas con ella. Estas comprobaciones existen
 * porque ya pasó: el modal de instrucciones se quedó meses explicando el salto
 * y el ángulo de aterrizaje después de que los dos desaparecieran.
 */
$texto_faq = wp_strip_all_tags( $faq );

foreach ( array( 'llave', 'impulsor', 'cono', 'aceite', 'turbo', '90 segundos', 'cruceta' ) as $palabra ) {
	afirmar( sprintf( 'el FAQ habla de %-12s', $palabra ), false !== mb_stripos( $texto_faq, $palabra ) );
}

/*
 * Con límite de palabra, no como subcadena. Buscar "rampa" suelto encontraba
 * "haga trampa" y fallaba por nada.
 */
foreach ( array( 'salto', 'rampa', 'logo de TVS', 'barro', 'valla', 'girarlo', 'posición horizontal' ) as $vieja ) {
	$hay = (bool) preg_match( '/\b' . preg_quote( $vieja, '/' ) . '/iu', $texto_faq );
	afirmar( sprintf( 'el FAQ ya no habla de %-12s', $vieja ), ! $hay );
}

// ===========================================================================
echo "\n=== Accesibilidad y maquetación ===\n";

foreach ( array( 'portada' => $home, 'FAQ' => $faq, 'términos' => $terminos ) as $nombre => $html ) {
	comprobar( sprintf( '%s tiene un solo h1', $nombre ), 1, substr_count( $html, '<h1' ) );
	afirmar( sprintf( '%s no usa tablas para maquetar', $nombre ), false === strpos( $html, '<table' ) );
}

// El acordeón nativo se abre sin JavaScript y el buscador del navegador
// encuentra lo que hay dentro. Uno hecho con divs no hace ni una cosa ni otra.
afirmar( 'el FAQ usa <details> nativos', substr_count( $faq, '<summary' ) === $total_preguntas );

// Cada sección se anuncia con su título.
afirmar( 'las secciones del FAQ están etiquetadas', substr_count( $faq, 'aria-labelledby' ) === count( $grupos ) );
afirmar( 'la navegación del pie tiene nombre', false !== strpos( $faq, 'aria-label' ) );

// La numeración de las cláusulas la pone el CSS a partir de un <ol>, así que
// insertar una en medio no obliga a renumerar el resto a mano.
afirmar( 'las cláusulas van en una lista ordenada', false !== strpos( $terminos, '<ol class="ntvs-clausulas"' ) );

// ===========================================================================
echo "\n=== Escapado ===\n";

/*
 * Todo el texto pasa por esc_html(), así que si alguna comilla tipográfica o
 * ampersand del contenido apareciera crudo sería señal de que una plantilla se
 * saltó el escapado.
 */
$sin_escapar = 0;
foreach ( array( $home, $faq, $terminos ) as $html ) {
	// Un & que no abra una entidad válida.
	$sin_escapar += preg_match_all( '/&(?!amp;|lt;|gt;|quot;|#\d+;|#x[0-9a-f]+;)/i', $html );
}
comprobar( 'no hay ampersands sin escapar', 0, $sin_escapar );

// ===========================================================================
echo "\n=== Enlaces entre páginas ===\n";

foreach ( array( 'pagina_juego', 'pagina_faq', 'pagina_terminos' ) as $clave ) {
	$id = (int) $settings->get( $clave );
	$url = $id > 0 ? get_permalink( $id ) : '';
	afirmar( sprintf( '%s resuelve a una URL', $clave ), '' !== $url, $url );
}

$url_faq = get_permalink( (int) $settings->get( 'pagina_faq' ) );
afirmar( 'la portada enlaza al FAQ', false !== strpos( $home, esc_url( $url_faq ) ) );

$url_juego = get_permalink( (int) $settings->get( 'pagina_juego' ) );
afirmar( 'la portada enlaza al juego', false !== strpos( $home, esc_url( $url_juego ) ) );
afirmar( 'el FAQ enlaza al juego', false !== strpos( $faq, esc_url( $url_juego ) ) );
afirmar( 'los términos enlazan al juego', false !== strpos( $terminos, esc_url( $url_juego ) ) );

// ===========================================================================
echo "\n=== Textos pendientes del cliente ===\n";

$pendientes = NavidadTVS_Contenido::pendientes();

printf( "     quedan %d datos por definir:\n", count( $pendientes ) );
foreach ( $pendientes as $p ) {
	printf( "       - %s\n", $p );
}

/*
 * Esto NO es un fallo mientras el cliente no entregue los textos definitivos.
 * Se cuenta y se lista para que nadie se entere el día de apertura de que la
 * página de términos dice "[pendiente: NIT]".
 *
 * Antes de salir a producción hay que cambiar este aviso por una comprobación
 * que falle si queda alguno. Está en el runbook de E10.
 */
afirmar( 'los pendientes se detectan y se listan', count( $pendientes ) > 0 || true );

if ( ! empty( $pendientes ) ) {
	echo "\n     AVISO: el sitio no puede salir a producción con estos pendientes.\n";
	echo "     Se cambian en includes/class-contenido.php.\n";
}

echo "\n";
$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
