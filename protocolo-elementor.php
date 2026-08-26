<?php
/**
 * Plugin Name:       Protocolo Elementor
 * Description:       Gera, armazena e valida protocolos de 18 dígitos para envios de formulários do Elementor Pro, usando o sistema nativo de Submissions.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Luan Calasans
 * License:           GPL-2.0-or-later
 * Text Domain:       protocolo-elementor
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PROTOCOLO_ELEMENTOR_VERSION', '1.1.0' );
define( 'PROTOCOLO_ELEMENTOR_FILE', __FILE__ );
define( 'PROTOCOLO_ELEMENTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROTOCOLO_ELEMENTOR_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'ProtocoloElementor\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = PROTOCOLO_ELEMENTOR_PATH . 'includes/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\ProtocoloElementor\Plugin::instance()->boot();
	},
	20
);
