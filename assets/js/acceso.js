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
	var avisoRotar         = document.getElementById( 'ntvs-rotar' );

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
	// Orientación del dispositivo
	// -----------------------------------------------------------------

	function revisarOrientacion() {
		if ( ! avisoRotar ) {
			return;
		}

		// Solo tiene sentido en pantallas de teléfono o tablet.
		var esTactil = window.matchMedia( '(hover: none)' ).matches;
		var vertical = window.innerHeight > window.innerWidth;

		avisoRotar.hidden = ! ( esTactil && vertical );
	}

	window.addEventListener( 'resize', revisarOrientacion );
	window.addEventListener( 'orientationchange', revisarOrientacion );

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

	function mostrarInstrucciones() {
		var etiquetaNombre = document.getElementById( 'ntvs-nombre-jugador' );
		if ( etiquetaNombre ) {
			etiquetaNombre.textContent = sesion.nombre;
		}

		panelAcceso.hidden = true;
		panelInstrucciones.hidden = false;
		revisarOrientacion();
		panelInstrucciones.scrollIntoView( { block: 'start', behavior: 'smooth' } );

		// El bundle se descarga mientras el participante lee las
		// instrucciones. Son unos segundos que de otro modo se perderían
		// después de pulsar el botón, con el reloj ya en marcha.
		precargarJuego();
	}

	function precargarJuego() {
		if ( ! botonIniciar ) {
			return;
		}

		if ( typeof window.navidadTvsIniciarJuego === 'function' ) {
			juegoListo = true;
			return;
		}

		botonIniciar.disabled = true;
		botonIniciar.textContent = textos.cargando || 'Cargando el juego…';

		var etiqueta = document.createElement( 'script' );
		etiqueta.src = cfg.urlJuego;
		etiqueta.async = true;

		etiqueta.onload = function () {
			juegoListo = true;
			botonIniciar.disabled = false;
			botonIniciar.textContent = textos.listo || 'Iniciar carrera';
		};

		etiqueta.onerror = function () {
			juegoFallo = true;
			botonIniciar.disabled = true;
			mostrarErrorEn( panelInstrucciones, textos.errorJuego || 'No se pudo cargar el juego.' );
		};

		document.head.appendChild( etiqueta );
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

					window.navidadTvsIniciarJuego( {
						token: sesion.token,
						nombre: carrera.nombre || sesion.nombre,
						seed: carrera.seed,
						urlTema: cfg.urlTema,
						alTerminar: mostrarResultado
					} );
				} )
				.catch( function ( error ) {
					botonIniciar.disabled = false;
					botonIniciar.textContent = textos.listo || 'Iniciar carrera';
					mostrarErrorEn( panelInstrucciones, error.message || textos.errorGeneral );
				} );
		} );
	}

	/**
	 * Resultado al terminar la carrera.
	 *
	 * En E6 esto envía el registro de entradas al servidor, que reejecuta la
	 * carrera y calcula la distancia oficial. Por ahora solo muestra lo que
	 * calculó el navegador, que es informativo y no cuenta para nada.
	 */
	function mostrarResultado( resultado ) {
		var caja = document.getElementById( 'ntvs-resultado' );
		if ( ! caja ) {
			return;
		}

		var pon = function ( id, valor ) {
			var el = document.getElementById( id );
			if ( el ) {
				el.textContent = valor;
			}
		};

		pon( 'ntvs-res-nombre', resultado.nombre );
		pon( 'ntvs-res-distancia', resultado.distancia + ' m' );
		pon( 'ntvs-res-logos', resultado.items );
		pon( 'ntvs-res-caidas', resultado.caidas );
		pon( 'ntvs-res-sobrecal', resultado.sobrecalentamientos );
		pon( 'ntvs-res-bytes', resultado.entradas.length );

		caja.hidden = false;
		caja.scrollIntoView( { block: 'start', behavior: 'smooth' } );

		// Queda a mano para poder revisarlo desde la consola mientras E6 no
		// exista.
		window.navidadTvsUltimoResultado = resultado;
	}

	revisarOrientacion();
} )();
