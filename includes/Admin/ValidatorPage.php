<?php

declare(strict_types=1);

namespace ProtocoloElementor\Admin;

use ProtocoloElementor\Protocol\Validator;

final class ValidatorPage {

	private const MENU_SLUG = 'protocolo-elementor-validar';

	/** @var Validator */
	private $validator;

	public function __construct( Validator $validator ) {
		$this->validator = $validator;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'protocolo-elementor',
			__( 'Validar Protocolo', 'protocolo-elementor' ),
			__( 'Validar Protocolo', 'protocolo-elementor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$result   = null;
		$protocol = '';

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'protocolo_elementor_validate' );

			$protocol = isset( $_POST['protocolo_elementor_protocol'] )
				? sanitize_text_field( wp_unslash( (string) $_POST['protocolo_elementor_protocol'] ) )
				: '';

			$result = $this->validator->validate( $protocol );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Validar Protocolo', 'protocolo-elementor' ); ?></h1>
			<p>
				<?php echo esc_html__( 'Informe um protocolo de 18 dígitos para localizar e validar a submissão correspondente no Elementor.', 'protocolo-elementor' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'protocolo_elementor_validate' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="protocolo_elementor_protocol">
								<?php echo esc_html__( 'Protocolo', 'protocolo-elementor' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								name="protocolo_elementor_protocol"
								id="protocolo_elementor_protocol"
								value="<?php echo esc_attr( $protocol ); ?>"
								class="regular-text code"
								inputmode="numeric"
								pattern="[0-9]{18}"
								maxlength="18"
								autocomplete="off"
								required
							/>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Validar', 'protocolo-elementor' ) ); ?>
			</form>

			<?php if ( is_array( $result ) ) : ?>
				<?php if ( ! empty( $result['valid'] ) ) : ?>
					<div class="notice notice-success inline">
						<p><strong><?php echo esc_html( (string) $result['message'] ); ?></strong></p>
					</div>
					<?php if ( ! empty( $result['data'] ) && is_array( $result['data'] ) ) : ?>
						<table class="widefat striped" style="max-width:640px;margin-top:1em;">
							<tbody>
								<tr>
									<th><?php echo esc_html__( 'Protocolo', 'protocolo-elementor' ); ?></th>
									<td><code><?php echo esc_html( (string) $result['data']['protocol'] ); ?></code></td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'Nome', 'protocolo-elementor' ); ?></th>
									<td>
										<?php
										echo '' !== (string) ( $result['data']['name'] ?? '' )
											? esc_html( (string) $result['data']['name'] )
											: esc_html__( 'Não disponível', 'protocolo-elementor' );
										?>
									</td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'E-mail', 'protocolo-elementor' ); ?></th>
									<td>
										<?php
										echo '' !== (string) ( $result['data']['email'] ?? '' )
											? esc_html( (string) $result['data']['email'] )
											: esc_html__( 'Não disponível', 'protocolo-elementor' );
										?>
									</td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'Data', 'protocolo-elementor' ); ?></th>
									<td><?php echo esc_html( (string) $result['data']['date'] ); ?></td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'Hora', 'protocolo-elementor' ); ?></th>
									<td><?php echo esc_html( (string) $result['data']['time'] ); ?></td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'ID da submissão', 'protocolo-elementor' ); ?></th>
									<td><?php echo esc_html( (string) $result['data']['submission_id'] ); ?></td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'Nome do formulário', 'protocolo-elementor' ); ?></th>
									<td><?php echo esc_html( (string) $result['data']['form_name'] ); ?></td>
								</tr>
								<tr>
									<th><?php echo esc_html__( 'ID do formulário', 'protocolo-elementor' ); ?></th>
									<td>
										<?php
										echo ! empty( $result['data']['form_id'] )
											? esc_html( (string) $result['data']['form_id'] )
											: esc_html__( 'Não disponível', 'protocolo-elementor' );
										?>
									</td>
								</tr>
							</tbody>
						</table>
					<?php endif; ?>
				<?php else : ?>
					<div class="notice notice-error inline">
						<p><strong><?php echo esc_html( (string) $result['message'] ); ?></strong></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
