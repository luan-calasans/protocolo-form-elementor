<?php

declare(strict_types=1);

namespace ProtocoloElementor\Protocol;

use ProtocoloElementor\Elementor\SubmissionRepository;
use ProtocoloElementor\Plugin;

final class Validator {

	/** @var Fingerprint */
	private $fingerprint;

	/** @var SubmissionRepository */
	private $repository;

	/** @var Generator */
	private $generator;

	public function __construct( Fingerprint $fingerprint, SubmissionRepository $repository ) {
		$this->fingerprint = $fingerprint;
		$this->repository  = $repository;
		$this->generator   = new Generator( $fingerprint, $repository );
	}

	/**
	 * @return array{valid:bool,message:string,data?:array<string,mixed>}
	 */
	public function validate( string $protocol ): array {
		$protocol = preg_replace( '/\D+/', '', $protocol );
		$protocol = is_string( $protocol ) ? $protocol : '';

		if ( 18 !== strlen( $protocol ) || ! ctype_digit( $protocol ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Informe um protocolo com exatamente 18 dígitos numéricos.', 'protocolo-elementor' ),
			);
		}

		$submission = $this->repository->find_by_protocol( $protocol );

		if ( null === $submission ) {
			return array(
				'valid'   => false,
				'message' => __( 'Protocolo não encontrado ou inválido.', 'protocolo-elementor' ),
			);
		}

		$datetime_prefix = substr( $protocol, 0, 14 );
		$stored_datetime = (string) ( $submission['meta'][ Plugin::META_DATETIME ] ?? '' );

		if ( '' === $stored_datetime ) {
			$stored_datetime = $this->repository->submission_datetime_prefix( $submission );
		}

		if ( $datetime_prefix !== $stored_datetime ) {
			return array(
				'valid'   => false,
				'message' => __( 'Protocolo inválido.', 'protocolo-elementor' ),
			);
		}

		$microseconds = (int) ( $submission['meta'][ Plugin::META_MICROSECONDS ] ?? 0 );
		$attempt      = (int) ( $submission['meta'][ Plugin::META_ATTEMPT ] ?? 0 );
		$timestamp    = (int) ( $submission['meta'][ Plugin::META_TIMESTAMP ] ?? 0 );
		$form_id      = (string) ( $submission['meta'][ Plugin::META_FORM_ID ] ?? $submission['form_id'] );
		$page_id      = (int) $submission['page_id'];
		$form_name    = (string) $submission['form_name'];
		$fields = is_array( $submission['fields'] ?? null ) ? $submission['fields'] : array();

		if ( $timestamp <= 0 && 14 === strlen( $datetime_prefix ) ) {
			try {
				$timezone  = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
				$dt        = \DateTimeImmutable::createFromFormat( 'YmdHis', $datetime_prefix, $timezone );
				$timestamp = $dt ? (int) $dt->format( 'U' ) : 0;
			} catch ( \Exception $e ) {
				$timestamp = 0;
			}
		}

		$stored_fingerprint = (string) ( $submission['meta'][ Plugin::META_FINGERPRINT ] ?? '' );

		// Preferencialmente reconstrói com o HMAC persistido; senão, a partir dos campos da submissão.
		$payload_fingerprint = '' !== $stored_fingerprint
			? $stored_fingerprint
			: $this->fingerprint->from_fields( $fields );

		$expected_signature = $this->generator->build_signature(
			$timestamp,
			$microseconds,
			(int) $submission['id'],
			$form_name,
			$form_id,
			$page_id,
			$payload_fingerprint,
			$attempt
		);

		$expected_protocol = $datetime_prefix . $expected_signature;

		if ( ! hash_equals( $expected_protocol, $protocol ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Protocolo inválido.', 'protocolo-elementor' ),
			);
		}

		$created = $this->repository->format_submission_datetime( $submission );

		return array(
			'valid'   => true,
			'message' => __( 'Protocolo válido.', 'protocolo-elementor' ),
			'data'    => array(
				'protocol'      => $protocol,
				'date'          => $created['date'],
				'time'          => $created['time'],
				'submission_id' => (int) $submission['id'],
				'form_name'     => $form_name,
				'form_id'       => $form_id !== '' ? $form_id : null,
				'name'          => $this->field_value( $fields, 'name' ),
				'email'         => $this->field_value( $fields, 'email' ),
			),
		);
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

		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}

		return trim( (string) $value );
	}
}
