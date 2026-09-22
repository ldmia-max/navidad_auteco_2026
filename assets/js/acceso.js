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
				return respuesta.json().then( function ( datos ) {
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

	function mostrarInstrucciones() {
		var etiquetaNombre = document.getElementById( 'ntvs-nombre-jugador' );
		if ( etiquetaNombre ) {
			etiquetaNombre.textContent = sesion.nombre;
		}

		panelAcceso.hidden = true;
		panelInstrucciones.hidden = false;
		revisarOrientacion();
		panelInstrucciones.scrollIntoView( { block: 'start', behavior: 'smooth' } );
	}

	var botonIniciar = document.getElementById( 'ntvs-iniciar' );

	if ( botonIniciar ) {
		botonIniciar.addEventListener( 'click', function () {
			panelInstrucciones.hidden = true;
			panelJuego.hidden = false;

			var etiquetaToken = document.getElementById( 'ntvs-token' );
			if ( etiquetaToken && sesion ) {
				etiquetaToken.textContent = sesion.token;
			}

			// En E4 aquí arranca el countdown 3-2-1 y luego la carrera. El
			// cronómetro no corre durante el countdown.
			if ( typeof window.navidadTvsIniciarJuego === 'function' ) {
				window.navidadTvsIniciarJuego( sesion );
			}
		} );
	}

	revisarOrientacion();
} )();
