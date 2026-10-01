<?php
/**
 * Ninja Forms.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications are Email actions, each a model with its own settings. A form
 * usually has an admin notification ({wp:admin_email}) and often a confirmation to
 * the visitor ({field:email}), which is left alone.
 *
 * ⚠️ EVERY SETTING IS LOADED BEFORE ONE IS CHANGED.
 *
 * A Ninja Forms model loads its settings lazily, the first time one is read, and
 * only if it holds none yet. Calling update_setting() first leaves it holding one,
 * so it never loads the rest — and save() writes back what it holds. get_settings()
 * comes first, every time.
 *
 * ⚠️ AND THE FORM CACHE IS REBUILT AFTERWARDS, as the form builder does on publish.
 * Submissions read actions from the database, but other parts of Ninja Forms read
 * the cached form, and a cache left behind would show the old address back.
 */
class Humainbox_Ninja_Adapter extends Humainbox_Adapter {

	/**
	 * Ninja Forms' tags for the site's admin address. {field:...}, {user:...},
	 * {post:author_email} and the rest are worked out per submission.
	 */
	const STATIC_TAGS = array( '{wp:admin_email}', '{system:admin_email}' );

	public function slug() {
		return 'ninja';
	}

	public function label() {
		return 'Ninja Forms';
	}

	public function is_available() {
		return function_exists( 'Ninja_Forms' ) && class_exists( 'WPN_Helper' );
	}

	/**
	 * A form's Email actions, freshly read, with their settings loaded.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array{action: object, settings: array}> keyed by action ID
	 */
	private function actions( $form_id ) {
		$out = array();

		// No $where: Ninja Forms builds that query without an AND between its parts.
		foreach ( (array) Ninja_Forms()->form( absint( $form_id ) )->get_actions( array(), true ) as $action ) {
			$settings = (array) $action->get_settings();

			if ( isset( $settings['type'] ) && 'email' === $settings['type'] ) {
				$out[ (int) $action->get_id() ] = array(
					'action'   => $action,
					'settings' => $settings,
				);
			}
		}

		return $out;
	}

	/**
	 * Change one action's recipient and save it.
	 *
	 * @param int    $form_id   Form ID.
	 * @param int    $action_id Action ID.
	 * @param string $to        New recipient.
	 * @return bool
	 */
	private function write( $form_id, $action_id, $to ) {
		$action = Ninja_Forms()->form( absint( $form_id ) )->get_action( absint( $action_id ) );

		if ( ! is_object( $action ) ) {
			return false;
		}

		$action->get_settings();
		$action->update_setting( 'to', $to );
		$action->save();

		return true;
	}

	/**
	 * Rebuild the form cache, as publishing in the builder does.
	 *
	 * @param int $form_id Form ID.
	 */
	private function refresh( $form_id ) {
		if ( method_exists( 'WPN_Helper', 'build_nf_cache' ) ) {
			WPN_Helper::build_nf_cache( absint( $form_id ) );
		}
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();

		foreach ( (array) Ninja_Forms()->form()->get_forms() as $form ) {
			$form_id = (int) $form->get_id();

			$repointable = array();
			$all         = array();
			$copies      = array();

			foreach ( $this->actions( $form_id ) as $entry ) {
				$to = isset( $entry['settings']['to'] ) && is_string( $entry['settings']['to'] ) ? $entry['settings']['to'] : '';

				if ( '' !== trim( $to ) ) {
					$all[] = $to;
				}

				if ( $this->may_be_repointed( $to, self::STATIC_TAGS ) ) {
					$repointable[] = $to;
				}

				foreach ( array( 'cc', 'bcc' ) as $key ) {
					if ( ! empty( $entry['settings'][ $key ] ) && is_string( $entry['settings'][ $key ] ) ) {
						$copies[] = $entry['settings'][ $key ];
					}
				}
			}

			$changeable = ! empty( $repointable );
			$fixed      = array_unique( array_filter( $repointable, 'strlen' ) );

			$out[] = array(
				'id'         => (string) $form_id,
				'title'      => (string) $form->get_setting( 'title' ),
				'recipient'  => $changeable ? implode( ', ', $fixed ) : implode( ', ', array_unique( $all ) ),
				'aside'      => $changeable && count( $all ) > count( $fixed ),
				'changeable' => $changeable,
				'reason'     => $changeable ? '' : __( 'Every email action on this form is addressed with a merge tag worked out for each submission, so there is no fixed address to change.', 'humainbox' ),
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

		foreach ( $this->actions( $form_id ) as $action_id => $entry ) {
			$to = isset( $entry['settings']['to'] ) && is_string( $entry['settings']['to'] ) ? $entry['settings']['to'] : '';

			if ( ! $this->may_be_repointed( $to, self::STATIC_TAGS ) ) {
				continue;
			}

			if ( ! $this->write( $form_id, $action_id, $recipient ) ) {
				return false;
			}

			$changed = true;
		}

		if ( $changed ) {
			$this->refresh( $form_id );
		}

		return $changed;
	}

	public function snapshot( $form_id ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$to = array();

		foreach ( $this->actions( $form_id ) as $action_id => $entry ) {
			$value = isset( $entry['settings']['to'] ) && is_string( $entry['settings']['to'] ) ? $entry['settings']['to'] : '';

			if ( $this->may_be_repointed( $value, self::STATIC_TAGS ) ) {
				$to[ (string) $action_id ] = $value;
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

		foreach ( $snapshot['to'] as $action_id => $address ) {
			if ( ! isset( $actions[ $action_id ] ) ) {
				continue;
			}

			$now = isset( $actions[ $action_id ]['settings']['to'] ) && is_string( $actions[ $action_id ]['settings']['to'] ) ? $actions[ $action_id ]['settings']['to'] : '';

			// Changed by hand since we pointed it at us: theirs now, not ours to undo.
			if ( ! $this->still_ours( $now ) ) {
				continue;
			}

			if ( ! $this->write( $form_id, $action_id, (string) $address ) ) {
				return false;
			}

			++$restored;
		}

		if ( $restored > 0 ) {
			$this->refresh( $form_id );
		}

		return $restored;
	}
}
