<?php

declare(strict_types=1);

namespace ProtocoloElementor\Protocol;

final class Mailer {

	/**
	 * @param array<string, mixed> $fields
	 */
	public function send_protocol( string $protocol, array $fields ): bool {
		$email = $this->resolve_email( $fields );

		if ( '' === $email || ! is_email( $email ) ) {
			return false;
		}

		$name    = $this->field_value( $fields, 'name' );
		$subject = sprintf(
			/* translators: %s: protocol number */
			__( 'Seu protocolo: %s', 'protocolo-elementor' ),
			$protocol
		);

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		$body = $this->build_html_body( $protocol, $name );

		return (bool) wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private function resolve_email( array $fields ): string {
		$email = $this->field_value( $fields, 'email' );

		if ( '' !== $email && is_email( $email ) ) {
			return $email;
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = strtolower( (string) ( $field['type'] ?? '' ) );

			if ( 'email' !== $type ) {
				continue;
			}

			$value = $this->extract_scalar( $field['value'] ?? '' );

			if ( '' !== $value && is_email( $value ) ) {
				return $value;
			}
		}

		foreach ( $fields as $field ) {
			$value = is_array( $field )
				? $this->extract_scalar( $field['value'] ?? '' )
				: $this->extract_scalar( $field );

			if ( '' !== $value && is_email( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private function field_value( array $fields, string $id ): string {
		if ( ! array_key_exists( $id, $fields ) ) {
			return '';
		}

		$value = $fields[ $id ];

		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			$value = $value['value'];
		}

		return $this->extract_scalar( $value );
	}

	/**
	 * @param mixed $value
	 */
	private function extract_scalar( $value ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	private function build_html_body( string $protocol, string $name ): string {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$greeting  = '' !== $name
			? sprintf(
				/* translators: %s: person name */
				__( 'Olá, %s.', 'protocolo-elementor' ),
				$name
			)
			: __( 'Olá.', 'protocolo-elementor' );

		$intro   = __( 'Recebemos o seu envio com sucesso. O número do seu protocolo é:', 'protocolo-elementor' );
		$label   = __( 'Protocolo', 'protocolo-elementor' );
		$outro   = __( 'Guarde este número. Ele será necessário para consultas futuras.', 'protocolo-elementor' );
		$sign    = sprintf(
			/* translators: %s: site name */
			__( 'Atenciosamente,<br>%s', 'protocolo-elementor' ),
			esc_html( $site_name )
		);

		$protocol_escaped = esc_html( $protocol );
		$greeting_escaped = esc_html( $greeting );
		$intro_escaped    = esc_html( $intro );
		$label_escaped    = esc_html( $label );
		$outro_escaped    = esc_html( $outro );
		$site_escaped     = esc_html( $site_name );

		return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$site_escaped}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Georgia,'Times New Roman',serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border:1px solid #e5e7eb;">
					<tr>
						<td style="padding:28px 32px 12px;border-bottom:1px solid #e5e7eb;">
							<p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:#6b7280;">
								{$site_escaped}
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding:36px 32px 40px;">
							<p style="margin:0 0 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:16px;line-height:1.5;color:#111827;">
								{$greeting_escaped}
							</p>
							<p style="margin:0 0 28px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;line-height:1.6;color:#374151;">
								{$intro_escaped}
							</p>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;">
								<tr>
									<td align="center" style="padding:28px 20px;">
										<p style="margin:0 0 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:#6b7280;">
											{$label_escaped}
										</p>
										<p style="margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:26px;line-height:1.3;letter-spacing:0.06em;color:#111827;font-weight:600;">
											{$protocol_escaped}
										</p>
									</td>
								</tr>
							</table>
							<p style="margin:28px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.6;color:#6b7280;">
								{$outro_escaped}
							</p>
							<p style="margin:32px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:14px;line-height:1.6;color:#374151;">
								{$sign}
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
HTML;
	}
}
