<?php

declare(strict_types=1);

namespace ProtocoloElementor\Protocol;

use ProtocoloElementor\Plugin;

final class Fingerprint {

	/**
	 * @param array<string, mixed> $fields Mapa id => valor (ou estrutura de campo Elementor).
	 */
	public function from_fields( array $fields ): string {
		$normalized = $this->normalize_fields( $fields );

		$payload = wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $payload ) ) {
			$payload = '';
		}

		return hash_hmac( 'sha256', $payload, $this->secret() );
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, string>
	 */
	public function normalize_fields( array $fields ): array {
		$normalized = array();

		foreach ( $fields as $key => $field ) {
			$key = (string) $key;

			if ( $this->should_skip_key( $key ) ) {
				continue;
			}

			$value = $this->extract_value( $field );

			if ( null === $value || '' === $value ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$value = is_string( $value ) ? $value : '';
			}

			if ( '' === $value ) {
				continue;
			}

			$normalized[ $key ] = $value;
		}

		ksort( $normalized, SORT_STRING );

		return $normalized;
	}

	private function should_skip_key( string $key ): bool {
		if ( Plugin::is_plugin_meta_key( $key ) ) {
			return true;
		}

		$skip = array( 'submit', 'recaptcha', 'honeypot', 'password' );

		return in_array( strtolower( $key ), $skip, true );
	}

	/**
	 * @param mixed $field
	 * @return string|array<string, mixed>|null
	 */
	private function extract_value( $field ) {
		if ( is_array( $field ) ) {
			if ( array_key_exists( 'value', $field ) ) {
				return $this->normalize_scalar( $field['value'] );
			}

			$nested = array();

			foreach ( $field as $nested_key => $nested_value ) {
				$normalized = $this->normalize_scalar( $nested_value );

				if ( null !== $normalized ) {
					$nested[ (string) $nested_key ] = $normalized;
				}
			}

			return $nested ?: null;
		}

		return $this->normalize_scalar( $field );
	}

	/**
	 * @param mixed $value
	 * @return string|null
	 */
	private function normalize_scalar( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();

			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$parts[] = trim( (string) $item );
				}
			}

			sort( $parts, SORT_STRING );

			return implode( ',', $parts );
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		return trim( (string) $value );
	}

	public function secret(): string {
		return (string) wp_salt( 'auth' );
	}
}
