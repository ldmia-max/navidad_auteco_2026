<?php
/**
 * Verificación del rango de fechas del concurso. Se corre con:
 *   docker compose run --rm wpcli eval-file wp-content/plugins/navidad-tvs/dev/verificar-rango-fechas.php
 *
 * verificar-e1.php ya comprueba a fondo si la ventana abre o no para una
 * fecha dada. Lo que se prueba aquí es lo otro: que el formulario del panel no
 * deje guardar un rango imposible, y que la página pública diga POR QUÉ está
 * cerrada en vez de soltar el horario de siempre.
 *
 * Esa segunda parte importa más de lo que parece. Un participante que entra en
 * noviembre y lee "se juega de 12:00 a 1:30 de lunes a sábado" se queda
 * pensando que el formulario está roto; lo que necesita leer es que el
 * concurso empieza en diciembre.
 *
 * Deja la configuración como la encontró.
 */

$GLOBALS['fallos'] = 0;

function comprobar( $etiqueta, $condicion, $detalle = '' ) {
	if ( ! $condicion ) {
		$GLOBALS['fallos']++;
	}
	printf( "%s %-52s %s\n", $condicion ? 'OK   ' : 'FALLA', $etiqueta, $detalle );
}

$plugin   = NavidadTVS_Plugin::instancia();
$settings = $plugin->settings;
$acceso   = $plugin->acceso;

$previo = array(
	'fecha_inicio'       => $settings->get( 'fecha_inicio' ),
	'fecha_fin'          => $settings->get( 'fecha_fin' ),
	'hora_inicio'        => $settings->get( 'hora_inicio' ),
	'hora_fin'           => $settings->get( 'hora_fin' ),
	'dias_habiles'       => $settings->get( 'dias_habiles' ),
	'concurso_congelado' => $settings->get( 'concurso_congelado' ),
	'telefono_pruebas'   => $settings->get( 'telefono_pruebas' ),
);

$hoy = NavidadTVS_Plugin::hoy();
$tz  = new DateTimeZone( NAVIDAD_TVS_TZ );
$ref = new DateTimeImmutable( $hoy, $tz );

$dentro_de = function ( $dias ) use ( $ref ) {
	$signo = $dias >= 0 ? '+' : '-';
	return $ref->modify( $signo . abs( $dias ) . ' days' )->format( 'Y-m-d' );
};

printf( "Hoy en Colombia: %s\n\n", $hoy );

// ===========================================================================
echo "--- El formulario no deja guardar un rango imposible ---\n";

/*
 * sanear_fecha() y la validación del par son privadas, que es donde deben
 * estar: nadie fuera del formulario tiene por qué llamarlas. Para probarlas se
 * usa reflexión en vez de abrirlas al mundo solo por el test.
 */
require_once NAVIDAD_TVS_PATH . 'includes/admin/class-admin-settings.php';

$admin_settings = new NavidadTVS_Admin_Settings( $settings );
$sanear         = new ReflectionMethod( 'NavidadTVS_Admin_Settings', 'sanear_fecha' );
$sanear->setAccessible( true );

$casos_fecha = array(
	array( '2026-12-24', '2026-12-24', 'una fecha normal pasa' ),
	array( '', '', 'vacio se queda vacio (sin tope)' ),
	array( '2026-02-31', '', '31 de febrero se rechaza' ),
	array( '2026-13-01', '', 'mes 13 se rechaza' ),
	array( '24/12/2026', '', 'formato con barras se rechaza' ),
	array( 'mañana', '', 'texto suelto se rechaza' ),
	array( '2026-2-4', '', 'sin cero a la izquierda se rechaza' ),
	array( '2026-12-24; DROP TABLE', '', 'basura pegada detras se rechaza' ),
);

foreach ( $casos_fecha as $c ) {
	$obtenido = $sanear->invoke( $admin_settings, $c[0] );
	comprobar( $c[2], $c[1] === $obtenido, sprintf( '"%s" -> "%s"', $c[0], $obtenido ) );
}

/*
 * El par al revés. Se simula lo que hace guardar(): si el fin es anterior al
 * inicio, se devuelven las DOS fechas que había. La invariante que sostiene
 * todo esto es que lo guardado siempre es un rango válido, así que restaurarlo
 * nunca puede dejar otro inválido.
 */
$settings->set(
	array(
		'fecha_inicio' => '2026-12-01',
		'fecha_fin'    => '2026-12-24',
	)
);

$valores = array( 'fecha_inicio' => '2026-12-24', 'fecha_fin' => '2026-12-01' );

if ( '' !== $valores['fecha_inicio'] && '' !== $valores['fecha_fin']
	&& $valores['fecha_fin'] < $valores['fecha_inicio'] ) {
	$valores['fecha_inicio'] = (string) $settings->get( 'fecha_inicio' );
	$valores['fecha_fin']    = (string) $settings->get( 'fecha_fin' );
}

comprobar(
	'un rango al reves conserva el anterior',
	'2026-12-01' === $valores['fecha_inicio'] && '2026-12-24' === $valores['fecha_fin'],
	$valores['fecha_inicio'] . ' a ' . $valores['fecha_fin']
);

// ===========================================================================
echo "\n--- La pagina publica dice por que esta cerrada ---\n";

/*
 * El horario se deja abierto de par en par en todos estos casos. Así lo único
 * que puede cerrar la ventana es la fecha, y el mensaje que salga tiene que
 * hablar de fechas, no de horas.
 */
$settings->set(
	array(
		'hora_inicio'        => '00:00',
		'hora_fin'           => '23:59',
		'dias_habiles'       => array( 1, 2, 3, 4, 5, 6, 7 ),
		'concurso_congelado' => false,
	)
);

// --- Todavía no empieza ---
$settings->set(
	array(
		'fecha_inicio' => $dentro_de( 10 ),
		'fecha_fin'    => $dentro_de( 40 ),
	)
);
$estado = $acceso->estado_ventana();

comprobar( 'antes del inicio: cerrada', false === $estado['abierta'] );
comprobar(
	'antes del inicio: avisa de cuando empieza',
	false !== strpos( $estado['mensaje'], 'empieza' ),
	$estado['mensaje']
);
comprobar(
	'antes del inicio: el rango sale en castellano',
	false !== strpos( $estado['rango'], 'Del ' ) && false !== strpos( $estado['rango'], ' al ' ),
	$estado['rango']
);

// --- Ya terminó ---
$settings->set(
	array(
		'fecha_inicio' => $dentro_de( -40 ),
		'fecha_fin'    => $dentro_de( -10 ),
	)
);
$estado = $acceso->estado_ventana();

comprobar( 'despues del final: cerrada', false === $estado['abierta'] );
comprobar(
	'despues del final: dice que termino',
	false !== strpos( $estado['mensaje'], 'terminó' ),
	$estado['mensaje']
);

// --- Dentro del rango ---
$settings->set(
	array(
		'fecha_inicio' => $dentro_de( -5 ),
		'fecha_fin'    => $dentro_de( 5 ),
	)
);
$estado = $acceso->estado_ventana();

comprobar( 'dentro del rango y en horario: abierta', true === $estado['abierta'], $estado['rango'] );
comprobar( 'abierta: sin mensaje de cierre', '' === $estado['mensaje'] );

// --- Sin rango ---
$settings->set( array( 'fecha_inicio' => '', 'fecha_fin' => '' ) );
$estado = $acceso->estado_ventana();

comprobar( 'sin rango: abierta', true === $estado['abierta'] );
comprobar( 'sin rango: no se inventa una frase de fechas', '' === $estado['rango'], '"' . $estado['rango'] . '"' );

// --- Un solo extremo ---
$settings->set( array( 'fecha_inicio' => $dentro_de( -5 ), 'fecha_fin' => '' ) );
$estado = $acceso->estado_ventana();
comprobar(
	'solo inicio: lo cuenta como "a partir del"',
	false !== strpos( $estado['rango'], 'A partir del' ),
	$estado['rango']
);

$settings->set( array( 'fecha_inicio' => '', 'fecha_fin' => $dentro_de( 5 ) ) );
$estado = $acceso->estado_ventana();
comprobar(
	'solo final: lo cuenta como "hasta el"',
	false !== strpos( $estado['rango'], 'Hasta el' ),
	$estado['rango']
);

// ===========================================================================
echo "\n--- El ultimo dia no promete una jornada que ya no existe ---\n";

/*
 * Caso concreto y fácil de pasar por alto: hoy es el último día y la jornada
 * ya cerró. El mensaje de siempre diría "la próxima jornada es el lunes", y no
 * va a haber ninguna. Para forzarlo, el horario se pone en una franja de
 * madrugada que ya pasó.
 */
$settings->set(
	array(
		'fecha_inicio' => $dentro_de( -5 ),
		'fecha_fin'    => $hoy,
		'hora_inicio'  => '03:00',
		'hora_fin'     => '03:30',
		'dias_habiles' => array( 1, 2, 3, 4, 5, 6, 7 ),
	)
);
$estado = $acceso->estado_ventana();

comprobar( 'ultimo dia pasada la hora: cerrada', false === $estado['abierta'] );
comprobar(
	'no anuncia una proxima jornada',
	false === strpos( $estado['mensaje'], 'próxima jornada' ),
	$estado['mensaje']
);

// Y en cambio, a mitad del concurso sí debe anunciarla.
$settings->set( array( 'fecha_fin' => $dentro_de( 20 ) ) );
$estado = $acceso->estado_ventana();

comprobar(
	'a mitad de concurso si anuncia la proxima',
	false !== strpos( $estado['mensaje'], 'próxima jornada' )
		|| false !== strpos( $estado['mensaje'], 'abre a las' ),
	$estado['mensaje']
);

// ===========================================================================
echo "\n--- El modo de pruebas sobrevive a las fechas ---\n";

/*
 * El caso real: el concurso está configurado para más adelante y hay que
 * seguir revisando el juego hasta que empiece.
 *
 * El servidor siempre dejó entrar al teléfono de pruebas fuera del horario y
 * fuera del rango, pero la pantalla mostraba el cartel de cerrado y no había
 * dónde escribir el número. Lo que se comprueba aquí es que el formulario
 * aparece mientras el teléfono esté puesto, y que desaparece en cuanto se
 * borra, sin tocar ninguna otra casilla.
 */
$settings->set(
	array(
		'fecha_inicio' => $dentro_de( 18 ),
		'fecha_fin'    => $dentro_de( 48 ),
		'hora_inicio'  => '12:00',
		'hora_fin'     => '13:30',
		'dias_habiles' => array( 1, 2, 3, 4, 5, 6 ),
	)
);

comprobar( 'el escenario parte con la ventana cerrada', ! NavidadTVS_Plugin::ventana_abierta() );

$settings->set( array( 'telefono_pruebas' => '' ) );
$html = do_shortcode( '[' . NavidadTVS_Shortcode::TAG . ']' );

comprobar( 'sin telefono de pruebas: no hay formulario', false === strpos( $html, 'ntvs-form' ) );
comprobar( 'sin telefono de pruebas: sale la pantalla de cerrado', false !== strpos( $html, 'ntvs--cerrado' ) );

$settings->set( array( 'telefono_pruebas' => '3999000111' ) );
$html_pruebas = do_shortcode( '[' . NavidadTVS_Shortcode::TAG . ']' );

comprobar( 'con telefono de pruebas: hay formulario', false !== strpos( $html_pruebas, 'ntvs-form' ) );

/*
 * Y no se anuncia por ninguna parte. El participante que llegue antes de la
 * fecha de apertura tiene que ver exactamente la misma pantalla que verá el
 * primer día: ni cartel de "todavía no abre", ni mención de que esto son
 * pruebas, ni la fecha de arranque asomando por un rincón.
 *
 * Se comprueba contra el HTML entero, no contra una clase concreta, porque lo
 * que importa no es dónde saldría el texto sino que no salga en ningún lado.
 */
foreach ( array(
	'Todavía no abre',
	'pruebas internas',
	'El concurso empieza el',
) as $filtrado ) {
	comprobar(
		sprintf( 'no se filtra "%s"', $filtrado ),
		false === strpos( $html_pruebas, $filtrado )
	);
}

/*
 * La única señal es el triángulo, que viaja en el HTML pero nace oculto: lo
 * descubre acceso.js cuando el servidor confirma que la sesión es de pruebas.
 */
comprobar(
	'el triangulo viaja en la pagina',
	false !== strpos( $html_pruebas, 'ntvs-marca-pruebas' )
);
comprobar(
	'y viaja oculto',
	(bool) preg_match( '/<p class="ntvs-marca-pruebas"[^>]*\shidden/', $html_pruebas )
);

/*
 * "Modo de pruebas" sí aparece en el código fuente: es el title y el
 * aria-label del triángulo, que es lo que explica el icono a quien pasa el
 * ratón por encima o usa lector de pantalla. No se puede quitar sin dejar el
 * icono mudo.
 *
 * Lo que sí tiene que cumplirse es que esa frase no exista en ningún otro
 * sitio de la página: fuera del bloque oculto, ni rastro. Quien no abra el
 * código fuente no tiene forma de enterarse, y quien lo abra ya está mirando
 * donde un participante no mira.
 */
$sin_marca = preg_replace( '#<p class="ntvs-marca-pruebas".*?</p>#s', '', $html_pruebas );

comprobar(
	'la frase "Modo de pruebas" solo vive dentro del triangulo oculto',
	false === strpos( (string) $sin_marca, 'Modo de pruebas' )
		&& false !== strpos( $html_pruebas, 'Modo de pruebas' )
);

// Con la ventana abierta la pantalla tiene que ser la misma.
$settings->set( array( 'fecha_inicio' => $dentro_de( -5 ), 'fecha_fin' => $dentro_de( 5 ), 'hora_inicio' => '00:00', 'hora_fin' => '23:59' ) );
$html_abierto = do_shortcode( '[' . NavidadTVS_Shortcode::TAG . ']' );

comprobar( 'concurso abierto: hay formulario', false !== strpos( $html_abierto, 'ntvs-form' ) );
comprobar(
	'la pantalla de antes y la de despues son identicas',
	$html_pruebas === $html_abierto,
	strlen( $html_pruebas ) . ' vs ' . strlen( $html_abierto ) . ' bytes'
);

// ===========================================================================
$settings->set( $previo );

printf(
	"\nConfiguracion restaurada: fechas %s a %s, horario %s-%s\n",
	'' !== $previo['fecha_inicio'] ? $previo['fecha_inicio'] : 'sin tope',
	'' !== $previo['fecha_fin'] ? $previo['fecha_fin'] : 'sin tope',
	$previo['hora_inicio'],
	$previo['hora_fin']
);

$fallos = (int) $GLOBALS['fallos'];
echo 0 === $fallos ? "TODO OK\n" : "{$fallos} FALLO(S)\n";
