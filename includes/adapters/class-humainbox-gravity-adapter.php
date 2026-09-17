<?php
/**
 * Gravity Forms.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications are first-class objects here, each with its own event, routing and
 * recipient — and `to` means different things depending on `toType`.
 *
 * ⚠️ ONLY toType 'email' NOTIFICATIONS ARE TOUCHED. A notification routed to a field
 * ('field') sends to whatever address the visitor typed into a form field, and one
 * using 'routing' picks an address from conditional rules. Overwriting either would
 * not redirect a notification, it would BREAK one — a "send a copy to yourself"
 * confirmation would start mailing the site's Humainbox address instead of the
 * visitor. Those are listed on the settings screen and left exactly as they are.
 */
class Humainbox_Gravity_Adapter extends Humainbox_Adapter {

	public function slug() {
		return 'gravity';
	}

	public function label() {
		return 'Gravity Forms';
	}

	public function is_available() {
		return class_exists( 'GFAPI' );
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();

		foreach ( GFAPI::get_forms() as $form ) {
			if ( empty( $form['id'] ) ) {
				continue;
			}

			$out[] = array(
				'id'        => (string) $form['id'],
				'title'     => isset( $form['title'] ) ? (string) $form['title'] : '',
				'recipient' => $this->recipients_from( $form ),
			);
		}

		return $out;
	}

	public function set_recipient( $form_id, $recipient ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$form = GFAPI::get_form( absint( $form_id ) );

		if ( empty( $form ) || empty( $form['notifications'] ) ) {
			return false;
		}

		$changed = false;

		foreach ( $form['notifications'] as $key => $notification ) {
			// Absent toType means the historical default, which is a plain address.
			$type = isset( $notification['toType'] ) ? $notification['toType'] : 'email';

			if ( 'email' !== $type ) {
				continue;
			}

			$form['notifications'][ $key ]['to'] = $recipient;
			$changed                             = true;
		}

		if ( ! $changed ) {
			return false;
		}

		$result = GFAPI::update_form( $form );

		// update_form() answers with WP_Error rather than throwing.
		return ! is_wp_error( $result );
	}

	/**
	 * What this form's plain-address notifications currently go to.
	 *
	 * @param array $form Gravity form array.
	 * @return string
	 */
	private function recipients_from( $form ) {
		if ( empty( $form['notifications'] ) ) {
			return '';
		}

		$found = array();

		foreach ( $form['notifications'] as $notification ) {
			$type = isset( $notification['toType'] ) ? $notification['toType'] : 'email';

			if ( 'email' === $type && ! empty( $notification['to'] ) ) {
				$found[] = (string) $notification['to'];
			}
		}

		return implode( ', ', array_unique( $found ) );
	}
}
