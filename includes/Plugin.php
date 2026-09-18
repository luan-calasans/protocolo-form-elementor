<?php

declare(strict_types=1);

namespace ProtocoloElementor;

use ProtocoloElementor\Admin\SettingsPage;
use ProtocoloElementor\Admin\ValidatorPage;
use ProtocoloElementor\Elementor\SubmissionListener;
use ProtocoloElementor\Elementor\SubmissionRepository;
use ProtocoloElementor\Protocol\Fingerprint;
use ProtocoloElementor\Protocol\Generator;
use ProtocoloElementor\Protocol\Mailer;
use ProtocoloElementor\Protocol\Validator;

final class Plugin {

	public const OPTION_FORM_NAMES = 'protocolo_elementor_form_names';
	public const META_PROTOCOL     = 'protocolo_elementor_protocol';
	public const META_DATETIME     = 'protocolo_elementor_datetime';
	public const META_TIMESTAMP    = 'protocolo_elementor_timestamp';
	public const META_MICROSECONDS = 'protocolo_elementor_microseconds';
	public const META_ATTEMPT      = 'protocolo_elementor_attempt';
	public const META_FORM_ID      = 'protocolo_elementor_form_id';
	public const META_FINGERPRINT  = 'protocolo_elementor_fingerprint';

	/** Chaves de versões anteriores, usadas só para leitura e migração. */
	public const LEGACY_OPTION_FORM_NAMES = 'adesaf_protocolos_form_names';
	public const LEGACY_META_PROTOCOL     = 'adesaf_protocol';

	/** @var array<string, string> */
	public const LEGACY_META_MAP = array(
		'adesaf_protocol'     => self::META_PROTOCOL,
		'adesaf_datetime'     => self::META_DATETIME,
		'adesaf_timestamp'    => self::META_TIMESTAMP,
		'adesaf_microseconds' => self::META_MICROSECONDS,
		'adesaf_attempt'      => self::META_ATTEMPT,
		'adesaf_form_id'      => self::META_FORM_ID,
		'adesaf_fingerprint'  => self::META_FINGERPRINT,
	);

	/** @var self|null */
	private static $instance = null;

	/** @var Generator */
	private $generator;

	/** @var Validator */
	private $validator;

	/** @var SubmissionRepository */
	private $repository;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$fingerprint      = new Fingerprint();
		$this->repository = new SubmissionRepository();
		$this->generator  = new Generator( $fingerprint, $this->repository );
		$this->validator  = new Validator( $fingerprint, $this->repository );
	}

	public function boot(): void {
		$this->migrate_legacy_option();

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			( new ValidatorPage( $this->validator ) )->register();
		}

		add_action( 'elementor_pro/init', array( $this, 'boot_elementor_integration' ) );

		if ( did_action( 'elementor_pro/init' ) || class_exists( '\ElementorPro\Plugin' ) ) {
			$this->boot_elementor_integration();
		} else {
			add_action( 'admin_notices', array( $this, 'maybe_render_dependency_notice' ) );
		}
	}

	public function boot_elementor_integration(): void {
		static $booted = false;

		if ( $booted ) {
			return;
		}

		$booted = true;

		( new SubmissionListener( $this->generator, $this->repository, new Mailer() ) )->register();
	}

	public function maybe_render_dependency_notice(): void {
		if ( class_exists( '\ElementorPro\Plugin' ) || did_action( 'elementor_pro/init' ) ) {
			return;
		}

		$this->render_dependency_notice();
	}

	/**
	 * @return string[]
	 */
	public static function enabled_form_names(): array {
		$names = get_option( self::OPTION_FORM_NAMES, array() );

		if ( ! is_array( $names ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $names as $name ) {
			$name = trim( (string) $name );

			if ( '' !== $name ) {
				$normalized[] = $name;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	public static function is_form_enabled( string $form_name ): bool {
		$form_name = trim( $form_name );

		if ( '' === $form_name ) {
			return false;
		}

		foreach ( self::enabled_form_names() as $enabled ) {
			if ( 0 === strcasecmp( $enabled, $form_name ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_plugin_meta_key( string $key ): bool {
		return 0 === strpos( $key, 'protocolo_elementor_' )
			|| isset( self::LEGACY_META_MAP[ $key ] );
	}

	public static function canonical_meta_key( string $key ): string {
		return self::LEGACY_META_MAP[ $key ] ?? $key;
	}

	public function render_dependency_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__(
			'Protocolo Elementor requer o Elementor Pro ativo, com Submissions habilitado nos formulários configurados.',
			'protocolo-elementor'
		);
		echo '</p></div>';
	}

	private function migrate_legacy_option(): void {
		$current = get_option( self::OPTION_FORM_NAMES, false );

		if ( false !== $current ) {
			return;
		}

		$legacy = get_option( self::LEGACY_OPTION_FORM_NAMES, false );

		if ( false === $legacy ) {
			return;
		}

		update_option( self::OPTION_FORM_NAMES, $legacy );
	}
}
