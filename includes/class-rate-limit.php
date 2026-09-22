<?php
/**
 * Limitador de intentos.
 *
 * Frena la enumeración de teléfonos: sin esto, cualquiera puede recorrer el
 * rango de celulares colombianos preguntando cuáles compraron una moto TVS.
 *
 * Solo se cuentan los intentos FALLIDOS. Consultar y registrar son dos
 * operaciones separadas a propósito: si contara también los aciertos, en la
 * ventana de 90 minutos —donde todo el tráfico del día llega junto y buena
 * parte de los participantes comparte IP de salida por el CGNAT de los
 * operadores móviles— los primeros en entrar dejarían bloqueados a los demás.
 *
 * Se apoya en transients, que aquí viven en la tabla de opciones. Con 150
 * participantes al día eso es irrelevante; si algún día se monta Redis como
 * caché de objetos, los transients pasan a memoria sin cambiar nada de aquí.
 *
 * @package NavidadTVS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cuenta intentos fallidos por clave.
 */
class NavidadTVS_Rate_Limit {

	const PREFIJO = 'navidad_tvs_rl_';

	/**
	 * Indica si una clave ya superó el tope de fallos.
	 *
	 * Solo lee: no cuenta este intento.
	 *
	 * @param string $clave Identificador (ip, teléfono...).
	 * @param int    $tope  Fallos permitidos en la ventana.
	 * @return bool
	 */
	public static function bloqueado( $clave, $tope ) {
		if ( '' === $clave ) {
			return false;
		}

		return (int) get_transient( self::PREFIJO . md5( $clave ) ) >= $tope;
	}

	/**
	 * Suma un fallo a la cuenta de una clave.
	 *
	 * @param string $clave    Identificador.
	 * @param int    $segundos Duración de la ventana.
	 * @return void
	 */
	public static function registrar_fallo( $clave, $segundos ) {
		if ( '' === $clave ) {
			return;
		}

		$id     = self::PREFIJO . md5( $clave );
		$actual = (int) get_transient( $id );

		/*
		 * set_transient reinicia la expiración en cada escritura, así que la
		 * ventana sería deslizante y quien insista sin parar no se
		 * desbloquearía nunca. Se guarda el momento de inicio aparte para que
		 * la ventana sea fija.
		 */
		$inicio = get_transient( $id . '_t' );

		if ( false === $inicio ) {
			$inicio = time();
			set_transient( $id . '_t', $inicio, $segundos );
		}

		$restante = max( 1, $segundos - ( time() - (int) $inicio ) );
		set_transient( $id, $actual + 1, $restante );
	}

	/**
	 * Limpia el contador de una clave.
	 *
	 * Se llama cuando el intento fue exitoso: al participante legítimo no le
	 * cuentan los tropiezos previos.
	 *
	 * @param string $clave Identificador.
	 * @return void
	 */
	public static function limpiar( $clave ) {
		if ( '' === $clave ) {
			return;
		}

		$id = self::PREFIJO . md5( $clave );
		delete_transient( $id );
		delete_transient( $id . '_t' );
	}

	/**
	 * Devuelve la IP del visitante.
	 *
	 * Detrás de Cloudflare la IP real llega en CF-Connecting-IP; detrás de un
	 * proxy propio, en X-Forwarded-For. Esas cabeceras solo se confían si el
	 * sitio declara estar tras un proxy con NAVIDAD_TVS_TRAS_PROXY, porque de
	 * lo contrario cualquiera las falsifica para saltarse el límite.
	 *
	 * @return string Cadena vacía si no se pudo determinar.
	 */
	public static function ip() {
		$confiar = defined( 'NAVIDAD_TVS_TRAS_PROXY' ) && NAVIDAD_TVS_TRAS_PROXY;

		if ( $confiar ) {
			if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}

			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$lista = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
				$ip    = trim( $lista[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		$ip = ! empty( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
