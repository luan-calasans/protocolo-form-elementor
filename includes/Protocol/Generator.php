<?php

declare(strict_types=1);

namespace ProtocoloElementor\Protocol;

use ProtocoloElementor\Elementor\SubmissionRepository;
use DateTimeImmutable;
use DateTimeZone;

final class Generator {

	private const MAX_ATTEMPTS = 20;

	/** @var Fingerprint */
	private $fingerprint;

	/** @var SubmissionRepository */
	private $repository;

	public function __construct( Fingerprint $fingerprint, SubmissionRepository $repository ) {
		$this->fingerprint = $fingerprint;
		$this->repository  = $repository;
	}

	/**
	 * @param array{
	 *     submission_id:int,
	 *     form_name:string,
	 *     form_id:string,
	 *     page_id:int,
	 *     fields:array<string,mixed>
	 * } $context
	 * @return array{protocol:string,datetime:string,timestamp:int,microseconds:int,attempt:int,fingerprint:string}|null
	 */
	public function generate( array $context ): ?array {
		$submission_id = (int) ( $context['submission_id'] ?? 0 );
		$form_name     = (string) ( $context['form_name'] ?? '' );
		$form_id       = (string) ( $context['form_id'] ?? '' );
		$page_id       = (int) ( $context['page_id'] ?? 0 );
		$fields        = is_array( $context['fields'] ?? null ) ? $context['fields'] : array();

		if ( $submission_id <= 0 || '' === $form_name ) {
			return null;
		}

		$instant      = $this->capture_instant();
		$datetime     = $instant['datetime'];
		$timestamp    = $instant['timestamp'];
		$microseconds = $instant['microseconds'];
		$fingerprint  = $this->fingerprint->from_fields( $fields );

		for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++ ) {
			$signature = $this->build_signature(
				$timestamp,
				$microseconds,
				$submission_id,
				$form_name,
				$form_id,
				$page_id,
				$fingerprint,
				$attempt
			);

			$protocol = $datetime . $signature;

			if ( ! $this->repository->protocol_exists( $protocol ) ) {
				return array(
					'protocol'     => $protocol,
					'datetime'     => $datetime,
					'timestamp'    => $timestamp,
					'microseconds' => $microseconds,
					'attempt'      => $attempt,
					'fingerprint'  => $fingerprint,
				);
			}
		}

		return null;
	}

	public function build_signature(
		int $timestamp,
		int $microseconds,
		int $submission_id,
		string $form_name,
		string $form_id,
		int $page_id,
		string $payload_fingerprint,
		int $attempt
	): string {
		$input = implode(
			'|',
			array(
				(string) $timestamp,
				(string) $microseconds,
				(string) $submission_id,
				$form_name,
				$form_id,
				(string) $page_id,
				$payload_fingerprint,
				(string) $attempt,
			)
		);

		$hmac = hash_hmac( 'sha256', $input, $this->fingerprint->secret() );

		// 8 hex chars cobrem 32 bits; módulo 10000 produz 0000–9999 de forma estável.
		$numeric = hexdec( substr( $hmac, 0, 8 ) ) % 10000;

		return str_pad( (string) $numeric, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * @return array{datetime:string,timestamp:int,microseconds:int}
	 */
	private function capture_instant(): array {
		$now     = microtime( true );
		$seconds = (int) floor( $now );
		$micros  = (int) round( ( $now - $seconds ) * 1000000 );

		if ( $micros >= 1000000 ) {
			++$seconds;
			$micros = 0;
		}

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$dt       = ( new DateTimeImmutable( '@' . $seconds ) )->setTimezone( $timezone );

		return array(
			'datetime'     => $dt->format( 'YmdHis' ),
			'timestamp'    => $seconds,
			'microseconds' => $micros,
		);
	}
}
