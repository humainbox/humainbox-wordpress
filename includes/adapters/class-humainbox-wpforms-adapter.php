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

	public function slug() {
		return 'wpforms';
	}

	public function label() {
		return 'WPForms';
	}

	public function is_available() {
		// The accessor this adapter uses, rather than a plugin path: Lite and Pro ship
		// under different folders and both provide wpforms().
		return function_exists( 'wpforms' ) && isset( wpforms()->form );
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$forms = wpforms()->form->get( '', array( 'numberposts' => 200 ) );

		if ( ! is_array( $forms ) ) {
			return array();
		}

		$out = array();

		foreach ( $forms as $form ) {
			if ( ! isset( $form->ID ) ) {
				continue;
			}

			$data = wpforms_decode( $form->post_content );

			$out[] = array(
				'id'        => (string) $form->ID,
				'title'     => isset( $form->post_title ) ? (string) $form->post_title : '',
				'recipient' => $this->recipients_from( $data ),
			);
		}

		return $out;
	}

	public function set_recipient( $form_id, $recipient ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$form_id = absint( $form_id );
		$form    = wpforms()->form->get( $form_id );

		if ( empty( $form ) || ! isset( $form->post_content ) ) {
			return false;
		}

		$data = wpforms_decode( $form->post_content );

		if ( ! is_array( $data ) || empty( $data['settings']['notifications'] ) ) {
			return false;
		}

		foreach ( $data['settings']['notifications'] as $key => $notification ) {
			$data['settings']['notifications'][ $key ]['email'] = $recipient;
		}

		/*
		 * Through the plugin's own update(), which re-encodes, bumps the revision and
		 * clears its caches. Writing post_content with wp_update_post would leave all
		 * three undone.
		 */
		return (bool) wpforms()->form->update( $form_id, $data );
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
}
