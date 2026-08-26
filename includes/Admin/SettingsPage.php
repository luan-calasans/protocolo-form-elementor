<?php

declare(strict_types=1);

namespace ProtocoloElementor\Admin;

use ProtocoloElementor\Plugin;

final class SettingsPage {

	private const MENU_SLUG = 'protocolo-elementor';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Protocolos', 'protocolo-elementor' ),
			__( 'Protocolos', 'protocolo-elementor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-id',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Configurações', 'protocolo-elementor' ),
			__( 'Configurações', 'protocolo-elementor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'protocolo_elementor_settings',
			Plugin::OPTION_FORM_NAMES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_form_names' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $value
	 * @return string[]
	 */
	public function sanitize_form_names( $value ): array {
		if ( is_string( $value ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $value );
			$value = is_array( $lines ) ? $lines : array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $name ) {
			$name = sanitize_text_field( (string) $name );
			$name = trim( $name );

			if ( '' !== $name ) {
				$clean[] = $name;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$names = Plugin::enabled_form_names();
		$text  = implode( "\n", $names );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Protocolos — Configurações', 'protocolo-elementor' ); ?></h1>
			<p>
				<?php echo esc_html__( 'Informe os Form Name do Elementor Pro que devem gerar protocolo automaticamente. Um por linha.', 'protocolo-elementor' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'protocolo_elementor_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="protocolo_elementor_form_names">
								<?php echo esc_html__( 'Form Names habilitados', 'protocolo-elementor' ); ?>
							</label>
						</th>
						<td>
							<textarea
								name="<?php echo esc_attr( Plugin::OPTION_FORM_NAMES ); ?>"
								id="protocolo_elementor_form_names"
								rows="8"
								cols="50"
								class="large-text code"
							><?php echo esc_textarea( $text ); ?></textarea>
							<p class="description">
								<?php echo esc_html__( 'Deve coincidir com o campo Form Name nas configurações do formulário Elementor. O formulário precisa ter a ação “Collect Submissions” ativa.', 'protocolo-elementor' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Salvar configurações', 'protocolo-elementor' ) ); ?>
			</form>
		</div>
		<?php
	}
}
