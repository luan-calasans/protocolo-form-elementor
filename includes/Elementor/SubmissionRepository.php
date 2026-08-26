<?php

declare(strict_types=1);

namespace ProtocoloElementor\Elementor;

use ProtocoloElementor\Plugin;
use DateTimeImmutable;
use DateTimeZone;

final class SubmissionRepository {

	/** @var string|null */
	private $submissions_table;

	/** @var string|null */
	private $values_table;

	public function submissions_table(): string {
		if ( null === $this->submissions_table ) {
			$this->resolve_tables();
		}

		return (string) $this->submissions_table;
	}

	public function values_table(): string {
		if ( null === $this->values_table ) {
			$this->resolve_tables();
		}

		return (string) $this->values_table;
	}

	public function protocol_exists( string $protocol ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT id FROM {$this->values_table()} WHERE `key` IN (%s, %s) AND `value` = %s LIMIT 1",
			Plugin::META_PROTOCOL,
			Plugin::LEGACY_META_PROTOCOL,
			$protocol
		);

		return (bool) $wpdb->get_var( $sql );
	}

	/**
	 * @param array<string, string|int> $meta
	 */
	public function save_protocol_meta( int $submission_id, array $meta ): bool {
		if ( $submission_id <= 0 || empty( $meta[ Plugin::META_PROTOCOL ] ) ) {
			return false;
		}

		foreach ( $meta as $key => $value ) {
			$this->upsert_value( $submission_id, (string) $key, (string) $value );
		}

		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find_by_protocol( string $protocol ): ?array {
		global $wpdb;

		$submission_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT submission_id FROM {$this->values_table()} WHERE `key` IN (%s, %s) AND `value` = %s LIMIT 1",
				Plugin::META_PROTOCOL,
				Plugin::LEGACY_META_PROTOCOL,
				$protocol
			)
		);

		if ( $submission_id <= 0 ) {
			return null;
		}

		return $this->get_submission( $submission_id );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_submission( int $submission_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->submissions_table()} WHERE id = %d LIMIT 1",
				$submission_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$values = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `key`, `value` FROM {$this->values_table()} WHERE submission_id = %d",
				$submission_id
			),
			ARRAY_A
		);

		$meta   = array();
		$fields = array();

		if ( is_array( $values ) ) {
			foreach ( $values as $value_row ) {
				$key = (string) ( $value_row['key'] ?? '' );
				$val = (string) ( $value_row['value'] ?? '' );

				if ( '' === $key ) {
					continue;
				}

				if ( Plugin::is_plugin_meta_key( $key ) ) {
					$meta[ Plugin::canonical_meta_key( $key ) ] = $val;
					continue;
				}

				$fields[ $key ] = $val;
			}
		}

		$element_id = (string) ( $row['element_id'] ?? '' );

		return array(
			'id'         => (int) $row['id'],
			'form_name'  => (string) ( $row['form_name'] ?? '' ),
			'form_id'    => $element_id,
			'page_id'    => (int) ( $row['post_id'] ?? 0 ),
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'meta'       => $meta,
			'fields'     => $fields,
		);
	}

	/**
	 * @param array<string, mixed> $submission
	 */
	public function submission_datetime_prefix( array $submission ): string {
		$created_at = (string) ( $submission['created_at'] ?? '' );

		if ( '' === $created_at ) {
			return '';
		}

		try {
			$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$dt       = new DateTimeImmutable( $created_at, $timezone );

			return $dt->format( 'YmdHis' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * @param array<string, mixed> $submission
	 * @return array{date:string,time:string}
	 */
	public function format_submission_datetime( array $submission ): array {
		$datetime = (string) ( $submission['meta'][ Plugin::META_DATETIME ] ?? '' );

		if ( 14 === strlen( $datetime ) && ctype_digit( $datetime ) ) {
			return array(
				'date' => substr( $datetime, 0, 4 ) . '-' . substr( $datetime, 4, 2 ) . '-' . substr( $datetime, 6, 2 ),
				'time' => substr( $datetime, 8, 2 ) . ':' . substr( $datetime, 10, 2 ) . ':' . substr( $datetime, 12, 2 ),
			);
		}

		$created_at = (string) ( $submission['created_at'] ?? '' );

		try {
			$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$dt       = new DateTimeImmutable( $created_at, $timezone );

			return array(
				'date' => $dt->format( 'Y-m-d' ),
				'time' => $dt->format( 'H:i:s' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'date' => '',
				'time' => '',
			);
		}
	}

	private function upsert_value( int $submission_id, string $key, string $value ): void {
		global $wpdb;

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->values_table()} WHERE submission_id = %d AND `key` = %s LIMIT 1",
				$submission_id,
				$key
			)
		);

		if ( $existing_id > 0 ) {
			$wpdb->update(
				$this->values_table(),
				array( 'value' => $value ),
				array( 'id' => $existing_id ),
				array( '%s' ),
				array( '%d' )
			);

			return;
		}

		$wpdb->insert(
			$this->values_table(),
			array(
				'submission_id' => $submission_id,
				'key'           => $key,
				'value'         => $value,
			),
			array( '%d', '%s', '%s' )
		);
	}

	private function resolve_tables(): void {
		global $wpdb;

		if ( class_exists( '\ElementorPro\Modules\Forms\Submissions\Database\Query' ) ) {
			$query = \ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance();

			if ( method_exists( $query, 'get_table_submissions' ) ) {
				$this->submissions_table = (string) $query->get_table_submissions();
			}

			if ( method_exists( $query, 'get_table_submissions_values' ) ) {
				$this->values_table = (string) $query->get_table_submissions_values();
			}

			// Algumas versões expõem propriedades públicas em vez de getters.
			if ( empty( $this->submissions_table ) && isset( $query->table_submissions ) ) {
				$this->submissions_table = (string) $query->table_submissions;
			}

			if ( empty( $this->values_table ) && isset( $query->table_submissions_values ) ) {
				$this->values_table = (string) $query->table_submissions_values;
			}
		}

		if ( empty( $this->submissions_table ) ) {
			$this->submissions_table = $wpdb->prefix . 'e_submissions';
		}

		if ( empty( $this->values_table ) ) {
			$this->values_table = $wpdb->prefix . 'e_submissions_values';
		}
	}
}
