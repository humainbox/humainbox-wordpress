<?php
/**
 * The one screen this plugin has.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings, under Settings, where a configuration screen belongs.
 *
 * ── Not a top-level menu
 *
 * A plugin that does one thing once does not deserve a permanent entry in the
 * left-hand rail of somebody's dashboard. Guideline 11 is about not hijacking the
 * admin and this is the quiet reading of it: a top-level menu for a configurator is a
 * small daily tax on every other plugin's visibility.
 *
 * ── Every handler does the same two checks, in the same order
 *
 * current_user_can() FIRST, then the nonce. That order matters: the nonce answers
 * "did this request come from our form", the capability answers "is this person
 * allowed to do it at all", and a valid nonce from a subscriber must not get further
 * into the code than an invalid one from an administrator.
 */
class Humainbox_Settings {

	const CAPABILITY = 'manage_options';
	const SLUG       = 'humainbox';

	/**
	 * Singleton.
	 *
	 * @var Humainbox_Settings|null
	 */
	private static $instance = null;

	/**
	 * @return Humainbox_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register everything this screen needs.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_humainbox_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_humainbox_apply', array( $this, 'handle_apply' ) );
		add_action( 'admin_post_humainbox_restore', array( $this, 'handle_restore' ) );
	}

	/**
	 * Add the page.
	 */
	public function menu() {
		add_options_page(
			__( 'Humainbox', 'humainbox' ),
			__( 'Humainbox', 'humainbox' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * The stored settings.
	 *
	 * @return array{address:string}
	 */
	public function settings() {
		$settings = get_option( HUMAINBOX_OPTION, array() );

		return array(
			'address' => isset( $settings['address'] ) ? (string) $settings['address'] : '',
		);
	}

	/**
	 * Save the address.
	 */
	public function handle_save() {
		$this->guard( 'humainbox_save' );

		/*
		 | sanitize_email is not validation — it strips characters it does not like and
		 | hands back whatever is left, so "not an address" becomes "notanaddress" and
		 | passes. is_email() afterwards is what actually decides.
		 |
		 | wp_unslash first: WordPress slashes every superglobal on the way in, so an
		 | address is otherwise sanitized with its escaping still attached.
		 */
		$raw     = isset( $_POST['humainbox_address'] ) ? sanitize_email( wp_unslash( $_POST['humainbox_address'] ) ) : '';
		$address = is_email( $raw ) ? $raw : '';

		if ( '' !== $raw && '' === $address ) {
			$this->redirect( 'invalid' );
		}

		update_option( HUMAINBOX_OPTION, array( 'address' => $address ), false );

		$this->redirect( 'saved' );
	}

	/**
	 * Point selected forms at the address.
	 */
	public function handle_apply() {
		$this->guard( 'humainbox_apply' );

		$settings = $this->settings();

		if ( '' === $settings['address'] ) {
			$this->redirect( 'no-address' );
		}

		$selected = isset( $_POST['humainbox_forms'] ) ? wp_unslash( $_POST['humainbox_forms'] ) : array();
		$selected = is_array( $selected ) ? $selected : array();

		$done   = 0;
		$failed = 0;

		foreach ( $selected as $token ) {
			list( $slug, $form_id ) = $this->split( $token );

			if ( '' === $slug ) {
				continue;
			}

			if ( Humainbox_Forms::apply( $slug, $form_id, $settings['address'] ) ) {
				++$done;
			} else {
				++$failed;
			}
		}

		$this->redirect( 'applied', $done, $failed );
	}

	/**
	 * Put selected forms back.
	 */
	public function handle_restore() {
		$this->guard( 'humainbox_restore' );

		$selected = isset( $_POST['humainbox_forms'] ) ? wp_unslash( $_POST['humainbox_forms'] ) : array();
		$selected = is_array( $selected ) ? $selected : array();

		$done   = 0;
		$failed = 0;

		foreach ( $selected as $token ) {
			list( $slug, $form_id ) = $this->split( $token );

			if ( '' === $slug ) {
				continue;
			}

			if ( Humainbox_Forms::restore( $slug, $form_id ) ) {
				++$done;
			} else {
				++$failed;
			}
		}

		$this->redirect( 'restored', $done, $failed );
	}

	/**
	 * Capability, then nonce. Neither is optional and the order is not arbitrary.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have permission to change where this site\'s forms deliver.', 'humainbox' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( $action );
	}

	/**
	 * Split a "slug:id" token from the form, safely.
	 *
	 * ⚠️ The slug is matched against a known list rather than trusted. It arrives from
	 * a POST body and is handed to Humainbox_Forms::adapter(); accepting an arbitrary
	 * string there would be an untrusted value steering which code runs.
	 *
	 * @param mixed $token Raw token.
	 * @return array{0:string,1:string}
	 */
	private function split( $token ) {
		$token = sanitize_text_field( (string) $token );

		if ( ! str_contains( $token, ':' ) ) {
			return array( '', '' );
		}

		list( $slug, $form_id ) = explode( ':', $token, 2 );

		$known = array();

		foreach ( Humainbox_Forms::adapters() as $adapter ) {
			$known[] = $adapter->slug();
		}

		if ( ! in_array( $slug, $known, true ) ) {
			return array( '', '' );
		}

		return array( $slug, sanitize_text_field( $form_id ) );
	}

	/**
	 * Back to the screen with something to say.
	 *
	 * Post/Redirect/Get: without it, a refresh after applying would re-apply, and a
	 * refresh after restoring would try to restore a backup that no longer exists.
	 *
	 * @param string $status Status key.
	 * @param int    $done   How many succeeded.
	 * @param int    $failed How many did not.
	 */
	private function redirect( $status, $done = 0, $failed = 0 ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::SLUG,
					'humainbox_status' => rawurlencode( $status ),
					'humainbox_done'   => absint( $done ),
					'humainbox_failed' => absint( $failed ),
				),
				admin_url( 'options-general.php' )
			)
		);

		exit;
	}

	/**
	 * Draw the page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings  = $this->settings();
		$inventory = Humainbox_Forms::inventory();
		$backup    = Humainbox_Forms::backup();
		$notice    = $this->notice();

		require HUMAINBOX_PATH . 'admin/settings-page.php';
	}

	/**
	 * What to tell the administrator after their last action.
	 *
	 * Read from the query string, which is attacker-influenceable — so the STATUS
	 * chooses a sentence written here, and no part of the query string is ever printed.
	 *
	 * @return array{type:string,text:string}|null
	 */
	private function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a redirect status to choose a fixed sentence; nothing is acted on.
		$status = isset( $_GET['humainbox_status'] ) ? sanitize_key( wp_unslash( $_GET['humainbox_status'] ) ) : '';

		if ( '' === $status ) {
			return null;
		}

		/*
		 | absint() would make the slashes irrelevant on its own — it casts to an
		 | integer and nothing survives that. wp_unslash() is here anyway because the
		 | review tooling reads the pattern rather than the consequence, and an
		 | argument about why this one is safe costs more than the call does.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$done = isset( $_GET['humainbox_done'] ) ? absint( wp_unslash( $_GET['humainbox_done'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$failed = isset( $_GET['humainbox_failed'] ) ? absint( wp_unslash( $_GET['humainbox_failed'] ) ) : 0;

		switch ( $status ) {
			case 'saved':
				return array( 'type' => 'success', 'text' => __( 'Address saved.', 'humainbox' ) );

			case 'invalid':
				return array( 'type' => 'error', 'text' => __( 'That does not look like an email address, so nothing was saved.', 'humainbox' ) );

			case 'no-address':
				return array( 'type' => 'error', 'text' => __( 'Save a Humainbox address first — there is nowhere to point the forms at yet.', 'humainbox' ) );

			case 'applied':
				return array(
					'type' => $failed > 0 ? 'warning' : 'success',
					'text' => sprintf(
						/* translators: 1: number of forms changed, 2: number that could not be changed. */
						_n(
							'%1$d form now delivers to Humainbox. %2$d could not be changed.',
							'%1$d forms now deliver to Humainbox. %2$d could not be changed.',
							$done,
							'humainbox'
						),
						$done,
						$failed
					),
				);

			case 'restored':
				return array(
					'type' => $failed > 0 ? 'warning' : 'success',
					'text' => sprintf(
						/* translators: 1: number of forms restored, 2: number that could not be restored. */
						_n(
							'%1$d form is back on its original address. %2$d could not be restored.',
							'%1$d forms are back on their original addresses. %2$d could not be restored.',
							$done,
							'humainbox'
						),
						$done,
						$failed
					),
				);
		}

		return null;
	}
}
