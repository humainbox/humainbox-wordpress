<?php
/**
 * The registry: every form on this site, wherever it lives.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds forms, changes where they deliver, and can put them back.
 *
 * ── The one rule everything here is built around
 *
 * ⚠️ NOTHING IS OVERWRITTEN BEFORE IT IS RECORDED. A contact form's recipient is the
 * address a business's enquiries arrive at; losing it silently is not a bug in a
 * settings screen, it is somebody's leads going to an address nobody remembers. So
 * apply() writes to the backup FIRST and only then asks the adapter to save, and the
 * backup is never overwritten for a form that already has one — otherwise pressing
 * "apply" twice would record the Humainbox address as the original and the way back
 * would be gone.
 *
 * The backup is also the reason this plugin can be deleted safely: the addresses it
 * replaced are visible on its own screen, in plain text, for as long as it is here.
 */
class Humainbox_Forms {

	/**
	 * All adapters, available or not.
	 *
	 * Unavailable ones are kept in the list so the screen can say "Gravity Forms is
	 * not active on this site" rather than silently omitting it — an administrator
	 * looking for a form that is missing should learn why.
	 *
	 * @return Humainbox_Adapter[]
	 */
	public static function adapters() {
		return array(
			new Humainbox_Cf7_Adapter(),
			new Humainbox_Wpforms_Adapter(),
			new Humainbox_Gravity_Adapter(),
		);
	}

	/**
	 * Every form this site has, grouped by the plugin that owns it.
	 *
	 * @return array<int, array{slug:string,label:string,available:bool,forms:array}>
	 */
	public static function inventory() {
		$out = array();

		foreach ( self::adapters() as $adapter ) {
			$available = $adapter->is_available();

			$out[] = array(
				'slug'      => $adapter->slug(),
				'label'     => $adapter->label(),
				'available' => $available,
				'forms'     => $available ? $adapter->forms() : array(),
			);
		}

		return $out;
	}

	/**
	 * Point one form at an address, keeping a way back.
	 *
	 * @param string $slug      Adapter slug.
	 * @param string $form_id   Form identifier.
	 * @param string $recipient Sanitized address.
	 * @return bool Whether the form was actually saved.
	 */
	public static function apply( $slug, $form_id, $recipient ) {
		$adapter = self::adapter( $slug );

		if ( ! $adapter || ! $adapter->is_available() ) {
			return false;
		}

		$key     = self::key( $slug, $form_id );
		$backup  = self::backup();
		$created = false;

		/*
		 | ⚠️ RECORDED BEFORE THE WRITE, AND ONLY ONCE.
		 |
		 | Only once, because the second press of "apply" would otherwise read back the
		 | address this plugin has just written and record THAT as the original. One
		 | double-click and the way home is gone, with nothing on screen to show it.
		 */
		if ( ! isset( $backup[ $key ] ) ) {
			$current = self::current_recipient( $adapter, $form_id );

			// An empty original is still an original — a form that delivered nowhere
			// is a fact worth being able to restore to.
			$backup[ $key ] = array(
				// For the screen: one readable line saying where it used to go.
				'recipient' => $current,
				/*
				 | ⚠️ AND THE PART THE RESTORE ACTUALLY USES.
				 |
				 | 'recipient' is a DISPLAY string — "{admin_email}, {field_id=\"1\"}"
				 | for a form with two notifications. Restoring from it wrote that
				 | whole joined line into the first notification as one address, and
				 | then deleted the backup: the undo destroyed the only record of what
				 | it was undoing. See Humainbox_Adapter::snapshot().
				 */
				'snapshot'  => $adapter->snapshot( $form_id ),
				'title'     => self::title( $adapter, $form_id ),
				'saved_at'  => time(),
			);

			update_option( HUMAINBOX_BACKUP_OPTION, $backup, false );
			$created = true;
		}

		if ( $adapter->set_recipient( $form_id, $recipient ) ) {
			return true;
		}

		/*
		 | The write failed, so the form is exactly as it was — and a record made for
		 | it a moment ago would list it in the "Before Humainbox" column as if it had been
		 | changed. Only this call's own record is withdrawn: one that was already
		 | there belongs to an earlier, successful change and is still the way back.
		 */
		if ( $created ) {
			$backup = self::backup();
			unset( $backup[ $key ] );
			update_option( HUMAINBOX_BACKUP_OPTION, $backup, false );
		}

		return false;
	}

	/**
	 * Put one form back to the address it had before this plugin touched it.
	 *
	 * The backup entry is removed only when the write succeeds. A restore that failed
	 * must leave the record in place, or a second attempt would have nothing to
	 * restore from and the screen would report that there was nothing to undo.
	 *
	 * ⚠️ THREE OUTCOMES, NOT TWO. A form somebody has since pointed somewhere else by
	 * hand has nothing of ours left to undo: it is left as it is and its record is
	 * cleared, and the screen says so rather than counting it as put back. The
	 * restore table showed where it goes now before the button was pressed.
	 *
	 * @param string $slug    Adapter slug.
	 * @param string $form_id Form identifier.
	 * @return string 'restored', 'kept' or 'failed'.
	 */
	public static function restore( $slug, $form_id ) {
		$adapter = self::adapter( $slug );
		$key     = self::key( $slug, $form_id );
		$backup  = self::backup();

		if ( ! $adapter || ! $adapter->is_available() || ! isset( $backup[ $key ] ) ) {
			return 'failed';
		}

		/*
		 | A backup written before snapshots existed has none. Falling back to the old
		 | behaviour would reintroduce the corruption, so it is refused instead — the
		 | record stays, and the screen reports a form it could not restore rather than
		 | quietly mangling it.
		 */
		if ( empty( $backup[ $key ]['snapshot'] ) || ! is_array( $backup[ $key ]['snapshot'] ) ) {
			return 'failed';
		}

		$restored = $adapter->apply_snapshot( $form_id, $backup[ $key ]['snapshot'] );

		if ( false === $restored ) {
			return 'failed';
		}

		unset( $backup[ $key ] );
		update_option( HUMAINBOX_BACKUP_OPTION, $backup, false );

		return $restored > 0 ? 'restored' : 'kept';
	}

	/**
	 * What was there before, for every form this plugin has changed.
	 *
	 * @return array
	 */
	public static function backup() {
		$backup = get_option( HUMAINBOX_BACKUP_OPTION, array() );

		return is_array( $backup ) ? $backup : array();
	}

	/**
	 * A stable key for one form.
	 *
	 * The adapter slug is part of it because form ids are only unique within their own
	 * plugin: Contact Form 7's form 3 and Gravity's form 3 are different forms, and a
	 * key of "3" would have one restore the other.
	 *
	 * @param string $slug    Adapter slug.
	 * @param string $form_id Form identifier.
	 * @return string
	 */
	public static function key( $slug, $form_id ) {
		return $slug . ':' . $form_id;
	}

	/**
	 * One adapter by slug, or null.
	 *
	 * @param string $slug Adapter slug.
	 * @return Humainbox_Adapter|null
	 */
	private static function adapter( $slug ) {
		foreach ( self::adapters() as $adapter ) {
			if ( $adapter->slug() === $slug ) {
				return $adapter;
			}
		}

		return null;
	}

	/**
	 * Where a form delivers right now, asked of the adapter.
	 *
	 * @param Humainbox_Adapter $adapter Adapter.
	 * @param string            $form_id Form identifier.
	 * @return string
	 */
	private static function current_recipient( $adapter, $form_id ) {
		foreach ( self::forms_of( $adapter ) as $form ) {
			if ( (string) $form['id'] === (string) $form_id ) {
				return (string) $form['recipient'];
			}
		}

		return '';
	}

	/**
	 * A form's title, for the backup record.
	 *
	 * Stored alongside the address so the restore list still reads properly after the
	 * form itself has been deleted — otherwise an administrator is offered the chance
	 * to restore "cf7:12" and has no idea what that was.
	 *
	 * @param Humainbox_Adapter $adapter Adapter.
	 * @param string            $form_id Form identifier.
	 * @return string
	 */
	private static function title( $adapter, $form_id ) {
		foreach ( self::forms_of( $adapter ) as $form ) {
			if ( (string) $form['id'] === (string) $form_id ) {
				return (string) $form['title'];
			}
		}

		return '';
	}

	/**
	 * One adapter's form list, read once per request.
	 *
	 * Applying to thirty forms used to list every form twice per form — sixty full
	 * inventories, each decoding every WPForms form's JSON — to find one title and
	 * one address. The list is only ever read here for a form BEFORE that form is
	 * written, so an entry read at the start of the request is still true for it.
	 *
	 * @param Humainbox_Adapter $adapter Adapter.
	 * @return array
	 */
	private static function forms_of( $adapter ) {
		static $cache = array();

		if ( ! isset( $cache[ $adapter->slug() ] ) ) {
			$cache[ $adapter->slug() ] = $adapter->forms();
		}

		return $cache[ $adapter->slug() ];
	}
}
