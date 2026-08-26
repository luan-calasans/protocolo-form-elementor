<?php

declare(strict_types=1);

namespace ProtocoloElementor\Elementor;

use ProtocoloElementor\Plugin;
use ProtocoloElementor\Protocol\Generator;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Submissions\Actions\Save_To_Database;
use ReflectionClass;
use ReflectionException;

final class SubmissionListener {

	/** @var int|null */
	private static $last_submission_id = null;

	/** @var Generator */
	private $generator;

	/** @var SubmissionRepository */
	private $repository;

	public function __construct( Generator $generator, SubmissionRepository $repository ) {
		$this->generator  = $generator;
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'elementor_pro/forms/actions/after_run', array( $this, 'capture_submission_id' ), 10, 2 );
		add_action( 'elementor_pro/forms/new_record', array( $this, 'handle_new_record' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_script' ) );
	}

	/**
	 * @param object      $action
	 * @param \Throwable|null $exception
	 */
	public function capture_submission_id( $action, $exception = null ): void {
		if ( null !== $exception ) {
			return;
		}

		if ( ! class_exists( Save_To_Database::class ) || ! ( $action instanceof Save_To_Database ) ) {
			return;
		}

		try {
			$reflection = new ReflectionClass( $action );

			if ( ! $reflection->hasProperty( 'submission_id' ) ) {
				return;
			}

			$property = $reflection->getProperty( 'submission_id' );
			$property->setAccessible( true );
			$submission_id = (int) $property->getValue( $action );

			if ( $submission_id > 0 ) {
				self::$last_submission_id = $submission_id;
			}
		} catch ( ReflectionException $e ) {
			// Mantém o último ID válido, se houver.
		}
	}

	/**
	 * @param Form_Record  $record
	 * @param Ajax_Handler $ajax_handler
	 */
	public function handle_new_record( $record, $ajax_handler ): void {
		$submission_id = self::$last_submission_id;
		self::$last_submission_id = null;

		if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) ) {
			return;
		}

		$form_name = trim( (string) $record->get_form_settings( 'form_name' ) );

		if ( ! Plugin::is_form_enabled( $form_name ) ) {
			return;
		}

		if ( ! $submission_id ) {
			return;
		}

		$form_id = (string) $record->get_form_settings( 'id' );
		$page_id = (int) $record->get_form_settings( 'form_post_id' );

		if ( $page_id <= 0 ) {
			$page_id = (int) $record->get_form_settings( 'edit_post_id' );
		}

		$fields = method_exists( $record, 'get' ) ? $record->get( 'fields' ) : array();
		$fields = is_array( $fields ) ? $fields : array();

		$result = $this->generator->generate(
			array(
				'submission_id' => $submission_id,
				'form_name'     => $form_name,
				'form_id'       => $form_id,
				'page_id'       => $page_id,
				'fields'        => $fields,
			)
		);

		if ( null === $result ) {
			return;
		}

		$saved = $this->repository->save_protocol_meta(
			$submission_id,
			array(
				Plugin::META_PROTOCOL     => $result['protocol'],
				Plugin::META_DATETIME     => $result['datetime'],
				Plugin::META_TIMESTAMP    => (string) $result['timestamp'],
				Plugin::META_MICROSECONDS => (string) $result['microseconds'],
				Plugin::META_ATTEMPT      => (string) $result['attempt'],
				Plugin::META_FORM_ID      => $form_id,
				Plugin::META_FINGERPRINT  => $result['fingerprint'],
			)
		);

		if ( ! $saved ) {
			return;
		}

		$this->append_protocol_to_success( $ajax_handler, $result['protocol'] );
	}

	public function enqueue_frontend_script(): void {
		if ( is_admin() ) {
			return;
		}

		wp_register_script(
			'protocolo-elementor-frontend',
			PROTOCOLO_ELEMENTOR_URL . 'assets/js/frontend-protocol.js',
			array( 'jquery' ),
			PROTOCOLO_ELEMENTOR_VERSION,
			true
		);

		wp_enqueue_script( 'protocolo-elementor-frontend' );
	}

	/**
	 * @param Ajax_Handler|object $ajax_handler
	 */
	private function append_protocol_to_success( $ajax_handler, string $protocol ): void {
		$line = sprintf(
			/* translators: %s: protocol number */
			__( 'Protocolo: %s', 'protocolo-elementor' ),
			$protocol
		);

		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_response_data' ) ) {
			$ajax_handler->add_response_data( 'protocol', $protocol );
			$ajax_handler->add_response_data( 'protocol_line', $line );
		}

		// Acrescenta à mensagem configurada sem substituí-la, quando a API interna permitir.
		if ( ! is_object( $ajax_handler ) ) {
			return;
		}

		try {
			$reflection = new ReflectionClass( $ajax_handler );

			if ( $reflection->hasProperty( 'messages' ) ) {
				$property = $reflection->getProperty( 'messages' );
				$property->setAccessible( true );
				$messages = $property->getValue( $ajax_handler );

				if ( is_array( $messages ) ) {
					$current = '';

					if ( isset( $messages['success'] ) && is_string( $messages['success'] ) ) {
						$current = $messages['success'];
					} elseif ( isset( $messages['success'] ) && is_array( $messages['success'] ) ) {
						$current = implode( ' ', $messages['success'] );
					}

					$messages['success'] = trim( $current . ( '' !== $current ? "\n" : '' ) . $line );
					$property->setValue( $ajax_handler, $messages );
				}
			}

			if ( $reflection->hasProperty( 'data' ) ) {
				$property = $reflection->getProperty( 'data' );
				$property->setAccessible( true );
				$data = $property->getValue( $ajax_handler );

				if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
					$data['message'] = trim( $data['message'] . "\n" . $line );
					$property->setValue( $ajax_handler, $data );
				}
			}
		} catch ( ReflectionException $e ) {
			// O JS de frontend permanece como fallback.
		}
	}
}
