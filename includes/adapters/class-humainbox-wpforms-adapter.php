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

		$out = array();

		foreach ( $forms as $form ) {
			if ( ! isset( $form->ID ) ) {
				continue;
			}

			$data = wpforms_decode( $form->post_content );

			$repointable = 0;

			foreach ( ( isset( $data['settings']['notifications'] ) ? $data['settings']['notifications'] : array() ) as $notification ) {
				if ( $this->may_be_repointed( isset( $notification['email'] ) ? $notification['email'] : '', self::STATIC_TAGS ) ) {
					++$repointable;
				}
			}

			$out[] = array(
				'id'         => (string) $form->ID,
				'title'      => isset( $form->post_title ) ? (string) $form->post_title : '',
				'recipient'  => $this->recipients_from( $data ),
				'changeable' => $repointable > 0,
				'reason'     => $repointable > 0 ? '' : __( 'Every notification on this form is addressed with a smart tag worked out for each submission.', 'humainbox' ),
			);
		}

		return $out;
	}

	public function set_recipient( $form_id, $recipient ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$form_id = absint( $form_id );
		$form    = $this->handler()->get( $form_id );

		if ( empty( $form ) || ! isset( $form->post_content ) ) {
			return false;
		}

		$data = wpforms_decode( $form->post_content );

		if ( ! is_array( $data ) || empty( $data['settings']['notifications'] ) ) {
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
		return (bool) $this->handler()->update( $form_id, $data );
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
		$data = $this->data( $form_id );

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

		$form_id = absint( $form_id );
		$data    = $this->data( $form_id );

		if ( null === $data || empty( $data['settings']['notifications'] ) ) {
			return false;
		}

		foreach ( $snapshot['emails'] as $key => $email ) {
			// A notification deleted since the snapshot was taken is not recreated.
			// Putting back a notification somebody removed on purpose would be a
			// worse surprise than leaving it gone.
			if ( isset( $data['settings']['notifications'][ $key ] ) ) {
				$data['settings']['notifications'][ $key ]['email'] = (string) $email;
			}
		}

		return (bool) $this->handler()->update( $form_id, $data );
	}

	/**
	 * A form's decoded settings, or null.
	 *
	 * @param string $form_id Form identifier.
	 * @return array|null
	 */
	private function data( $form_id ) {
		if ( ! $this->is_available() ) {
			return null;
		}

		$form = $this->handler()->get( absint( $form_id ) );

		if ( empty( $form ) || ! isset( $form->post_content ) ) {
			return null;
		}

		$data = wpforms_decode( $form->post_content );

		return is_array( $data ) ? $data : null;
	}
}
