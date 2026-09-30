<?php
/**
 * WPForms (and WPForms Lite).
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A form is a JSON document in post_content, and notifications are a numbered array
 * inside it — usually one entry keyed 1, but a site can have several and each has its
 * own recipient.
 *
 * ⚠️ EVERY notification is repointed, not just the first. A form with a second
 * notification going to a sales manager would otherwise keep half its mail on the old
 * path, and the settings screen would say the form was done — which is worse than
 * saying it failed.
 *
 * ⚠️ The recipient field accepts a comma-separated list and smart tags such as
 * {admin_email}. Replacing the whole value is deliberate: pointing a form at Humainbox
 * means Humainbox receives it and forwards it onward, so leaving a second address in
 * place would deliver the unfiltered copy as well and quietly defeat the point. The
 * original list is kept, in full, in the backup.
 */
class Humainbox_Wpforms_Adapter extends Humainbox_Adapter {

	/**
	 * WPForms' own tag for the site's admin address, which is its default.
	 *
	 * Static: it resolves to one address whoever submits. {field_id="3"} and the rest
	 * are worked out per submission and stay refused — see may_be_repointed().
	 */
	const STATIC_TAGS = array( '{admin_email}' );

	public function slug() {
		return 'wpforms';
	}

	public function label() {
		return 'WPForms';
	}

	public function is_available() {
		// The accessor this adapter uses, rather than a plugin path: Lite and Pro ship
		// under different folders and both provide wpforms().
		return null !== $this->handler();
	}

	/**
	 * WPForms' form handler, however this version of WPForms hands it out.
	 *
	 * ⚠️ THIS WAS `isset( wpforms()->form )` AND IT IS FALSE ON CURRENT WPFORMS.
	 *
	 * Modern WPForms keeps its objects in a registry reached through get( 'form' ).
	 * The old public property still appears to exist through a magic getter, but
	 * isset() on a magic property asks __isset(), which WPForms does not answer — so
	 * the check returned false on a site with WPForms Lite active and the screen told
	 * its owner "WPForms is not active on this site" while they were looking at it.
	 *
	 * Found by installing WPForms Lite on a real site and reading the inventory. It
	 * could not have been found any other way: every part of it is correct PHP.
	 *
	 * Both shapes are supported, newest first — an older install is somebody's working
	 * site and is not a reason to refuse to look at their forms.
	 *
	 * @return object|null
	 */
	private function handler() {
		if ( ! function_exists( 'wpforms' ) ) {
			return null;
		}

		$wpforms = wpforms();

		if ( is_object( $wpforms ) && method_exists( $wpforms, 'get' ) ) {
			$handler = $wpforms->get( 'form' );

			if ( is_object( $handler ) ) {
				return $handler;
			}
		}

		// Older releases, where it really was a property.
		return isset( $wpforms->form ) && is_object( $wpforms->form ) ? $wpforms->form : null;
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$forms = $this->handler()->get( '', array( 'numberposts' => 200 ) );

		if ( ! is_array( $forms ) ) {
			return array();
		}

		$out      = array();
		$may_save = $this->may_save();

		foreach ( $forms as $form ) {
			if ( ! isset( $form->ID ) ) {
				continue;
			}

			$data = wpforms_decode( $form->post_content );

			$repointable = array();
			$copies      = array();

			foreach ( ( isset( $data['settings']['notifications'] ) ? $data['settings']['notifications'] : array() ) as $notification ) {
				$email = isset( $notification['email'] ) ? (string) $notification['email'] : '';

				if ( $this->may_be_repointed( $email, self::STATIC_TAGS ) ) {
					$repointable[] = $email;
				}

				// WPForms Pro's CC setting, on any notification: the enquiry reaches
				// that address whatever the To says, and never through us.
				if ( ! empty( $notification['carboncopy'] ) ) {
					$copies[] = (string) $notification['carboncopy'];
				}
			}

			$changeable = ! empty( $repointable ) && $may_save;

			if ( empty( $repointable ) ) {
				$reason = __( 'Every notification on this form is addressed with a smart tag worked out for each submission.', 'humainbox' );
			} elseif ( ! $may_save ) {
				$reason = __( 'Your account on this site is not allowed to save HTML, and WPForms would strip it from this form while saving the change. Change the address in WPForms, or ask an administrator who can.', 'humainbox' );
			} else {
				$reason = '';
			}

			$out[] = array(
				'id'         => (string) $form->ID,
				'title'      => isset( $form->post_title ) ? (string) $form->post_title : '',
				// Only the notifications this plugin manages, when there are any: the
				// visitor's own copy ({field_id="1"}) is not ours, is never changed, and
				// shown beside ours it read as a second recipient. 'aside' says it exists.
				'recipient'  => ! empty( $repointable ) ? implode( ', ', array_unique( array_filter( $repointable, 'strlen' ) ) ) : $this->recipients_from( $data ),
				'aside'      => ! empty( $repointable ) && count( array_filter( explode( ', ', $this->recipients_from( $data ) ), 'strlen' ) ) > count( array_unique( array_filter( $repointable, 'strlen' ) ) ),
				'changeable' => $changeable,
				'reason'     => $reason,
				'routing'    => empty( $repointable ) ? 'none' : $this->routing( $repointable ),
				'notes'      => $changeable ? $this->notes( $repointable, $copies ) : array(),
			);
		}

		return $out;
	}

	/**
	 * Whether saving a form through WPForms would leave it as it was.
	 *
	 * ⚠️ FOR A USER WITHOUT unfiltered_html, WPForms' update() RUNS wp_strip_all_tags
	 * OVER THE WHOLE FORM. Every HTML field, every confirmation message with a link in
	 * it, flattened to text — to change one address. That user is not hypothetical:
	 * it is every site administrator on a multisite network, and anyone on a site
	 * with DISALLOW_UNFILTERED_HTML. The form builder would do the same to them, but
	 * the builder is where they would see it happen; this screen is not.
	 *
	 * @return bool
	 */
	private function may_save() {
		return current_user_can( 'unfiltered_html' );
	}

	/**
	 * Save form data, read by raw(), back through WPForms — byte for byte as it was
	 * apart from the addresses changed.
	 *
	 * ⚠️ THIS USED TO DELETE EVERY BACKSLASH IN THE FORM, TWICE OVER.
	 *
	 * The data was read with wpforms_decode(), which runs wp_unslash() on what it
	 * decodes, and handed to update(), which — in WPForms' default mode — runs
	 * wp_unslash() on what it is given, because the builder passes it values straight
	 * from a POST body. Two unslashes, and nothing to take them from but the real
	 * backslashes: a regex in an input mask, a Windows path in an HTML block, an
	 * escaped quote in a confirmation. Silently, on the save that changed an address,
	 * on a form nobody was looking at. Reproduced against WPForms Lite before fixing.
	 *
	 * So the stored JSON is read without unslashing (raw()), and slashed here exactly
	 * as far as update() is about to unslash it:
	 *
	 *  - default mode: update() unslashes everything, so everything is slashed;
	 *  - with form data slashing enabled (WPForms 1.9+, opt-in): update() unslashes
	 *    only the field keys named by the same filter it uses, so only those are.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $data    Form data from raw().
	 * @return bool
	 */
	private function save( $form_id, array $data ) {
		if ( ! function_exists( 'wpforms_is_form_data_slashing_enabled' ) || ! wpforms_is_form_data_slashing_enabled() ) {
			return (bool) $this->handler()->update( $form_id, wp_slash( $data ) );
		}

		// Same filter and defaults as WPForms_Form_Handler::unslash_field_keys().
		$keys = (array) apply_filters( 'wpforms_form_handler_unslash_field_keys', array( 'columns-json', 'calculation_code' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPForms' own filter, read so that our slashing matches its unslashing.

		if ( ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
			foreach ( $data['fields'] as $id => $field ) {
				foreach ( $keys as $key ) {
					if ( is_array( $field ) && isset( $field[ $key ] ) ) {
						$data['fields'][ $id ][ $key ] = wp_slash( $field[ $key ] );
					}
				}
			}
		}

		return (bool) $this->handler()->update( $form_id, $data );
	}

	public function set_recipient( $form_id, $recipient ) {
		if ( ! $this->is_available() || ! $this->may_save() ) {
			return false;
		}

		$form_id = absint( $form_id );
		$data    = $this->raw( $form_id );

		if ( null === $data || empty( $data['settings']['notifications'] ) ) {
			return false;
		}

		/*
		 | ⚠️ NOT EVERY NOTIFICATION AFTER ALL, AND THE README SAID OTHERWISE.
		 |
		 | It did overwrite all of them, with a reason that is right about half of them:
		 | leaving one addressed to the business behind would send an unfiltered copy as
		 | well. But a WPForms form commonly carries a SECOND notification addressed
		 | with a field smart tag — the "a copy of your enquiry" mail that goes back to
		 | the visitor who filled the form in. Overwriting that one does not send us a
		 | copy, it sends US THE CUSTOMER'S RECEIPT and sends the customer nothing.
		 |
		 | So: every notification with a fixed address, and every one using the site's
		 | own admin tag. Anything worked out per submission is left exactly as it is.
		 */
		$changed = false;

		foreach ( $data['settings']['notifications'] as $key => $notification ) {
			if ( ! $this->may_be_repointed( isset( $notification['email'] ) ? $notification['email'] : '', self::STATIC_TAGS ) ) {
				continue;
			}

			$data['settings']['notifications'][ $key ]['email'] = $recipient;
			$changed = true;
		}

		// Nothing to write is not a successful write. Reporting it as one is how a
		// screen comes to say a form now delivers to Humainbox when it does not.
		if ( ! $changed ) {
			return false;
		}

		/*
		 * Through the plugin's own update(), which re-encodes, bumps the revision and
		 * clears its caches. Writing post_content with wp_update_post would leave all
		 * three undone.
		 */
		return $this->save( $form_id, $data );
	}

	/**
	 * Every address this form currently sends to, joined for display.
	 *
	 * @param mixed $data Decoded form data.
	 * @return string
	 */
	private function recipients_from( $data ) {
		if ( ! is_array( $data ) || empty( $data['settings']['notifications'] ) ) {
			return '';
		}

		$found = array();

		foreach ( $data['settings']['notifications'] as $notification ) {
			if ( ! empty( $notification['email'] ) ) {
				$found[] = (string) $notification['email'];
			}
		}

		return implode( ', ', array_unique( $found ) );
	}

	public function snapshot( $form_id ) {
		$data = $this->raw( $form_id );

		if ( null === $data ) {
			return array();
		}

		$emails = array();

		foreach ( ( isset( $data['settings']['notifications'] ) ? $data['settings']['notifications'] : array() ) as $key => $notification ) {
			$emails[ (string) $key ] = isset( $notification['email'] ) ? (string) $notification['email'] : '';
		}

		return array( 'emails' => $emails );
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		if ( empty( $snapshot['emails'] ) || ! is_array( $snapshot['emails'] ) ) {
			return false;
		}

		if ( ! $this->may_save() ) {
			return false;
		}

		$form_id = absint( $form_id );
		$data    = $this->raw( $form_id );

		if ( null === $data || empty( $data['settings']['notifications'] ) ) {
			return false;
		}

		$restored = 0;

		foreach ( $snapshot['emails'] as $key => $email ) {
			// A notification deleted since the snapshot was taken is not recreated.
			// Putting back a notification somebody removed on purpose would be a
			// worse surprise than leaving it gone.
			if ( ! isset( $data['settings']['notifications'][ $key ] ) ) {
				continue;
			}

			$now = isset( $data['settings']['notifications'][ $key ]['email'] ) ? $data['settings']['notifications'][ $key ]['email'] : '';

			// Only what still goes to us. A visitor-copy we never touched, or an
			// address somebody has changed by hand since, is left as it is.
			if ( ! $this->still_ours( $now ) ) {
				continue;
			}

			$data['settings']['notifications'][ $key ]['email'] = (string) $email;
			++$restored;
		}

		if ( 0 === $restored ) {
			return 0;
		}

		return $this->save( $form_id, $data ) ? $restored : false;
	}

	/**
	 * A form's stored settings exactly as stored, or null.
	 *
	 * ⚠️ json_decode(), NOT wpforms_decode(). The latter runs wp_unslash() over what it
	 * decodes, which is right for reading and wrong for anything that will be written
	 * back: every backslash in the form would be gone before save() saw it. See save().
	 *
	 * @param string $form_id Form identifier.
	 * @return array|null
	 */
	private function raw( $form_id ) {
		if ( ! $this->is_available() ) {
			return null;
		}

		$form = $this->handler()->get( absint( $form_id ) );

		if ( empty( $form ) || ! isset( $form->post_content ) ) {
			return null;
		}

		$data = json_decode( (string) $form->post_content, true );

		return is_array( $data ) ? $data : null;
	}
}
