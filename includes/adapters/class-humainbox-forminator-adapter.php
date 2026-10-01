<?php
/**
 * Forminator.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A form's notifications live in one post meta, forminator_form_meta, as the
 * 'notifications' list. A notification sends to a typed list ('recipients'), or by
 * conditional rules ('email-recipients' => 'routing'), and save-draft notices go to
 * the visitor.
 *
 * ⚠️ WRITTEN AS POST META, SLASHED — NOT THROUGH Forminator_API::update_form().
 *
 * update_form() rebuilds the form from what it is given: fields not passed are
 * cleared, and submission behaviours and integration conditions are reset to
 * empty even when everything else is passed. And the model's own save() writes
 * the meta unslashed, so update_post_meta strips every backslash in the form on the
 * way — the same silent corruption WPForms had. So the meta is read, one key in one
 * notification changed, and written back slashed: update_post_meta unslashes it
 * once, and everything else in the form comes back exactly as it was.
 */
class Humainbox_Forminator_Adapter extends Humainbox_Adapter {

	const META = 'forminator_form_meta';

	public function slug() {
		return 'forminator';
	}

	public function label() {
		return 'Forminator';
	}

	public function is_available() {
		return class_exists( 'Forminator_API' ) && class_exists( 'Forminator_Form_Model' );
	}

	/**
	 * The stored meta for one form, or null.
	 *
	 * @param int $form_id Form (post) ID.
	 * @return array|null
	 */
	private function meta( $form_id ) {
		if ( 'forminator_forms' !== get_post_type( absint( $form_id ) ) ) {
			return null;
		}

		$meta = get_post_meta( absint( $form_id ), self::META, true );

		return is_array( $meta ) ? $meta : null;
	}

	/**
	 * Write the meta back, slashed so it is stored exactly as read.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $meta    Meta.
	 * @return bool
	 */
	private function write( $form_id, array $meta ) {
		$result = update_post_meta( absint( $form_id ), self::META, wp_slash( $meta ) );

		// false also means "unchanged", which here only happens when there was
		// nothing to change — callers only write after changing something.
		return false !== $result;
	}

	/**
	 * Whether one notification goes to a typed list we may change.
	 *
	 * @param array $notification One notification.
	 * @return bool
	 */
	private function repointable( array $notification ) {
		if ( isset( $notification['email-recipients'] ) && 'routing' === $notification['email-recipients'] ) {
			return false;
		}

		if ( isset( $notification['type'] ) && 'save_draft' === $notification['type'] ) {
			return false;
		}

		return $this->may_be_repointed( $this->address( $notification ) );
	}

	/**
	 * A notification's typed recipients.
	 *
	 * @param array $notification One notification.
	 * @return string
	 */
	private function address( array $notification ) {
		return isset( $notification['recipients'] ) && is_string( $notification['recipients'] ) ? $notification['recipients'] : '';
	}

	/**
	 * The form's display name.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $meta    Meta.
	 * @return string
	 */
	private function title( $form_id, array $meta ) {
		if ( ! empty( $meta['settings']['formName'] ) ) {
			return (string) $meta['settings']['formName'];
		}

		return (string) get_the_title( absint( $form_id ) );
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();

		// Forminator's own listing; polls and quizzes are other post types and never appear.
		foreach ( (array) Forminator_API::get_forms( null, 1, -1 ) as $model ) {
			if ( ! $model instanceof Forminator_Form_Model || empty( $model->id ) ) {
				continue;
			}

			$meta = $this->meta( $model->id );

			if ( null === $meta ) {
				continue;
			}

			$repointable = array();
			$all         = array();
			$copies      = array();

			foreach ( ( isset( $meta['notifications'] ) && is_array( $meta['notifications'] ) ? $meta['notifications'] : array() ) as $notification ) {
				$to = $this->address( $notification );

				if ( '' !== trim( $to ) || ( isset( $notification['email-recipients'] ) && 'routing' === $notification['email-recipients'] ) ) {
					$all[] = '' !== trim( $to ) ? $to : '(routing)';
				}

				if ( $this->repointable( $notification ) ) {
					$repointable[] = $to;
				}

				foreach ( array( 'cc-email', 'bcc-email' ) as $key ) {
					if ( ! empty( $notification[ $key ] ) && is_string( $notification[ $key ] ) ) {
						$copies[] = $notification[ $key ];
					}
				}
			}

			$changeable = ! empty( $repointable );
			$fixed      = array_unique( array_filter( $repointable, 'strlen' ) );

			$out[] = array(
				'id'         => (string) $model->id,
				'title'      => $this->title( $model->id, $meta ),
				'recipient'  => $changeable ? implode( ', ', $fixed ) : implode( ', ', array_unique( $all ) ),
				'aside'      => $changeable && count( $all ) > count( $fixed ),
				'changeable' => $changeable,
				'reason'     => $changeable ? '' : __( 'Every notification on this form is routed by rules or sent to an address from the form itself, so there is no fixed address to change.', 'humainbox' ),
				'routing'    => $changeable ? $this->routing( $repointable ) : 'none',
				'notes'      => $changeable ? $this->notes( $repointable, $copies ) : array(),
			);
		}

		return $out;
	}

	public function set_recipient( $form_id, $recipient ) {
		$meta = $this->is_available() ? $this->meta( $form_id ) : null;

		if ( null === $meta || empty( $meta['notifications'] ) || ! is_array( $meta['notifications'] ) ) {
			return false;
		}

		$changed = false;

		foreach ( $meta['notifications'] as $i => $notification ) {
			if ( ! is_array( $notification ) || ! $this->repointable( $notification ) ) {
				continue;
			}

			$meta['notifications'][ $i ]['recipients'] = $recipient;
			$changed                                   = true;
		}

		return $changed && $this->write( $form_id, $meta );
	}

	/**
	 * Keyed by each notification's slug rather than its position, so a notification
	 * added or removed since does not shift the rest onto the wrong address.
	 *
	 * @param string $form_id Form ID.
	 * @return array
	 */
	public function snapshot( $form_id ) {
		$meta = $this->is_available() ? $this->meta( $form_id ) : null;
		$to   = array();

		foreach ( ( null !== $meta && isset( $meta['notifications'] ) && is_array( $meta['notifications'] ) ? $meta['notifications'] : array() ) as $notification ) {
			if ( is_array( $notification ) && ! empty( $notification['slug'] ) && $this->repointable( $notification ) ) {
				$to[ (string) $notification['slug'] ] = $this->address( $notification );
			}
		}

		return array( 'to' => $to );
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		$meta = $this->is_available() ? $this->meta( $form_id ) : null;

		if ( null === $meta || empty( $snapshot['to'] ) || ! is_array( $snapshot['to'] ) || empty( $meta['notifications'] ) ) {
			return false;
		}

		$restored = 0;

		foreach ( $meta['notifications'] as $i => $notification ) {
			$slug = is_array( $notification ) && isset( $notification['slug'] ) ? (string) $notification['slug'] : '';

			if ( '' === $slug || ! array_key_exists( $slug, $snapshot['to'] ) ) {
				continue;
			}

			// Changed by hand since we pointed it at us: theirs now, not ours to undo.
			if ( ! $this->still_ours( $this->address( $notification ) ) ) {
				continue;
			}

			$meta['notifications'][ $i ]['recipients'] = (string) $snapshot['to'][ $slug ];
			++$restored;
		}

		if ( 0 === $restored ) {
			return 0;
		}

		return $this->write( $form_id, $meta ) ? $restored : false;
	}
}
