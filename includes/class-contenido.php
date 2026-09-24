<?php
/**
 * Textos del sitio público: FAQ y términos y condiciones.
 *
 * Viven aquí y no en el editor de WordPress por dos motivos. El primero es que
 * se versionan con el código, así que se puede saber qué texto exacto aceptó
 * cada participante mirando terminos_version. El segundo es que las respuestas
 * del FAQ describen el comportamiento del sistema, y cuando la mecánica cambia
 * hay que cambiarlas con ella: teniéndolas al lado del código es mucho más
 * probable que eso ocurra.
 *
 * ================== TEXTO DE EJEMPLO, NO DEFINITIVO ==================
 *
 * Es un borrador funcional, escrito para que el sitio se pueda maquetar y
 * probar entero. El área encargada del cliente entrega la versión legal
 * definitiva.
 *
 * Todo lo que falta por decidir va marcado como [pendiente: ...]. No es
 * decorativo: NavidadTVS_Contenido::pendientes() los cuenta, el panel de
 * administración avisa mientras quede alguno y dev/verificar-e7.php falla si
 * se intenta dar el sitio por terminado con pendientes dentro. La forma
 * realista de que esto salga mal es publicar con un "[pendiente: correo]" a la
 * vista.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contenido editorial del concurso.
 */
class NavidadTVS_Contenido {

	/**
	 * Marca de los textos que faltan por definir.
	 *
	 * @param string $que Qué falta.
	 * @return string
	 */
	private static function pendiente( $que ) {
		return '[pendiente: ' . $que . ']';
	}

	/**
	 * Preguntas frecuentes, agrupadas por tema.
	 *
	 * @return array<int, array{titulo: string, preguntas: array<int, array{p: string, r: string}>}>
	 */
	public static function faq() {
		$p = array( __CLASS__, 'pendiente' );

		return array(
			array(
				'titulo'    => __( 'Sobre el concurso', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Qué es el Concurso Navideño TVS?', 'navidad-tvs' ),
						'r' => __( 'Es una actividad de fin de año de Auteco TVS en la que quienes compraron una motocicleta TVS pueden jugar una carrera en línea y ganar premios según la distancia que recorran.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿En qué consiste el juego?', 'navidad-tvs' ),
						'r' => __( 'Es un juego de carreras de estilo retro. Manejas una moto durante 90 segundos y el objetivo es recorrer la mayor distancia posible. En el camino encuentras conos que te tumban, charcos de aceite que te frenan, impulsores que te empujan y llaves que suman metros.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Cuándo se realiza?', 'navidad-tvs' ),
						'r' => sprintf(
							/* translators: 1: fecha de inicio, 2: fecha de cierre. */
							__( 'Entre el %1$s y el %2$s.', 'navidad-tvs' ),
							call_user_func( $p, __( 'fecha de inicio', 'navidad-tvs' ) ),
							call_user_func( $p, __( 'fecha de cierre', 'navidad-tvs' ) )
						),
					),
					array(
						'p' => __( '¿Tiene algún costo?', 'navidad-tvs' ),
						'r' => __( 'No. La participación es gratuita.', 'navidad-tvs' ),
					),
				),
			),

			array(
				'titulo'    => __( 'Quiénes pueden participar', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Quién puede jugar?', 'navidad-tvs' ),
						'r' => __( 'Las personas mayores de 18 años que compraron una motocicleta TVS dentro del periodo definido para la actividad y que aparecen en la base de compradores elegibles.', 'navidad-tvs' ),
					),
					array(
						'p' => __( 'Compré una moto de otra marca de Auteco, ¿puedo participar?', 'navidad-tvs' ),
						'r' => __( 'No. Esta actividad es exclusiva para la marca TVS.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Cómo sé si estoy habilitado?', 'navidad-tvs' ),
						'r' => __( 'Ingresa a la página del concurso dentro del horario y digita tu número de celular. El sistema te indicará de inmediato si estás habilitado y si hoy es tu jornada.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Puedo participar cualquier día?', 'navidad-tvs' ),
						'r' => __( 'No. A cada participante se le asigna una jornada específica, de acuerdo con la fecha de su compra. Solo puedes jugar ese día.', 'navidad-tvs' ),
					),
					array(
						'p' => __( 'Compré la moto pero mi celular no aparece registrado, ¿qué hago?', 'navidad-tvs' ),
						'r' => sprintf(
							/* translators: 1: correo de soporte, 2: WhatsApp de soporte. */
							__( 'Escríbenos a %1$s o al WhatsApp %2$s con tu número de cédula y el número de factura. Verificaremos tu caso.', 'navidad-tvs' ),
							call_user_func( $p, __( 'correo de soporte', 'navidad-tvs' ) ),
							call_user_func( $p, __( 'WhatsApp de soporte', 'navidad-tvs' ) )
						),
					),
				),
			),

			array(
				'titulo'    => __( 'Cómo participar', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Cuál es el horario?', 'navidad-tvs' ),
						'r' => __( 'De lunes a sábado, de 12:00 p. m. a 1:30 p. m., hora de Colombia. Los domingos no hay jornada.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Qué necesito para jugar?', 'navidad-tvs' ),
						'r' => __( 'Un celular, tablet o computador con navegador actualizado y conexión a internet. Puedes jugar desde cualquiera de los tres.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Qué datos debo ingresar?', 'navidad-tvs' ),
						'r' => __( 'Tu número de celular y tu nombre, y aceptar los términos y condiciones.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Cuántas veces puedo jugar?', 'navidad-tvs' ),
						'r' => __( 'Una sola vez. No hay reintentos por ningún motivo.', 'navidad-tvs' ),
					),
					array(
						'p' => __( 'Se me cayó el internet a mitad de la carrera, ¿puedo volver a intentar?', 'navidad-tvs' ),
						'r' => __( 'No. El intento es único y no se repone por fallas de conexión, del dispositivo o del operador móvil. Por eso recomendamos jugar desde una conexión estable, preferiblemente WiFi, y no cerrar la página hasta que el sistema confirme que tu resultado quedó registrado.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Tengo que girar el celular?', 'navidad-tvs' ),
						'r' => __( 'No. La pantalla del juego es cuadrada y los controles van debajo, así que se juega con el celular en la mano como siempre.', 'navidad-tvs' ),
					),
				),
			),

			array(
				'titulo'    => __( 'Cómo se juega', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Cuáles son los controles?', 'navidad-tvs' ),
						'r' => __( 'Debajo de la pantalla hay un mando: la cruceta a la izquierda cambia de carril y los dos botones de la derecha son el acelerador y el turbo. En computador, la tecla Z acelera, la X es el turbo y las flechas cambian de carril. Antes de empezar verás una pantalla con las instrucciones.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Qué es la temperatura del motor?', 'navidad-tvs' ),
						'r' => __( 'El turbo te hace ir más rápido pero calienta el motor. Si la barra de temperatura llega al tope, el motor se sobrecalienta y la moto se detiene dos segundos y medio. Suelta el turbo para que se enfríe; soltar también el acelerador lo enfría el doble de rápido.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Conviene usar el turbo todo el tiempo?', 'navidad-tvs' ),
						'r' => __( 'No. Usarlo sin parar hace que se sobrecaliente tantas veces que terminas recorriendo menos distancia que sin usarlo nunca. La clave es dosificarlo.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Para qué sirven las llaves?', 'navidad-tvs' ),
						'r' => __( 'Cada llave que recojas suma 50 metros a tu distancia. Están repartidas entre los cuatro carriles, así que para juntarlas hay que moverse.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Qué son las flechas verdes del pavimento?', 'navidad-tvs' ),
						'r' => __( 'Son impulsores. Al pisarlos la moto recibe un empujón de velocidad y suman 1 metro.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Por qué la moto va cada vez más rápido?', 'navidad-tvs' ),
						'r' => __( 'Es parte del juego: cada 20 segundos la moto alcanza más velocidad y todo llega más rápido. Verás el aviso en pantalla cuando ocurra. Es igual para todos los participantes.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Cuánto dura la carrera?', 'navidad-tvs' ),
						'r' => __( '90 segundos exactos. El cronómetro empieza después de la cuenta regresiva, no antes.', 'navidad-tvs' ),
					),
				),
			),

			array(
				'titulo'    => __( 'Cómo se elige al ganador', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Cómo se determinan los ganadores?', 'navidad-tvs' ),
						'r' => __( 'Ganan los cuatro participantes con mayor distancia recorrida en cada jornada.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Qué pasa si hay empate?', 'navidad-tvs' ),
						'r' => __( 'Si varios participantes empatan en la última posición premiada, todos reciben premio. No hay desempate.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿El resultado que veo en pantalla es el definitivo?', 'navidad-tvs' ),
						'r' => __( 'Sí. La distancia se calcula en nuestros servidores, no en tu dispositivo, y lo que ves al terminar es ese resultado ya verificado.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Cómo se evita que alguien haga trampa?', 'navidad-tvs' ),
						'r' => __( 'Tu navegador no nos envía la distancia: nos envía lo que pulsaste durante la carrera. Nuestros servidores vuelven a correr la partida completa con esos datos y calculan ellos el resultado. Una partida que no se pueda reproducir se descalifica.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Cuándo y cómo se anuncian los ganadores?', 'navidad-tvs' ),
						'r' => call_user_func( $p, __( 'fecha, canal y forma de notificación', 'navidad-tvs' ) ),
					),
				),
			),

			array(
				'titulo'    => __( 'El premio y su entrega', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Cuál es el premio?', 'navidad-tvs' ),
						'r' => call_user_func( $p, __( 'descripción del premio', 'navidad-tvs' ) ),
					),
					array(
						'p' => __( '¿Puedo cambiar el premio por dinero?', 'navidad-tvs' ),
						'r' => __( 'No. Los premios son personales, intransferibles y no canjeables por dinero ni por otros productos.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Qué documentos necesito para reclamarlo?', 'navidad-tvs' ),
						'r' => __( 'Documento de identidad original y la factura de compra de tu motocicleta TVS, a nombre de la misma persona que participó.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Dónde y cuándo se entrega?', 'navidad-tvs' ),
						'r' => call_user_func( $p, __( 'lugar y plazo de entrega', 'navidad-tvs' ) ),
					),
					array(
						'p' => __( '¿Qué pasa si no reclamo el premio?', 'navidad-tvs' ),
						'r' => __( 'Si no lo reclamas en el plazo establecido en los términos y condiciones, se entiende que renuncias a él.', 'navidad-tvs' ),
					),
				),
			),

			array(
				'titulo'    => __( 'Datos personales y soporte', 'navidad-tvs' ),
				'preguntas' => array(
					array(
						'p' => __( '¿Qué datos guardan?', 'navidad-tvs' ),
						'r' => __( 'Tu nombre, celular, cédula, placa, ciudad y departamento, y el resultado de tu partida.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Para qué los usan?', 'navidad-tvs' ),
						'r' => __( 'Únicamente para verificar que puedes participar, operar el concurso, elegir a los ganadores y entregar los premios.', 'navidad-tvs' ),
					),
					array(
						'p' => __( '¿Puedo pedir que borren mis datos?', 'navidad-tvs' ),
						'r' => sprintf(
							/* translators: %s: correo de soporte. */
							__( 'Sí. Escribe a %s y atenderemos tu solicitud conforme a la Ley 1581 de 2012.', 'navidad-tvs' ),
							call_user_func( $p, __( 'correo de soporte', 'navidad-tvs' ) )
						),
					),
					array(
						'p' => __( 'Tengo un problema técnico, ¿a quién escribo?', 'navidad-tvs' ),
						'r' => sprintf(
							/* translators: 1: correo, 2: WhatsApp. */
							__( 'Correo: %1$s · WhatsApp: %2$s', 'navidad-tvs' ),
							call_user_func( $p, __( 'correo de soporte', 'navidad-tvs' ) ),
							call_user_func( $p, __( 'WhatsApp de soporte', 'navidad-tvs' ) )
						),
					),
				),
			),
		);
	}

	/**
	 * Cláusulas de los términos y condiciones, numeradas.
	 *
	 * Cada una es un título y una lista de párrafos.
	 *
	 * @return array<int, array{titulo: string, parrafos: array<int, string>}>
	 */
	public static function terminos() {
		$p = array( __CLASS__, 'pendiente' );

		return array(
			array(
				'titulo'   => __( 'Objeto de la actividad', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'El Concurso Navideño TVS es una actividad promocional dirigida a quienes adquirieron una motocicleta de la marca TVS dentro del periodo definido, cuyo objeto es premiar a los participantes según el resultado obtenido en un juego de habilidad en línea.', 'navidad-tvs' ),
					__( 'El resultado depende de la destreza del participante y no del azar.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Responsable de la actividad', 'navidad-tvs' ),
				'parrafos' => array(
					sprintf(
						/* translators: 1: razón social, 2: NIT, 3: domicilio. */
						__( 'La actividad es organizada por %1$s, identificada con NIT %2$s, con domicilio en %3$s.', 'navidad-tvs' ),
						call_user_func( $p, __( 'razón social del organizador', 'navidad-tvs' ) ),
						call_user_func( $p, __( 'NIT', 'navidad-tvs' ) ),
						call_user_func( $p, __( 'domicilio', 'navidad-tvs' ) )
					),
				),
			),
			array(
				'titulo'   => __( 'Vigencia y cobertura', 'navidad-tvs' ),
				'parrafos' => array(
					sprintf(
						/* translators: 1: fecha de inicio, 2: fecha de cierre. */
						__( 'La actividad se desarrolla entre el %1$s y el %2$s, ambas fechas inclusive.', 'navidad-tvs' ),
						call_user_func( $p, __( 'fecha de inicio', 'navidad-tvs' ) ),
						call_user_func( $p, __( 'fecha de cierre', 'navidad-tvs' ) )
					),
					__( 'Cada día hábil de la actividad constituye una jornada independiente, con sus propios ganadores.', 'navidad-tvs' ),
					__( 'La participación se habilita de lunes a sábado, entre las 12:00 p. m. y la 1:30 p. m., hora legal de la República de Colombia. Los domingos no hay jornada.', 'navidad-tvs' ),
					__( 'La actividad tiene cobertura en todo el territorio nacional colombiano.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Descripción de los premios', 'navidad-tvs' ),
				'parrafos' => array(
					sprintf(
						/* translators: %s: descripción del premio. */
						__( 'Se entregarán cuatro (4) premios por jornada, consistentes en %s.', 'navidad-tvs' ),
						call_user_func( $p, __( 'descripción y valor comercial del premio', 'navidad-tvs' ) )
					),
					__( 'Los premios son personales, intransferibles y no son canjeables por dinero en efectivo ni por otros bienes o servicios.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Mecánica de participación', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'El participante ingresa al sitio del concurso dentro del horario habilitado y en la jornada que le corresponde, digita su número de celular y su nombre, y acepta los presentes términos y condiciones.', 'navidad-tvs' ),
					__( 'Verificada su elegibilidad, accede a una pantalla de instrucciones y, al pulsar el botón de inicio, comienza una carrera de noventa (90) segundos.', 'navidad-tvs' ),
					__( 'El objetivo es recorrer la mayor distancia posible. La distancia se mide en metros e incluye los metros adicionales que otorgan los elementos recogidos durante la carrera.', 'navidad-tvs' ),
					__( 'Al finalizar, el dispositivo del participante transmite el registro de la partida al servidor del organizador, que la reproduce íntegramente y calcula el resultado oficial. El resultado mostrado en el dispositivo carece de validez hasta que el servidor lo confirma.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Requisitos para participar', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Ser persona natural mayor de dieciocho (18) años y residente en Colombia.', 'navidad-tvs' ),
					__( 'Haber adquirido una motocicleta de la marca TVS dentro del periodo definido por el organizador y figurar en la base de compradores elegibles.', 'navidad-tvs' ),
					__( 'Participar exclusivamente en la jornada asignada, determinada por la fecha de compra. La asignación de jornada es inmodificable.', 'navidad-tvs' ),
					__( 'Disponer de un dispositivo con navegador actualizado y conexión a internet.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Intento único', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Cada participante dispone de un (1) único intento durante toda la actividad. El intento se considera consumido en el momento en que el participante pulsa el botón de inicio de la carrera.', 'navidad-tvs' ),
					__( 'No se repondrá el intento por ningún motivo, incluyendo fallas de conexión a internet, agotamiento de datos, fallas del dispositivo, cierre accidental del navegador, llamadas entrantes, interrupciones del servicio del operador móvil o cualquier otra circunstancia ajena al organizador.', 'navidad-tvs' ),
					__( 'Se recomienda participar desde una conexión estable, preferiblemente WiFi, y no cerrar la página hasta que el sistema confirme que el resultado quedó registrado.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Criterio de selección de ganadores', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Resultarán ganadores de cada jornada los cuatro (4) participantes que hayan obtenido las mayores distancias válidas de esa jornada.', 'navidad-tvs' ),
					__( 'En caso de empate en la cuarta posición, todos los participantes empatados en dicha posición serán considerados ganadores y recibirán premio. No se aplicará ningún criterio de desempate.', 'navidad-tvs' ),
					__( 'Solo se consideran las partidas que el servidor haya podido verificar.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Identificación del ganador', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'La identificación del ganador se realiza mediante el número de cédula y el número de celular registrados en la base de compradores elegibles.', 'navidad-tvs' ),
					__( 'El nombre digitado al ingresar tiene carácter meramente informativo y no constituye medio de identificación. La coincidencia de nombres entre participantes no genera derecho alguno.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Requisitos para reclamar el premio', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Presentar el documento de identidad original.', 'navidad-tvs' ),
					__( 'Presentar la factura de compra de la motocicleta TVS, expedida a nombre del mismo titular que participó.', 'navidad-tvs' ),
					sprintf(
						/* translators: %s: plazo para reclamar. */
						__( 'Reclamar el premio dentro del plazo de %s contados desde la notificación.', 'navidad-tvs' ),
						call_user_func( $p, __( 'plazo para reclamar', 'navidad-tvs' ) )
					),
				),
			),
			array(
				'titulo'   => __( 'Condiciones de entrega', 'navidad-tvs' ),
				'parrafos' => array(
					sprintf(
						/* translators: %s: lugar y condiciones de entrega. */
						__( 'La entrega se realizará en %s.', 'navidad-tvs' ),
						call_user_func( $p, __( 'lugar y condiciones de entrega', 'navidad-tvs' ) )
					),
					__( 'Vencido el plazo para reclamar sin que el ganador se haya presentado, se entenderá que renuncia al premio, sin que ello genere compensación alguna.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Causales de descalificación y antifraude', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Será descalificado quien suplante la identidad de otra persona, utilice datos que no le correspondan o intente participar en una jornada distinta a la asignada.', 'navidad-tvs' ),
					__( 'Será descalificado quien emplee programas automatizados, modificaciones del juego, interceptación o alteración de las comunicaciones con el servidor, o cualquier medio técnico dirigido a obtener un resultado que no corresponda a su propia destreza.', 'navidad-tvs' ),
					__( 'El organizador registra y conserva el detalle de cada partida y puede reproducirla íntegramente con posterioridad. Las partidas cuyo resultado no pueda ser reproducido y verificado serán anuladas.', 'navidad-tvs' ),
					__( 'La descalificación podrá declararse en cualquier momento, incluso después de anunciados los ganadores y antes de la entrega del premio.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Protección de datos personales', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Al participar, el titular autoriza el tratamiento de sus datos personales conforme a la Ley 1581 de 2012 y sus decretos reglamentarios.', 'navidad-tvs' ),
					__( 'Se recolectan el nombre digitado, el número de celular, el número de cédula, la placa del vehículo, la ciudad y el departamento, así como los datos técnicos de la partida: dirección IP, identificador del navegador, marcas de tiempo y el registro de la partida.', 'navidad-tvs' ),
					__( 'La finalidad del tratamiento es verificar la elegibilidad, operar la actividad, seleccionar a los ganadores, entregar los premios y atender requerimientos de autoridades competentes.', 'navidad-tvs' ),
					sprintf(
						/* translators: %s: correo del responsable de datos. */
						__( 'El titular puede ejercer sus derechos de conocer, actualizar, rectificar y suprimir sus datos escribiendo a %s.', 'navidad-tvs' ),
						call_user_func( $p, __( 'correo del responsable de datos', 'navidad-tvs' ) )
					),
				),
			),
			array(
				'titulo'   => __( 'Uso de imagen', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Los ganadores autorizan al organizador a utilizar su nombre y la imagen de la entrega del premio con fines publicitarios relacionados con esta actividad, sin que ello genere contraprestación económica alguna.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Limitación de responsabilidad', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'El organizador no se hace responsable por fallas en la conexión a internet, en el dispositivo del participante, en el servicio de su operador móvil, ni por cualquier otra circunstancia ajena a su control que impida participar o completar la partida.', 'navidad-tvs' ),
					__( 'El organizador no responde por el uso indebido que terceros hagan de los datos de acceso del participante.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Modificación o suspensión', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'El organizador podrá modificar, suspender o dar por terminada la actividad por causas de fuerza mayor, caso fortuito o disposición de autoridad competente, informándolo por los mismos canales en que se divulgó.', 'navidad-tvs' ),
				),
			),
			array(
				'titulo'   => __( 'Canales de atención', 'navidad-tvs' ),
				'parrafos' => array(
					sprintf(
						/* translators: 1: correo, 2: WhatsApp, 3: horario de atención. */
						__( 'Correo: %1$s. WhatsApp: %2$s. Horario de atención: %3$s.', 'navidad-tvs' ),
						call_user_func( $p, __( 'correo de soporte', 'navidad-tvs' ) ),
						call_user_func( $p, __( 'WhatsApp de soporte', 'navidad-tvs' ) ),
						call_user_func( $p, __( 'horario de atención', 'navidad-tvs' ) )
					),
				),
			),
			array(
				'titulo'   => __( 'Jurisdicción y aceptación', 'navidad-tvs' ),
				'parrafos' => array(
					__( 'Estos términos y condiciones se rigen por la legislación colombiana. Cualquier controversia se someterá a los jueces de la República de Colombia.', 'navidad-tvs' ),
					__( 'La participación en la actividad implica el conocimiento y la aceptación plena de estos términos y condiciones.', 'navidad-tvs' ),
				),
			),
		);
	}

	/**
	 * Cuenta y lista los textos que faltan por definir.
	 *
	 * Lo usa el panel de administración para avisar y dev/verificar-e7.php
	 * para no dejar dar por terminado el sitio con pendientes dentro.
	 *
	 * @return array<int, string> Los pendientes distintos que hay, sin repetir.
	 */
	public static function pendientes() {
		$texto = '';

		foreach ( self::faq() as $grupo ) {
			foreach ( $grupo['preguntas'] as $par ) {
				$texto .= ' ' . $par['r'];
			}
		}

		foreach ( self::terminos() as $clausula ) {
			$texto .= ' ' . implode( ' ', $clausula['parrafos'] );
		}

		preg_match_all( '/\[pendiente: ([^\]]+)\]/u', $texto, $coincidencias );

		return array_values( array_unique( $coincidencias[1] ) );
	}
}
