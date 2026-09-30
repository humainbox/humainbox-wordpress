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
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_humainbox_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_humainbox_forms', array( $this, 'handle_forms' ) );
		add_action( 'admin_post_humainbox_test', array( $this, 'handle_test' ) );
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
	 * The stylesheet and the two pieces of behaviour, on this screen and nowhere else.
	 *
	 * ⚠️ THE SCREEN CHECK IS THE POINT. A plugin that enqueues on every admin page is
	 * slowing down somebody else's dashboard for a feature that is not on it, and it
	 * is one of the commonest reasons a review comes back.
	 *
	 * @param string $hook The current admin page.
	 */
	public function assets( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'humainbox-settings',
			plugins_url( 'admin/css/settings.css', HUMAINBOX_FILE ),
			array(),
			HUMAINBOX_VERSION
		);

		wp_enqueue_script(
			'humainbox-settings',
			plugins_url( 'admin/js/settings.js', HUMAINBOX_FILE ),
			array(),
			HUMAINBOX_VERSION,
			true
		);

		// Through PHP so they are translatable. A confirm dialog written in English
		// inside a JavaScript file stays English in every language.
		wp_localize_script(
			'humainbox-settings',
			'humainboxL10n',
			array(
				'nothingSelected' => __( 'Tick the forms first.', 'humainbox' ),
				'restoreOne'      => __( 'Restore 1 form to its original address? Its enquiries will go there directly again, without spam protection.', 'humainbox' ),
				/* translators: %1$d: number of forms. */
				'restoreMany'     => __( 'Restore %1$d forms to their original addresses? Their enquiries will go there directly again, without spam protection.', 'humainbox' ),
				/* translators: 1: always 1, 2: the Humainbox address. */
				'confirmOne'      => __( 'Connect 1 form to %2$s? From now on its enquiries go through Humainbox to the recipients you set there. Its current address is saved first, so you can undo this.', 'humainbox' ),
				/* translators: 1: number of forms, 2: the Humainbox address. */
				'confirmMany'     => __( 'Connect %1$d forms to %2$s? From now on their enquiries go through Humainbox to the recipients you set there. Their current addresses are saved first, so you can undo this.', 'humainbox' ),
			)
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() above ran check_admin_referer( 'humainbox_save' ), which dies rather than returning.
		$raw     = isset( $_POST['humainbox_address'] ) ? sanitize_email( wp_unslash( $_POST['humainbox_address'] ) ) : '';
		$address = is_email( $raw ) ? $raw : '';

		if ( '' !== $raw && '' === $address ) {
			$this->redirect( 'invalid' );
		}

		/*
		 | Shape, then ownership. is_email() only says the string could be delivered
		 | to somewhere — it has nothing to say about whether that somewhere is us,
		 | and "somewhere that is not us" is the answer that silently costs a site
		 | its enquiries. See HUMAINBOX_HOST.
		 */
		if ( '' !== $address && ! humainbox_is_inbox_address( $address ) ) {
			$this->redirect( 'not-ours' );
		}

		update_option( HUMAINBOX_OPTION, array( 'address' => $address ), false );

		$this->redirect( 'saved' );
	}

	/**
	 * The forms table: connect or restore, whichever button was pressed.
	 *
	 * One handler for both because they share one form and so one nonce — see the
	 * note on the table in settings-page.php. Anything but an explicit "restore"
	 * is a connect, which is the button Enter presses.
	 */
	public function handle_forms() {
		$this->guard( 'humainbox_forms' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() above ran check_admin_referer( 'humainbox_forms' ), which dies rather than returning.
		$do = isset( $_POST['humainbox_do'] ) ? sanitize_key( wp_unslash( $_POST['humainbox_do'] ) ) : '';

		if ( 'restore' === $do ) {
			$this->restore_chosen();
		}

		$this->apply_chosen();
	}

	/**
	 * Point selected forms at the address.
	 */
	private function apply_chosen() {
		$settings = $this->settings();

		if ( '' === $settings['address'] ) {
			$this->redirect( 'no-address' );
		}

		$selected = $this->chosen_forms();

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
	 *
	 * With one table for both actions, a ticked form may be one this plugin never
	 * changed. That is not a failure to restore it — there is simply nothing to
	 * undo — so it is skipped rather than counted against the result.
	 */
	private function restore_chosen() {
		$selected = $this->chosen_forms();
		$backup   = Humainbox_Forms::backup();

		$done    = 0;
		$kept    = 0;
		$failed  = 0;
		$skipped = 0;

		foreach ( $selected as $token ) {
			list( $slug, $form_id ) = $this->split( $token );

			if ( '' === $slug ) {
				continue;
			}

			if ( ! isset( $backup[ Humainbox_Forms::key( $slug, $form_id ) ] ) ) {
				++$skipped;
				continue;
			}

			$outcome = Humainbox_Forms::restore( $slug, $form_id );

			if ( 'restored' === $outcome ) {
				++$done;
			} elseif ( 'kept' === $outcome ) {
				++$kept;
			} else {
				++$failed;
			}
		}

		if ( 0 === $done + $kept + $failed && $skipped > 0 ) {
			$this->redirect( 'nothing-to-undo' );
		}

		$this->redirect( 'restored', $done, $failed, $kept );
	}

	/**
	 * Send one test message to the saved address.
	 *
	 * ⚠️ THE ADDRESS CHECK ON SAVE CANNOT CATCH A TYPO, AND A TYPO LOSES EVERYTHING.
	 *
	 * Refusing addresses that are not ours stops the worst paste, but one wrong or
	 * missing character inside a real-looking inbox address passes every test this
	 * plugin can run — and every form pointed at it then delivers into an inbox that
	 * does not exist. So the screen offers the one check that proves the whole path:
	 * a message, sent the way the forms send theirs, that should turn up in the
	 * Humainbox panel within a minute.
	 *
	 * It proves something else a site owner rarely knows: whether this WordPress can
	 * send mail at all. Very many cannot, and a form on such a site has been failing
	 * for as long as it has existed, whatever address it uses.
	 *
	 * Through wp_mail(), the same function the form plugins use — not a network call,
	 * and nothing is sent anywhere but to the address on this screen.
	 */
	public function handle_test() {
		$this->guard( 'humainbox_test' );

		$settings = $this->settings();

		if ( '' === $settings['address'] ) {
			$this->redirect( 'no-address' );
		}

		$site = wp_parse_url( home_url(), PHP_URL_HOST );

		$sent = wp_mail(
			$settings['address'],
			sprintf(
				/* translators: %s: this site's domain. */
				__( 'Humainbox test from %s', 'humainbox' ),
				$site
			),
			sprintf(
				/* translators: 1: this site's address, 2: the name of the person who pressed the button. */
				__( "This is a test sent from the Humainbox plugin on %1\$s by %2\$s.\n\nIf it shows up in your Humainbox panel, this site can send mail and the address is right. Nothing needs to be done with it.", 'humainbox' ),
				home_url(),
				wp_get_current_user()->display_name
			)
		);

		$this->redirect( $sent ? 'test-sent' : 'test-failed' );
	}

	/**
	 * The forms ticked on the screen, sanitized where they are read.
	 *
	 * ⚠️ SANITIZED HERE RATHER THAN DOWNSTREAM IN split(), and the difference is not
	 * cosmetic. The plugin's own header promises every input is sanitized on the way
	 * in; this read handed a raw array onward and relied on the next function
	 * remembering. Plugin Check said so in the terms a reviewer reads it in —
	 * "non-sanitized input variable" — and a reviewer has no way to follow the call
	 * two files later to find out that it is fine.
	 *
	 * Both callers ran guard() first, so the nonce is verified; the sniff cannot see
	 * across a method call, which is what the ignore says and why.
	 *
	 * @return string[]
	 */
	private function chosen_forms() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() ran check_admin_referer() before this was called, and it dies rather than returning.
		if ( ! isset( $_POST['humainbox_forms'] ) || ! is_array( $_POST['humainbox_forms'] ) ) {
			return array();
		}

		/*
		 | Sanitized inside the same expression that reads it. Doing it on the next
		 | line is the same code and the sniff still reports a non-sanitized input,
		 | because it reads the access rather than the eventual value — and so does a
		 | reviewer skimming for exactly this pattern.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() ran check_admin_referer() before this was called, and it dies rather than returning.
		return array_map( 'sanitize_text_field', wp_unslash( $_POST['humainbox_forms'] ) );
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
	 * @param int    $kept   How many were left as they are.
	 */
	private function redirect( $status, $done = 0, $failed = 0, $kept = 0 ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::SLUG,
					'humainbox_status' => rawurlencode( $status ),
					'humainbox_done'   => absint( $done ),
					'humainbox_failed' => absint( $failed ),
					'humainbox_kept'   => absint( $kept ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$kept = isset( $_GET['humainbox_kept'] ) ? absint( wp_unslash( $_GET['humainbox_kept'] ) ) : 0;
		$address = $this->settings()['address'];

		switch ( $status ) {
			case 'saved':
				if ( '' === $address ) {
					return array( 'type' => 'success', 'text' => __( 'Address cleared. No form was changed.', 'humainbox' ) );
				}

				return array( 'type' => 'success', 'text' => __( 'Address saved. No form has been changed yet — send a test message first to make sure the address is right.', 'humainbox' ) );

			case 'test-sent':
				return array(
					'type' => 'success',
					'text' => sprintf(
						/* translators: %s: the Humainbox address. */
						__( 'Test sent to %s. It should appear in your Humainbox panel within a minute. If it does not, check the address before pointing any form at it.', 'humainbox' ),
						$address
					),
				);

			case 'test-failed':
				return array(
					'type' => 'error',
					'text' => __( 'WordPress could not send the test. This site may not be able to send email at all — and if so, its forms are not delivering either, whatever address they use. An SMTP plugin is the usual fix. Nothing was changed.', 'humainbox' ),
				);

			case 'invalid':
				return array( 'type' => 'error', 'text' => __( 'That does not look like an email address, so nothing was saved.', 'humainbox' ) );

			case 'not-ours':
				return array(
					'type' => 'error',
					'text' => sprintf(
						/* translators: %s: the end of a Humainbox address, e.g. @in.humainbox.com */
						__( 'That is an email address, but not a Humainbox inbox address — those end in %s. Nothing was saved and your forms are untouched.', 'humainbox' ),
						'@' . HUMAINBOX_INBOX_HOST
					),
				);

			case 'no-address':
				return array( 'type' => 'error', 'text' => __( 'Save your Humainbox address first.', 'humainbox' ) );

			/*
			 | ⚠️ THE FAILURE CLAUSE IS NOT PRINTED WHEN THERE IS NO FAILURE.
			 |
			 | Both of these read "3 forms now deliver to Humainbox. 0 could not be
			 | changed." on a clean success — a sentence that ends by reporting a
			 | problem that did not happen, on the screen where somebody has just
			 | rewritten their live forms and is looking for reassurance.
			 |
			 | And nothing selected is its own outcome, not a success of size zero.
			 */
			case 'applied':
				if ( 0 === $done && 0 === $failed ) {
					return array( 'type' => 'info', 'text' => __( 'No forms were selected, so nothing changed.', 'humainbox' ) );
				}

				$text = $this->outcome(
					/* translators: %d: number of forms now delivering to Humainbox. */
					_n( '%d form is now connected to Humainbox.', '%d forms are now connected to Humainbox.', $done, 'humainbox' ),
					/* translators: %d: number of forms that could not be changed. */
					_n( '%d could not be changed.', '%d could not be changed.', $failed, 'humainbox' ),
					$done,
					$failed
				);

				// The one check left that only a person can do, while it is fresh.
				if ( $done > 0 ) {
					$text .= ' ' . __( 'Send one enquiry through a form yourself and check that it arrives in your Humainbox panel.', 'humainbox' );
				}

				return array(
					'type' => $failed > 0 ? 'warning' : 'success',
					'text' => $text,
				);

			case 'nothing-to-undo':
				return array( 'type' => 'info', 'text' => __( 'None of the selected forms were changed by this plugin, so there was nothing to restore.', 'humainbox' ) );

			case 'restored':
				if ( 0 === $done && 0 === $failed && 0 === $kept ) {
					return array( 'type' => 'info', 'text' => __( 'No forms were selected, so nothing changed.', 'humainbox' ) );
				}

				$text = $this->outcome(
					/* translators: %d: number of forms put back. */
					_n( '%d form sends to its original address again, without spam protection.', '%d forms send to their original addresses again, without spam protection.', $done, 'humainbox' ),
					/* translators: %d: number of forms that could not be put back. */
					_n( '%d could not be restored.', '%d could not be restored.', $failed, 'humainbox' ),
					$done,
					$failed
				);

				if ( $kept > 0 ) {
					$text = trim(
						$text . ' ' . sprintf(
							/* translators: %d: number of forms left as they are. */
							_n( '%d no longer went to Humainbox — it had been changed since — so it was left as it is.', '%d no longer went to Humainbox — they had been changed since — so they were left as they are.', $kept, 'humainbox' ),
							$kept
						)
					);
				}

				return array(
					'type' => $failed > 0 ? 'warning' : 'success',
					'text' => $text,
				);
		}

		return null;
	}

	/**
	 * One sentence, plus a second one only when there is something to say in it.
	 *
	 * @param string $succeeded Sentence about what worked, with one %d.
	 * @param string $failed    Sentence about what did not, with one %d.
	 * @param int    $done      How many worked.
	 * @param int    $count     How many did not.
	 * @return string
	 */
	private function outcome( $succeeded, $failed, $done, $count ) {
		$text = $done > 0 ? sprintf( $succeeded, $done ) : '';

		if ( $count > 0 ) {
			$text = trim( $text . ' ' . sprintf( $failed, $count ) );
		}

		return $text;
	}
}
