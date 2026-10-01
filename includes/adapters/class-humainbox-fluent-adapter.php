<?php
/**
 * Fluent Forms.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each email notification is its own row of form meta, keyed 'notifications', with
 * the notification as JSON. sendTo.type says where it goes: 'email' to a typed
 * address, 'field' to an address the visitor entered, 'routing' by rules.
 *
 * ⚠️ ONE ROW PER NOTIFICATION, SO EACH IS WRITTEN BY ITS OWN ID.
 *
 * Fluent Forms' setFormMeta()/persist() helpers upsert on (form, meta_key) — they
 * treat the key as unique, and on a form with three notifications they would write
 * one row and leave the other two looking unchanged. Rows are read and written here
 * through Fluent Forms' own FormMeta model, by row id.
 *
 * ⚠️ AND NOT THROUGH ITS SETTINGS SERVICE. That one re-sanitises the whole
 * notification for users without unfiltered_html, which is a change to the message
 * template nobody asked for, made on the way to changing an address.
 */
class Humainbox_Fluent_Adapter extends Humainbox_Adapter {

	/**
	 * Fluent's smart codes for the site's admin address. {inputs.*}, {user.*} and
	 * the rest are worked out per submission.
	 */
	const STATIC_TAGS = array( '{wp.admin_email}', '{admin_email}' );

	public function slug() {
		return 'fluent';
	}

	public function label() {
		return 'Fluent Forms';
	}

	public function is_available() {
		return class_exists( '\FluentForm\App\Models\Form' ) && class_exists( '\FluentForm\App\Models\FormMeta' );
	}

	/**
	 * A form's notifications, decoded, keyed by their row id.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, array>
	 */
	private function notifications( $form_id ) {
		$out  = array();
		$rows = \FluentForm\App\Models\FormMeta::where( 'form_id', absint( $form_id ) )
			->where( 'meta_key', 'notifications' ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Fluent Forms' own table and model, indexed on form_id.
			->orderBy( 'id' )
			->get();

		foreach ( $rows as $row ) {
			$data = json_decode( (string) $row->value, true );

			if ( is_array( $data ) ) {
				$out[ (int) $row->id ] = $data;
			}
		}

		return $out;
	}

	/**
	 * Whether one notification goes to a typed address we may change.
	 *
	 * @param array $notification Decoded notification.
	 * @return bool
	 */
	private function repointable( array $notification ) {
		$type = isset( $notification['sendTo']['type'] ) ? $notification['sendTo']['type'] : 'email';

		return 'email' === $type
			&& $this->may_be_repointed( $this->address( $notification ), self::STATIC_TAGS );
	}

	/**
	 * A notification's typed address.
	 *
	 * @param array $notification Decoded notification.
	 * @return string
	 */
	private function address( array $notification ) {
		return isset( $notification['sendTo']['email'] ) && is_string( $notification['sendTo']['email'] ) ? $notification['sendTo']['email'] : '';
	}

	/**
	 * Write one notification back to its own row.
	 *
	 * @param int   $form_id Form ID.
	 * @param int   $row_id  Notification row id.
	 * @param array $data    Decoded notification.
	 * @return bool
	 */
	private function write( $form_id, $row_id, array $data ) {
		$updated = \FluentForm\App\Models\FormMeta::where( 'id', absint( $row_id ) )
			->where( 'form_id', absint( $form_id ) )
			->where( 'meta_key', 'notifications' ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Fluent Forms' own table and model; the row is addressed by its primary key.
			->update( array( 'value' => wp_json_encode( $data ) ) );

		return false !== $updated;
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();

		foreach ( \FluentForm\App\Models\Form::select( array( 'id', 'title' ) )->orderBy( 'id' )->get() as $form ) {
			$repointable = array();
			$all         = array();
			$copies      = array();

			foreach ( $this->notifications( $form->id ) as $notification ) {
				$type = isset( $notification['sendTo']['type'] ) ? $notification['sendTo']['type'] : 'email';
				$to   = $this->address( $notification );

				if ( 'email' !== $type || '' !== trim( $to ) ) {
					$all[] = 'email' === $type ? $to : '(' . $type . ')';
				}

				if ( $this->repointable( $notification ) ) {
					$repointable[] = $to;
				}

				foreach ( array( 'cc', 'bcc' ) as $key ) {
					if ( ! empty( $notification[ $key ] ) && is_string( $notification[ $key ] ) ) {
						$copies[] = $notification[ $key ];
					}
				}
			}

			$changeable = ! empty( $repointable );
			$fixed      = array_unique( array_filter( $repointable, 'strlen' ) );

			$out[] = array(
				'id'         => (string) $form->id,
				'title'      => (string) $form->title,
				'recipient'  => $changeable ? implode( ', ', $fixed ) : implode( ', ', array_unique( $all ) ),
				'aside'      => $changeable && count( $all ) > count( $fixed ),
				'changeable' => $changeable,
				'reason'     => $changeable ? '' : __( 'Every notification on this form goes to a field, by routing rules or to a smart code worked out for each submission, so there is no fixed address to change.', 'humainbox' ),
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

		foreach ( $this->notifications( $form_id ) as $row_id => $notification ) {
			if ( ! $this->repointable( $notification ) ) {
				continue;
			}

			$notification['sendTo']['email'] = $recipient;

			if ( ! $this->write( $form_id, $row_id, $notification ) ) {
				return false;
			}

			$changed = true;
		}

		return $changed;
	}

	public function snapshot( $form_id ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$to = array();

		foreach ( $this->notifications( $form_id ) as $row_id => $notification ) {
			if ( $this->repointable( $notification ) ) {
				$to[ (string) $row_id ] = $this->address( $notification );
			}
		}

		return array( 'to' => $to );
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		if ( ! $this->is_available() || empty( $snapshot['to'] ) || ! is_array( $snapshot['to'] ) ) {
			return false;
		}

		$notifications = $this->notifications( $form_id );
		$restored      = 0;

		foreach ( $snapshot['to'] as $row_id => $address ) {
			if ( ! isset( $notifications[ $row_id ] ) ) {
				continue;
			}

			$notification = $notifications[ $row_id ];

			// Changed by hand since we pointed it at us: theirs now, not ours to undo.
			if ( ! $this->still_ours( $this->address( $notification ) ) ) {
				continue;
			}

			$notification['sendTo']['email'] = (string) $address;

			if ( ! $this->write( $form_id, $row_id, $notification ) ) {
				return false;
			}

			++$restored;
		}

		return $restored;
	}
}
