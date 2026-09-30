/**
 * Formulario de acceso al juego.
 *
 * Solo valida la forma de los datos para no hacer viajes inútiles al servidor.
 * Quién puede jugar lo decide el backend: cualquier cosa que se compruebe aquí
 * se comprueba otra vez allá.
 */
( function () {
	'use strict';

	var cfg = window.NAVIDAD_TVS || {};

	var form = document.getElementById( 'ntvs-form' );
	if ( ! form ) {
		return;
	}

	var inputNombre   = document.getElementById( 'ntvs-nombre' );
	var inputTelefono = document.getElementById( 'ntvs-telefono' );
	var inputAcepta   = document.getElementById( 'ntvs-acepta' );
	var boton         = document.getElementById( 'ntvs-enviar' );
	var cajaError     = document.getElementById( 'ntvs-error' );

	var panelAcceso        = document.getElementById( 'ntvs-panel-acceso' );
	var panelInstrucciones = document.getElementById( 'ntvs-panel-instrucciones' );
	var panelJuego         = document.getElementById( 'ntvs-panel-juego' );

	var textos = cfg.textos || {};
	var sesion = null;
	var enviando = false;

	// -----------------------------------------------------------------
	// Utilidades
	// -----------------------------------------------------------------

	function mostrarError( mensaje ) {
		cajaError.textContent = mensaje;
		cajaError.hidden = false;
		cajaError.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
	}

	function limpiarError() {
		cajaError.textContent = '';
		cajaError.hidden = true;
		inputNombre.removeAttribute( 'aria-invalid' );
		inputTelefono.removeAttribute( 'aria-invalid' );
	}

	/**
	 * Deja el teléfono en diez dígitos.
	 *
	 * Espejo de NavidadTVS_Plugin::normalizar_telefono() en PHP. Si una de las
	 * dos cambia, la otra tiene que cambiar igual.
	 */
	function normalizarTelefono( valor ) {
		var digitos = String( valor || '' ).replace( /\D+/g, '' );

		if ( digitos.length === 12 && digitos.indexOf( '57' ) === 0 ) {
			digitos = digitos.slice( 2 );
		}

		if ( digitos.length !== 10 || digitos.charAt( 0 ) !== '3' ) {
			return '';
		}

		return digitos;
	}

	function tokenTurnstile() {
		var campo = document.querySelector( 'input[name="cf-turnstile-response"]' );
		return campo ? campo.value : '';
	}

	// -----------------------------------------------------------------
	// Mando en pantalla
	// -----------------------------------------------------------------

	/*
	 * El juego lee estas cuatro banderas en cada tick. No las toca nadie más.
	 *
	 * Se crea aquí y no en el bundle porque este archivo carga antes: cuando el
	 * juego arranca ya tiene dónde mirar.
	 */
	window.navidadTvsMando = { arriba: false, abajo: false, acelera: false, turbo: false };

	function montarMando() {
		var mando = document.getElementById( 'ntvs-mando' );
		if ( ! mando ) {
			return;
		}

		var botones = mando.querySelectorAll( '[data-mando]' );

		Array.prototype.forEach.call( botones, function ( boton ) {
			var accion = boton.getAttribute( 'data-mando' );

			var pulsar = function ( evento ) {
				evento.preventDefault();
				window.navidadTvsMando[ accion ] = true;
				boton.classList.add( 'ntvs-btn--activo' );

				/*
				 * Capturar el puntero es lo que permite arrastrar el dedo fuera
				 * del botón sin que se quede pulsado para siempre: el pointerup
				 * llega igual a este elemento aunque el dedo ya no esté encima.
				 */
				if ( boton.setPointerCapture && evento.pointerId !== undefined ) {
					try {
						boton.setPointerCapture( evento.pointerId );
					} catch ( e ) {
						// Safari viejo. El soltar global de abajo lo cubre.
					}
				}
			};

			var soltar = function () {
				window.navidadTvsMando[ accion ] = false;
				boton.classList.remove( 'ntvs-btn--activo' );
			};

			boton.addEventListener( 'pointerdown', pulsar );
			boton.addEventListener( 'pointerup', soltar );
			boton.addEventListener( 'pointercancel', soltar );

			// Que el botón no se quede "pegado" si el navegador se traga el
			// pointerup: al perder el foco o al salir la pestaña, todo a cero.
			boton.addEventListener( 'lostpointercapture', soltar );

			// Sin esto, mantener pulsado abre el menú contextual en Android.
			boton.addEventListener( 'contextmenu', function ( e ) {
				e.preventDefault();
			} );
		} );

		var soltarTodo = function () {
			window.navidadTvsMando.arriba = false;
			window.navidadTvsMando.abajo = false;
			window.navidadTvsMando.acelera = false;
			window.navidadTvsMando.turbo = false;

			Array.prototype.forEach.call( botones, function ( b ) {
				b.classList.remove( 'ntvs-btn--activo' );
			} );
		};

		window.addEventListener( 'blur', soltarTodo );
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				soltarTodo();
			}
		} );
	}

	montarMando();

	// -----------------------------------------------------------------
	// Envío
	// -----------------------------------------------------------------

	inputTelefono.addEventListener( 'input', function () {
		// Que no se puedan escribir letras en el campo de celular.
		var limpio = this.value.replace( /[^\d+\s-]/g, '' );
		if ( limpio !== this.value ) {
			this.value = limpio;
		}
		limpiarError();
	} );

	inputNombre.addEventListener( 'input', limpiarError );
	inputAcepta.addEventListener( 'change', limpiarError );

	form.addEventListener( 'submit', function ( evento ) {
		evento.preventDefault();

		if ( enviando ) {
			return;
		}

		limpiarError();

		var nombre   = inputNombre.value.trim();
		var telefono = normalizarTelefono( inputTelefono.value );

		if ( nombre.length < 2 ) {
			inputNombre.setAttribute( 'aria-invalid', 'true' );
			inputNombre.focus();
			mostrarError( 'Escribe tu nombre para continuar.' );
			return;
		}

		if ( ! telefono ) {
			inputTelefono.setAttribute( 'aria-invalid', 'true' );
			inputTelefono.focus();
			mostrarError( 'Escribe tu número de celular a 10 dígitos.' );
			return;
		}

		if ( ! inputAcepta.checked ) {
			inputAcepta.focus();
			mostrarError( 'Debes aceptar los términos y condiciones para participar.' );
			return;
		}

		enviar( nombre, telefono );
	} );

	function enviar( nombre, telefono ) {
		// Sin configuración no hay a dónde enviar. Pasó una vez: los assets se
		// registraban tarde y este objeto no llegaba a la página, así que el
		// fetch salía contra undefined y el participante veía el error del
		// parser de JSON en pantalla.
		if ( ! cfg.endpointAcceso ) {
			mostrarError( textos.errorGeneral || 'Algo salió mal. Recarga la página e inténtalo de nuevo.' );
			return;
		}

		enviando = true;
		boton.disabled = true;
		boton.textContent = textos.validando || 'Validando…';

		fetch( cfg.endpointAcceso, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( {
				nombre: nombre,
				telefono: telefono,
				acepta: true,
				turnstile: tokenTurnstile()
			} )
		} )
			.then( function ( respuesta ) {
				// Si el servidor devuelve HTML (una página de error, un
				// mantenimiento, un proxy de por medio), json() lanzaría un
				// error del parser. El participante no tiene por qué leer eso.
				return respuesta.text().then( function ( texto ) {
					var datos;
					try {
						datos = JSON.parse( texto );
					} catch ( e ) {
						throw new Error( textos.errorServidor || 'El servidor no respondió como esperábamos. Inténtalo de nuevo en unos segundos.' );
					}
					return { ok: respuesta.ok, datos: datos };
				} );
			} )
			.then( function ( resultado ) {
				if ( ! resultado.ok ) {
					var mensaje = ( resultado.datos && resultado.datos.message )
						? resultado.datos.message
						: ( textos.errorGeneral || 'Algo salió mal.' );
					throw new Error( mensaje );
				}

				sesion = resultado.datos;
				mostrarInstrucciones();
			} )
			.catch( function ( error ) {
				var mensaje = error && error.message
					? error.message
					: ( textos.errorRed || 'No pudimos conectarnos.' );

				// Un fallo de red deja el mensaje genérico; los de negocio
				// vienen con su propio texto desde el servidor.
				mostrarError( mensaje === 'Failed to fetch' ? ( textos.errorRed || mensaje ) : mensaje );

				// Turnstile quema el token en cada verificación.
				if ( window.turnstile && typeof window.turnstile.reset === 'function' ) {
					window.turnstile.reset();
				}
			} )
			.finally( function () {
				enviando = false;
				boton.disabled = false;
				boton.textContent = textos.jugar || 'Entrar al juego';
			} );
	}

	// -----------------------------------------------------------------
	// Paso a instrucciones y al juego
	// -----------------------------------------------------------------

	var botonIniciar = document.getElementById( 'ntvs-iniciar' );
	var juegoListo = false;
	var juegoFallo = false;

	/**
	 * Espera obligatoria antes de poder arrancar.
	 *
	 * El intento es único y dura noventa segundos. Sin esta pausa, quien llega
	 * con prisa pulsa el botón nada más aparecer la pantalla y descubre los
	 * controles con el reloj ya corriendo. Diez segundos no alcanzan para
	 * leerlo todo, pero sí para que la vista se pose en la tabla.
	 */
	var ESPERA_MS = 10000;
	var finEspera = 0;
	var relojEspera = null;

	/**
	 * Segundos que faltan, calculados contra el reloj y no descontando de uno
	 * en uno.
	 *
	 * En el celular la pantalla se apaga o el participante se va a otra app, y
	 * ahí el navegador frena los temporizadores. Contando ticks, al volver
	 * faltarían ocho segundos que ya pasaron; contra el reloj, la cuenta dice
	 * la verdad.
	 */
	function segundosQueFaltan() {
		if ( ! finEspera ) {
			return 0;
		}

		return Math.max( 0, Math.ceil( ( finEspera - Date.now() ) / 1000 ) );
	}

	/**
	 * Deja el botón como toque según el estado.
	 *
	 * Son dos condiciones independientes y las dos tienen que cumplirse: que
	 * el bundle haya terminado de bajar y que la espera haya pasado. Tenerlas
	 * en un solo sitio evita el enredo de que una las deshaga por su cuenta.
	 */
	function refrescarBotonIniciar() {
		if ( ! botonIniciar || juegoFallo ) {
			return;
		}

		if ( ! juegoListo ) {
			botonIniciar.disabled = true;

			/*
			 * El porcentaje va también en el rótulo, no solo en la barra. La
			 * barra se ve de reojo; la cifra es la que convence de que la
			 * pantalla no se quedó pegada.
			 */
			var base = textos.cargando || 'Cargando el juego…';

			botonIniciar.textContent = ( 'number' === typeof pctDescarga )
				? base + ' ' + pctDescarga + '%'
				: base;

			return;
		}

		var faltan = segundosQueFaltan();

		if ( faltan > 0 ) {
			var plantilla = 1 === faltan
				? ( textos.esperaUno || 'Iniciar carrera en %d segundo' )
				: ( textos.espera || 'Iniciar carrera en %d segundos' );

			botonIniciar.disabled = true;
			botonIniciar.textContent = plantilla.replace( '%d', faltan );
			return;
		}

		botonIniciar.disabled = false;
		botonIniciar.textContent = textos.listo || 'Iniciar carrera';
	}

	function arrancarEspera() {
		finEspera = Date.now() + ESPERA_MS;
		refrescarBotonIniciar();

		if ( relojEspera ) {
			window.clearInterval( relojEspera );
		}

		/*
		 * Cada cuarto de segundo y no cada segundo: con un intervalo de 1000 ms
		 * el número tarda hasta un segundo entero en cambiar después de que
		 * toque, y la cuenta se ve trabada.
		 */
		relojEspera = window.setInterval( function () {
			refrescarBotonIniciar();

			if ( 0 === segundosQueFaltan() ) {
				window.clearInterval( relojEspera );
				relojEspera = null;
			}
		}, 250 );
	}

	function mostrarInstrucciones() {
		var etiquetaNombre = document.getElementById( 'ntvs-nombre-jugador' );
		if ( etiquetaNombre ) {
			etiquetaNombre.textContent = sesion.nombre;
		}

		panelAcceso.hidden = true;
		panelInstrucciones.hidden = false;
			panelInstrucciones.scrollIntoView( { block: 'start', behavior: 'smooth' } );

		// La cuenta arranca aquí y no al cargar la página: lo que se quiere
		// es que pasen diez segundos DELANTE de las instrucciones.
		arrancarEspera();

		// El bundle se descarga mientras el participante lee las
		// instrucciones. Son unos segundos que de otro modo se perderían
		// después de pulsar el botón, con el reloj ya en marcha.
		precargarJuego();
	}

	/**
	 * Descarga el bundle del juego enseñando cuánto lleva.
	 *
	 * El bundle pesa alrededor de metro y medio de megabyte. En una conexión
	 * móvil mala eso son varios segundos con el botón bloqueado y sin señal de
	 * vida, y un participante que ve una pantalla quieta cree que se rompió y
	 * recarga; recargar en mitad de la descarga la empieza de cero.
	 *
	 * Por eso no basta con <script src>: esa etiqueta no informa del avance.
	 * Se baja con XHR, que sí lo hace, y el código se inyecta después.
	 *
	 * La etiqueta suelta se conserva como plan B. Si el XHR falla —sin
	 * XMLHttpRequest, una CSP que prohíba el script en línea, un proxy que
	 * corte— se vuelve al camino de siempre, que funciona aunque no muestre
	 * progreso. Antes perder el progreso que perder el juego.
	 */
	function precargarJuego() {
		if ( ! botonIniciar ) {
			return;
		}

		if ( typeof window.navidadTvsIniciarJuego === 'function' ) {
			juegoListo = true;
			refrescarBotonIniciar();
			return;
		}

		refrescarBotonIniciar();

		if ( typeof window.XMLHttpRequest === 'undefined' ) {
			precargarConEtiqueta();
			return;
		}

		var xhr = new XMLHttpRequest();

		xhr.open( 'GET', cfg.urlJuego, true );

		/*
		 * lengthComputable es false cuando el servidor no manda Content-Length,
		 * que pasa con algunas configuraciones de compresión al vuelo. En ese
		 * caso se enseña la barra en movimiento pero sin cifra, que es honesto:
		 * está bajando y no se sabe cuánto falta.
		 */
		xhr.onprogress = function ( evento ) {
			if ( evento.lengthComputable && evento.total > 0 ) {
				// Se topa en 99: el 100 se pone cuando el juego ya respondió.
				ponerProgreso( Math.min( 99, Math.round( ( evento.loaded * 100 ) / evento.total ) ) );
			} else {
				ponerProgreso( null );
			}
		};

		xhr.onload = function () {
			if ( xhr.status < 200 || xhr.status >= 300 ) {
				precargarConEtiqueta();
				return;
			}

			try {
				var etiqueta = document.createElement( 'script' );
				etiqueta.text = xhr.responseText;
				document.head.appendChild( etiqueta );
			} catch ( e ) {
				precargarConEtiqueta();
				return;
			}

			/*
			 * Que el script se haya inyectado no garantiza que se haya
			 * ejecutado: una CSP estricta lo bloquea en silencio. Se comprueba
			 * que la función existe antes de dar el juego por cargado.
			 */
			if ( typeof window.navidadTvsIniciarJuego !== 'function' ) {
				precargarConEtiqueta();
				return;
			}

			ponerProgreso( 100 );
			ocultarProgreso();
			juegoListo = true;
			refrescarBotonIniciar();
		};

		xhr.onerror = function () {
			precargarConEtiqueta();
		};

		mostrarProgreso();
		xhr.send();
	}

	/** Descarga el bundle con una etiqueta script, sin progreso. */
	function precargarConEtiqueta() {
		if ( juegoListo || juegoFallo ) {
			return;
		}

		ocultarProgreso();

		var etiqueta = document.createElement( 'script' );
		etiqueta.src = cfg.urlJuego;
		etiqueta.async = true;

		etiqueta.onload = function () {
			juegoListo = true;
			refrescarBotonIniciar();
		};

		etiqueta.onerror = function () {
			juegoFallo = true;
			botonIniciar.disabled = true;
			mostrarErrorEn( panelInstrucciones, textos.errorJuego || 'No se pudo cargar el juego.' );
		};

		document.head.appendChild( etiqueta );
	}

	// -----------------------------------------------------------------
	// Barra de progreso de la descarga
	// -----------------------------------------------------------------

	var cajaProgreso = document.getElementById( 'ntvs-progreso' );
	var barraProgreso = document.getElementById( 'ntvs-progreso-barra' );
	var pctDescarga = null;

	function mostrarProgreso() {
		if ( cajaProgreso ) {
			cajaProgreso.hidden = false;
		}
	}

	function ocultarProgreso() {
		if ( cajaProgreso ) {
			cajaProgreso.hidden = true;
		}
	}

	/**
	 * Fija el avance.
	 *
	 * @param {number|null} pct Porcentaje, o null si no se puede saber.
	 */
	function ponerProgreso( pct ) {
		pctDescarga = pct;

		if ( barraProgreso ) {
			if ( null === pct ) {
				// Sin cifra: la barra se llena a medias y se deja animada por CSS.
				barraProgreso.style.width = '100%';
				barraProgreso.parentNode.classList.add( 'ntvs-progreso--indeterminado' );
			} else {
				barraProgreso.parentNode.classList.remove( 'ntvs-progreso--indeterminado' );
				barraProgreso.style.width = pct + '%';
			}
		}

		if ( cajaProgreso ) {
			cajaProgreso.setAttribute( 'aria-valuenow', null === pct ? '0' : String( pct ) );
		}

		refrescarBotonIniciar();
	}

	/** Muestra un error dentro de un panel que no tiene su propia caja. */
	function mostrarErrorEn( panel, mensaje ) {
		var caja = panel.querySelector( '.ntvs-error' );

		if ( ! caja ) {
			caja = document.createElement( 'p' );
			caja.className = 'ntvs-error';
			caja.setAttribute( 'role', 'alert' );
			panel.insertBefore( caja, panel.firstChild );
		}

		caja.textContent = mensaje;
		caja.hidden = false;
	}

	if ( botonIniciar ) {
		botonIniciar.addEventListener( 'click', function () {
			if ( ! juegoListo || juegoFallo || ! sesion ) {
				return;
			}

			botonIniciar.disabled = true;
			botonIniciar.textContent = textos.preparando || 'Preparando la pista…';

			// Este POST es el que gasta el intento: el servidor marca la sesión
			// como consumida y recién entonces entrega el seed de la pista.
			fetch( cfg.endpointIniciar, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { token: sesion.token } )
			} )
				.then( function ( respuesta ) {
					return respuesta.text().then( function ( texto ) {
						var datos;
						try {
							datos = JSON.parse( texto );
						} catch ( e ) {
							throw new Error( textos.errorServidor || 'El servidor no respondió como esperábamos.' );
						}
						if ( ! respuesta.ok ) {
							throw new Error( datos.message || textos.errorGeneral );
						}
						return datos;
					} );
				} )
				.then( function ( carrera ) {
					panelInstrucciones.hidden = true;
					panelJuego.hidden = false;

					// El mando aparece con el juego: antes de eso no hay nada
					// que controlar y solo sería ruido en la pantalla.
					var mando = document.getElementById( 'ntvs-mando' );
					if ( mando ) {
						mando.hidden = false;
					}

					window.navidadTvsIniciarJuego( {
						token: sesion.token,
						nombre: carrera.nombre || sesion.nombre,
						seed: carrera.seed,
						urlTema: cfg.urlTema,
						alTerminar: mostrarResultado
					} );
				} )
				.catch( function ( error ) {
					refrescarBotonIniciar();
					mostrarErrorEn( panelInstrucciones, error.message || textos.errorGeneral );
				} );
		} );
	}

	/**
	 * Resultado al terminar la carrera.
	 *
	 * Lo que el navegador calculó se muestra enseguida, pero es provisional: la
	 * distancia que cuenta la calcula el servidor reejecutando el registro de
	 * entradas. Hasta que confirme, el intento no existe.
	 */
	function mostrarResultado( resultado ) {
		var caja = document.getElementById( 'ntvs-resultado' );
		if ( ! caja ) {
			return;
		}

		pon( 'ntvs-res-nombre', resultado.nombre );
		pon( 'ntvs-res-distancia', resultado.distancia + ' m' );
		pon( 'ntvs-res-logos', resultado.items );
		pon( 'ntvs-res-caidas', resultado.caidas );
		pon( 'ntvs-res-sobrecal', resultado.sobrecalentamientos );

		caja.hidden = false;
		caja.scrollIntoView( { block: 'start', behavior: 'smooth' } );

		enviarResultado( resultado, 0 );
	}

	function pon( id, valor ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.textContent = valor;
		}
	}

	/**
	 * Cuántas veces se reintenta solo antes de pedirle al participante que lo
	 * haga él, y cuánto se espera entre intentos.
	 *
	 * Hay un solo intento por persona y lo que no llega al servidor no existe,
	 * así que un bache de red de dos segundos no puede costar la participación.
	 * Pero tampoco se puede reintentar indefinidamente en silencio: si la
	 * conexión está caída de verdad, el participante tiene que enterarse
	 * mientras todavía está delante de la pantalla.
	 */
	var REINTENTOS = 3;
	var ESPERAS_MS = [ 1500, 4000, 9000 ];

	function enviarResultado( resultado, intento ) {
		var aviso = document.getElementById( 'ntvs-envio' );
		var boton = document.getElementById( 'ntvs-reintentar' );
		var gracias = document.getElementById( 'ntvs-res-gracias' );

		if ( boton ) {
			boton.hidden = true;
		}

		if ( aviso ) {
			aviso.className = 'ntvs-envio ntvs-envio--curso';
			aviso.textContent = textos.enviando || 'Enviando tu resultado…';
		}

		if ( ! cfg.endpointTerminar ) {
			fallo( textos.errorGeneral );
			return;
		}

		fetch( cfg.endpointTerminar, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( {
				token: resultado.token,
				entradas: resultado.entradas,
				distancia: resultado.distancia
			} )
		} )
			.then( function ( respuesta ) {
				return respuesta.text().then( function ( texto ) {
					var datos;
					try {
						datos = JSON.parse( texto );
					} catch ( e ) {
						throw { reintentable: true, mensaje: textos.errorServidor };
					}
					if ( ! respuesta.ok ) {
						/*
						 * Un 4xx es una decisión del servidor y no va a cambiar
						 * por reintentar: sesión inválida, ya participó,
						 * registro corrupto. Un 5xx sí puede ser pasajero.
						 */
						throw {
							reintentable: respuesta.status >= 500,
							mensaje: datos.message || textos.envioRechazado
						};
					}
					return datos;
				} );
			} )
			.then( function ( oficial ) {
				// A partir de aquí manda el servidor, no lo que vio la pantalla.
				pon( 'ntvs-res-distancia', oficial.distancia + ' m' );
				pon( 'ntvs-res-logos', oficial.llaves );
				pon( 'ntvs-res-caidas', oficial.caidas );

				if ( aviso ) {
					aviso.className = 'ntvs-envio ntvs-envio--ok';
					aviso.textContent = oficial.valido
						? ( textos.enviado || 'Resultado registrado.' )
						: ( textos.envioNoValido || 'Resultado registrado para revisión.' );
				}

				if ( gracias ) {
					gracias.hidden = false;
				}
			} )
			.catch( function ( error ) {
				var reintentable = ! error || error.reintentable !== false;
				var mensaje = ( error && error.mensaje ) ? error.mensaje : textos.envioFallo;

				if ( reintentable && intento < REINTENTOS ) {
					if ( aviso ) {
						aviso.textContent = ( textos.enviando || 'Enviando tu resultado…' ) +
							' (' + ( intento + 2 ) + '/' + ( REINTENTOS + 1 ) + ')';
					}
					window.setTimeout( function () {
						enviarResultado( resultado, intento + 1 );
					}, ESPERAS_MS[ intento ] );
					return;
				}

				fallo( mensaje, reintentable, resultado );
			} );

		function fallo( mensaje, reintentable, res ) {
			if ( aviso ) {
				aviso.className = 'ntvs-envio ntvs-envio--error';
				aviso.textContent = mensaje || textos.envioFallo;
			}

			if ( boton && reintentable && res ) {
				boton.hidden = false;
				boton.onclick = function () {
					enviarResultado( res, 0 );
				};
			}
		}
	}

} )();
