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
 *
 * ⚠️ AND toType 'email' IS NOT ENOUGH ON ITS OWN. Its "Send to Email" box accepts
 * merge tags, so the same visitor-copy can be written as toType 'email' with a `to`
 * of {Email:3}. This adapter used to overwrite that one, which the other two never
 * did: the same rule now applies here — a fixed address, or {admin_email}, and
 * nothing worked out per submission. See Humainbox_Adapter::may_be_repointed().
 */
class Humainbox_Gravity_Adapter extends Humainbox_Adapter {

	/**
	 * Gravity's own tag for the site's admin address — the default "Admin
	 * Notification" is addressed to it. Static: one address whoever submits.
	 */
	const STATIC_TAGS = array( '{admin_email}' );

	public function slug() {
		return 'gravity';
	}

	public function label() {
		return 'Gravity Forms';
	}

	public function is_available() {
		return class_exists( 'GFAPI' );
	}

	/**
	 * Whether this plugin may change one notification.
	 *
	 * @param array $notification A Gravity notification.
	 * @return bool
	 */
	private function repointable( $notification ) {
		// Absent toType means the historical default, which is a plain address.
		$type = isset( $notification['toType'] ) ? $notification['toType'] : 'email';

		return 'email' === $type
			&& $this->may_be_repointed( isset( $notification['to'] ) ? $notification['to'] : '', self::STATIC_TAGS );
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

			/*
			 | Whether set_recipient() would find anything to write. A form with nothing
			 | but field-routed, rule-routed or merge-tag notifications is a form this
			 | plugin cannot change, and offering it a checkbox only to report a failure
			 | afterwards is the screen lying twice.
			 */
			$repointable = array();
			$copies      = array();

			foreach ( ( isset( $form['notifications'] ) ? $form['notifications'] : array() ) as $notification ) {
				if ( $this->repointable( $notification ) ) {
					$repointable[] = isset( $notification['to'] ) ? (string) $notification['to'] : '';
				}

				foreach ( array( 'cc', 'bcc' ) as $field ) {
					if ( ! empty( $notification[ $field ] ) ) {
						$copies[] = (string) $notification[ $field ];
					}
				}
			}

			$changeable = ! empty( $repointable );

			$out[] = array(
				'id'         => (string) $form['id'],
				'title'      => isset( $form['title'] ) ? (string) $form['title'] : '',
				'recipient'  => $changeable ? implode( ', ', array_unique( array_filter( $repointable, 'strlen' ) ) ) : $this->recipients_from( $form ),
				// Field-routed, rule-routed or merge-tag notifications exist beside ours.
				'aside'      => $changeable && count( $repointable ) < count( isset( $form['notifications'] ) ? $form['notifications'] : array() ),
				'changeable' => $changeable,
				'reason'     => $changeable ? '' : __( 'Every notification on this form is routed to a field, by a rule or with a merge tag worked out for each submission, so there is no fixed address to change.', 'humainbox' ),
				'routing'    => $changeable ? $this->routing( $repointable ) : 'none',
				'notes'      => $changeable ? $this->notes( $repointable, $copies ) : array(),
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
			if ( ! $this->repointable( $notification ) ) {
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

	public function snapshot( $form_id ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$form = GFAPI::get_form( absint( $form_id ) );

		if ( empty( $form ) ) {
			return array();
		}

		$to = array();

		foreach ( ( isset( $form['notifications'] ) ? $form['notifications'] : array() ) as $key => $notification ) {
			// Only the ones this plugin is allowed to change. A snapshot of a
			// field-routed notification would be a promise to restore something we
			// never touched.
			if ( $this->repointable( $notification ) ) {
				$to[ (string) $key ] = isset( $notification['to'] ) ? (string) $notification['to'] : '';
			}
		}

		return array( 'to' => $to );
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		if ( ! $this->is_available() || empty( $snapshot['to'] ) || ! is_array( $snapshot['to'] ) ) {
			return false;
		}

		$form = GFAPI::get_form( absint( $form_id ) );

		if ( empty( $form ) || empty( $form['notifications'] ) ) {
			return false;
		}

		$restored = 0;

		foreach ( $snapshot['to'] as $key => $address ) {
			if ( ! isset( $form['notifications'][ $key ] ) ) {
				continue;
			}

			// Changed by hand since we pointed it at us: theirs now, not ours to undo.
			if ( ! $this->still_ours( isset( $form['notifications'][ $key ]['to'] ) ? $form['notifications'][ $key ]['to'] : '' ) ) {
				continue;
			}

			$form['notifications'][ $key ]['to'] = (string) $address;
			++$restored;
		}

		if ( 0 === $restored ) {
			return 0;
		}

		return is_wp_error( GFAPI::update_form( $form ) ) ? false : $restored;
	}
}
