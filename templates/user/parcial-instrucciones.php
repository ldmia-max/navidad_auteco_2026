<?php
/**
 * Pantalla de instrucciones.
 *
 * Sigue el diseño de imagenes_apoyo/Ayudas.png: una tabla de controles con una
 * columna por dispositivo y, debajo, las fichas de los elementos de la pista.
 *
 * Los botones de la tabla son los MISMOS del mando, con las mismas clases;
 * solo cambia --m, el módulo del que cuelgan todas sus medidas. Así el
 * participante ve en la ayuda el botón que va a tener debajo del juego, y si
 * mañana el mando cambia de color la ayuda cambia con él.
 *
 * Los iconos salen de los sprites del juego con `npm run arte:iconos`. No se
 * dibujan a mano ni se piden por separado, para que no puedan quedarse atrás
 * respecto a lo que se ve en la pista.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Las fichas van en un array y no repetidas en el marcado: son cinco bloques
 * idénticos salvo el texto, y con el bucle no hay forma de que uno se quede
 * con la clase mal escrita.
 *
 * El ancho y el alto son los del PNG tal como lo escribe el exportador, que
 * los imprime al generarlos. Van en el atributo para que el navegador reserve
 * el hueco antes de descargarlos y la pantalla no dé el salto al cargar.
 */
$ntvs_fichas = array(
	array(
		'archivo' => 'temp.png',
		'ancho'   => 116,
		'alto'    => 68,
		'titulo'  => __( 'Temperatura', 'navidad-tvs' ),
		'texto'   => __( 'El turbo la sube. Si llega al tope, el motor se sobrecalienta y la moto se detiene dos segundos y medio. Suelta el turbo para enfriarlo; soltar el acelerador enfría el doble.', 'navidad-tvs' ),
	),
	array(
		'archivo' => 'llave.png',
		'ancho'   => 72,
		'alto'    => 40,
		'titulo'  => __( 'Llaves', 'navidad-tvs' ),
		'texto'   => __( 'Cada una que recojas suma 50 metros.', 'navidad-tvs' ),
	),
	array(
		'archivo' => 'impulsor.png',
		'ancho'   => 65,
		'alto'    => 40,
		'titulo'  => __( 'Impulsores', 'navidad-tvs' ),
		'texto'   => __( 'Las flechas verdes del pavimento te dan un empujón y suman 1 metro.', 'navidad-tvs' ),
	),
	array(
		'archivo' => 'cono.png',
		'ancho'   => 40,
		'alto'    => 56,
		'titulo'  => __( 'Conos', 'navidad-tvs' ),
		'texto'   => __( 'Esquívalos cambiando de carril. Si le pegas a uno te caes y pierdes dos segundos.', 'navidad-tvs' ),
	),
	array(
		'archivo' => 'aceite.png',
		'ancho'   => 78,
		'alto'    => 27,
		'titulo'  => __( 'Charcos de aceite', 'navidad-tvs' ),
		'texto'   => __( 'No tumban, pero te frenan mientras los pisas.', 'navidad-tvs' ),
	),
);
?>
<div class="ntvs-panel ntvs-panel--ayuda" id="ntvs-panel-instrucciones" hidden>

	<p class="ntvs-bienvenida">
		<?php esc_html_e( '¡Listo', 'navidad-tvs' ); ?>
		<span id="ntvs-nombre-jugador"></span>!
	</p>

	<h2 class="ntvs-ayuda__titulo"><?php esc_html_e( '¿Cómo se juega?', 'navidad-tvs' ); ?></h2>

	<!-- Controles ---------------------------------------------------- -->
	<?php
	/*
	 * La columna del celular y la del computador van las dos en el HTML y el
	 * CSS enseña una sola, la que corresponde al aparato.
	 *
	 * Se hace con CSS y no en PHP porque en el servidor no se sabe qué hay al
	 * otro lado: user agent sniffing se equivoca, y una tableta o un portátil
	 * táctil no caen en ninguna de las dos casillas. El CSS pregunta lo único
	 * que importa —si hay ratón y pantalla ancha— y acierta siempre.
	 *
	 * Las dos van en el marcado también para que quien llegue con un lector de
	 * pantalla o con el CSS caído las tenga ambas: la ayuda completa es mejor
	 * que media ayuda.
	 */
	?>
	<div class="ntvs-ayuda__bloque">
		<table class="ntvs-controles">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Mecánica', 'navidad-tvs' ); ?></th>
					<th scope="col" class="ntvs-col--tactil"><?php esc_html_e( 'Celular', 'navidad-tvs' ); ?></th>
					<th scope="col" class="ntvs-col--teclado"><?php esc_html_e( 'Computador', 'navidad-tvs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Acelerador', 'navidad-tvs' ); ?></th>
					<td class="ntvs-col--tactil">
						<span class="ntvs-mando-mini">
							<span class="ntvs-btn ntvs-btn--acelera"><?php esc_html_e( 'ACELERAR', 'navidad-tvs' ); ?></span>
						</span>
					</td>
					<td class="ntvs-col--teclado"><kbd class="ntvs-tecla">Z</kbd></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Turbo', 'navidad-tvs' ); ?></th>
					<td class="ntvs-col--tactil">
						<span class="ntvs-mando-mini">
							<span class="ntvs-btn ntvs-btn--turbo"><?php esc_html_e( 'TURBO', 'navidad-tvs' ); ?></span>
						</span>
					</td>
					<td class="ntvs-col--teclado"><kbd class="ntvs-tecla">X</kbd></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cambio de carril', 'navidad-tvs' ); ?></th>
					<td class="ntvs-col--tactil">
						<span class="ntvs-mando-mini ntvs-mando-mini--par">
							<span class="ntvs-btn ntvs-btn--dir">
								<span class="ntvs-flecha ntvs-flecha--arriba" aria-hidden="true"></span>
							</span>
							<span class="ntvs-btn ntvs-btn--dir">
								<span class="ntvs-flecha ntvs-flecha--abajo" aria-hidden="true"></span>
							</span>
						</span>
					</td>
					<td class="ntvs-col--teclado">
						<span class="ntvs-teclas-par">
							<kbd class="ntvs-tecla">&uarr;</kbd>
							<kbd class="ntvs-tecla">&darr;</kbd>
						</span>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Elementos de la pista ---------------------------------------- -->
	<div class="ntvs-ayuda__bloque">
		<ul class="ntvs-fichas">
			<?php foreach ( $ntvs_fichas as $ntvs_ficha ) : ?>
				<li class="ntvs-ficha">
					<div class="ntvs-ficha__texto">
						<h3><?php echo esc_html( $ntvs_ficha['titulo'] ); ?></h3>
						<p><?php echo esc_html( $ntvs_ficha['texto'] ); ?></p>
					</div>
					<?php
					/*
					 * alt vacío a propósito: el icono repite lo que ya dice el
					 * título de al lado, y anunciarlo dos veces solo estorba a
					 * quien usa lector de pantalla.
					 */
					?>
					<img class="ntvs-ficha__icono"
						src="<?php echo esc_url( NavidadTVS_Shortcode::url_asset( 'assets/img/instrucciones/' . $ntvs_ficha['archivo'] ) ); ?>"
						alt=""
						width="<?php echo esc_attr( $ntvs_ficha['ancho'] ); ?>"
						height="<?php echo esc_attr( $ntvs_ficha['alto'] ); ?>"
						loading="lazy" decoding="async">
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<p class="ntvs-aviso ntvs-aviso--fuerte">
		<?php esc_html_e( 'Al pulsar el botón empieza una cuenta regresiva y luego la carrera. Es tu único intento.', 'navidad-tvs' ); ?>
	</p>

	<?php
	/*
	 * Barra de descarga del bundle.
	 *
	 * Nace oculta y solo aparece mientras baja el juego. El role progressbar
	 * con sus valores es lo que hace que un lector de pantalla cante el avance;
	 * sin eso, para quien no ve la barra la espera es una pantalla muda.
	 */
	?>
	<div class="ntvs-progreso" id="ntvs-progreso" hidden
		role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
		aria-label="<?php esc_attr_e( 'Descarga del juego', 'navidad-tvs' ); ?>">
		<span class="ntvs-progreso__barra" id="ntvs-progreso-barra"></span>
	</div>

	<?php
	/*
	 * El botón nace deshabilitado y en rojo. La cuenta de diez segundos la
	 * lleva acceso.js, que es quien sabe además si el bundle del juego ya
	 * terminó de bajar; el botón solo se abre cuando se cumplen las dos cosas.
	 *
	 * aria-live en el propio botón para que un lector de pantalla cante lo que
	 * falta sin duplicar el texto en otro sitio.
	 */
	?>
	<button class="ntvs-boton ntvs-boton--relieve ntvs-boton--arranque" type="button" id="ntvs-iniciar"
		disabled aria-live="polite">
		<?php esc_html_e( 'Iniciar carrera', 'navidad-tvs' ); ?>
	</button>
</div>
