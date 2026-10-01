<?php
/**
 * Formidable Forms.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications are Email actions: posts of type frm_form_actions, one per action,
 * with their settings as JSON in post_content. A form can have several, enabled
 * (published) or disabled (draft).
 *
 * ⚠️ SAVED WHOLE, THROUGH THE ACTION'S OWN save_settings().
 *
 * It encodes and slashes post_content itself and clears Formidable's action cache,
 * so the settings are handed over as a plain array — slashing them here would
 * double it. And it saves through wp_insert_post, which does not merge: an action
 * passed without its title, status and menu_order comes back untitled, disabled and
 * detached from its form. So the whole action is read, one key changed, and the
 * whole action written back.
 *
 * ⚠️ ONLY email_to IS CHANGED. Cc and Bcc are separate choices and are reported, not
 * rewritten — the same rule as the other adapters.
 */
class Humainbox_Formidable_Adapter extends Humainbox_Adapter {

	/**
	 * Formidable's tags for one fixed address: the site admin, and the "Default
	 * Email" setting, which falls back to the admin address. Field shortcodes —
	 * [25], [email], [email show=...] — are worked out per entry and stay refused.
	 */
	const STATIC_TAGS = array( '[admin_email]', '[default-email]' );

	public function slug() {
		return 'formidable';
	}

	public function label() {
		return 'Formidable Forms';
	}

	public function is_available() {
		return class_exists( 'FrmForm' ) && class_exists( 'FrmFormAction' ) && class_exists( 'FrmFormActionsController' );
	}

	/**
	 * A form's Email actions, keyed by action ID — enabled and disabled alike, since
	 * a disabled action pointing at the old address would undo the change the day
	 * somebody switches it back on.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, object>
	 */
	private function actions( $form_id ) {
		$actions = FrmFormAction::get_action_for_form( absint( $form_id ), 'email' );

		return is_array( $actions ) ? $actions : array();
	}

	/**
	 * One action's settings, as an array whatever shape it arrived in.
	 *
	 * @param object $action An Email action.
	 * @return array
	 */
	private function settings_of( $action ) {
		return isset( $action->post_content ) && is_array( $action->post_content ) ? $action->post_content : array();
	}

	/**
	 * Write one action back, whole.
	 *
	 * @param object $action An Email action whose post_content has been changed.
	 * @return bool
	 */
	private function save( $action ) {
		$control = FrmFormActionsController::get_form_actions( 'email' );

		if ( ! is_object( $control ) || ! method_exists( $control, 'save_settings' ) ) {
			return false;
		}

		$saved = $control->save_settings( (array) $action );

		return ! empty( $saved ) && ! is_wp_error( $saved );
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();

		foreach ( FrmForm::get_published_forms() as $form ) {
			if ( empty( $form->id ) ) {
				continue;
			}

			$repointable = array();
			$all         = array();
			$copies      = array();

			foreach ( $this->actions( $form->id ) as $action ) {
				$settings = $this->settings_of( $action );
				$to       = isset( $settings['email_to'] ) ? (string) $settings['email_to'] : '';

				if ( '' !== trim( $to ) ) {
					$all[] = $to;
				}

				if ( $this->may_be_repointed( $to, self::STATIC_TAGS ) ) {
					$repointable[] = $to;
				}

				foreach ( array( 'cc', 'bcc' ) as $key ) {
					if ( ! empty( $settings[ $key ] ) ) {
						$copies[] = (string) $settings[ $key ];
					}
				}
			}

			$changeable = ! empty( $repointable );

			$out[] = array(
				'id'         => (string) $form->id,
				'title'      => isset( $form->name ) ? (string) $form->name : '',
				'recipient'  => $changeable
					? implode( ', ', array_unique( array_filter( $repointable, 'strlen' ) ) )
					: implode( ', ', array_unique( $all ) ),
				'aside'      => $changeable && count( $all ) > count( array_filter( $repointable, 'strlen' ) ),
				'changeable' => $changeable,
				'reason'     => $changeable ? '' : __( 'Every email action on this form is addressed with a field shortcode worked out for each entry, so there is no fixed address to change.', 'humainbox' ),
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

		$changed = false;

		foreach ( $this->actions( $form_id ) as $action ) {
			$settings = $this->settings_of( $action );

			if ( ! $this->may_be_repointed( isset( $settings['email_to'] ) ? $settings['email_to'] : '', self::STATIC_TAGS ) ) {
				continue;
			}

			$settings['email_to'] = $recipient;
			$action->post_content = $settings;

			if ( ! $this->save( $action ) ) {
				return false;
			}

			$changed = true;
		}

		// Nothing to write is not a successful write.
		return $changed;
	}

	public function snapshot( $form_id ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$to = array();

		foreach ( $this->actions( $form_id ) as $id => $action ) {
			$settings = $this->settings_of( $action );

			if ( $this->may_be_repointed( isset( $settings['email_to'] ) ? $settings['email_to'] : '', self::STATIC_TAGS ) ) {
				$to[ (string) $id ] = isset( $settings['email_to'] ) ? (string) $settings['email_to'] : '';
			}
		}

		return array( 'to' => $to );
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		if ( ! $this->is_available() || empty( $snapshot['to'] ) || ! is_array( $snapshot['to'] ) ) {
			return false;
		}

		$actions  = $this->actions( $form_id );
		$restored = 0;

		foreach ( $snapshot['to'] as $id => $address ) {
			// An action deleted since is not recreated.
			if ( ! isset( $actions[ $id ] ) ) {
				continue;
			}

			$action   = $actions[ $id ];
			$settings = $this->settings_of( $action );

			// Changed by hand since we pointed it at us: theirs now, not ours to undo.
			if ( ! $this->still_ours( isset( $settings['email_to'] ) ? $settings['email_to'] : '' ) ) {
				continue;
			}

			$settings['email_to'] = (string) $address;
			$action->post_content = $settings;

			if ( ! $this->save( $action ) ) {
				return false;
			}

			++$restored;
		}

		return $restored;
	}
}
