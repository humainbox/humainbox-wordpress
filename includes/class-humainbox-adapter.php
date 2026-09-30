<?php
/**
 * What every form plugin has to be able to answer.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One form plugin, seen through the only four questions this plugin asks it.
 *
 * ── Why adapters rather than a switch statement
 *
 * Every form plugin stores its notification recipient somewhere different, and
 * three of them store it somewhere that is not a database column at all: Contact
 * Form 7 keeps it in post meta as part of a mail template, WPForms keeps it in a
 * JSON blob in post_content, Gravity Forms keeps it in an array of notification
 * objects. The ONLY safe way to change any of them is through that plugin's own
 * API, because each one validates, caches and versions its own data.
 *
 * ⚠️ NOTHING IN THIS PLUGIN TOUCHES THE DATABASE DIRECTLY. Writing to postmeta
 * behind Contact Form 7's back would leave its object cache holding the old mail
 * template until something happened to flush it, and the site owner would watch
 * enquiries keep arriving at the old address with the settings screen showing the
 * new one. Everything goes through get()/set() below.
 */
abstract class Humainbox_Adapter {

	/**
	 * Machine name. Used as an array key and in a nonce, never shown raw.
	 *
	 * @return string
	 */
	abstract public function slug();

	/**
	 * What the administrator calls this plugin.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Whether that plugin is actually active on this site.
	 *
	 * Checked on the API this adapter is about to use, not on a plugin file path:
	 * a plugin can be active with its classes not yet loaded, and a file can exist
	 * for a plugin that is deactivated.
	 *
	 * @return bool
	 */
	abstract public function is_available();

	/**
	 * Every form it knows about, and where each one currently delivers.
	 *
	 * ⚠️ EACH ONE ALSO SAYS WHETHER IT MAY BE CHANGED, and that is not a formality.
	 *
	 * A notification whose recipient is worked out per submission — the address the
	 * visitor typed, the author of the listing they are enquiring about, a conditional
	 * rule — is not a fixed address that happens to be inconvenient. Overwriting one
	 * with a single address does not redirect it; it BREAKS it, silently, in the case
	 * that matters most, and the site owner finds out when somebody asks why their
	 * enquiry went to the wrong person.
	 *
	 * Gravity Forms has refused these from the beginning. Contact Form 7 did not until
	 * this plugin was run against a real site, where three of six forms turned out to
	 * deliver to a mail tag rather than to an address.
	 *
	 * ⚠️ AND EACH ONE SAYS WHETHER IT ALREADY GOES TO US, which the screen cannot work
	 * out from 'recipient'. That is a display line joining every notification, and a
	 * WPForms form pointed at us still reads "…@in.humainbox.com, {field_id="1"}"
	 * because its visitor-copy is rightly left alone. Comparing that line to the
	 * saved address reported a form this plugin had just changed as "Unchanged", and
	 * drew the route as "nothing routed yet", on the one screen somebody checks to
	 * see whether it worked. See routing().
	 *
	 * 'notes' are the things about a form that change what pointing it at us MEANS:
	 * several people receiving it today, a Cc that bypasses the filter.
	 *
	 * @return array<int, array{id:string,title:string,recipient:string,changeable:bool,reason:string,routing:string,notes:string[]}>
	 */
	abstract public function forms();

	/**
	 * Point one form at an address.
	 *
	 * Returns true only when the change was actually saved. A silent false is what
	 * lets the settings screen tell the truth about a form it could not write to,
	 * instead of reporting success and leaving somebody's enquiries going nowhere.
	 *
	 * @param string $form_id   Form identifier as returned by forms().
	 * @param string $recipient A sanitized email address.
	 * @return bool
	 */
	abstract public function set_recipient( $form_id, $recipient );

	/**
	 * Everything needed to put this form back exactly as it is right now.
	 *
	 * ⚠️ THE DISPLAY STRING IS NOT ENOUGH, AND ASSUMING IT WAS CORRUPTED FORMS.
	 *
	 * forms() returns one human-readable line per form — "{admin_email},
	 * {field_id="1"}" for a WPForms form with two notifications. The backup stored
	 * that line and the restore wrote it back through set_recipient(), which put the
	 * whole joined string into the first notification as if it were one address. The
	 * original was then gone, and the backup entry deleted, and the only record of
	 * what the form used to do was destroyed by the feature that exists to protect it.
	 *
	 * Found by pointing a two-notification WPForms form at an address on a real site
	 * and pressing restore.
	 *
	 * The shape is this adapter's own business — a mail template key, a map of
	 * notification ids — and nothing outside reads into it.
	 *
	 * @param string $form_id Form identifier.
	 * @return array<string, mixed>
	 */
	abstract public function snapshot( $form_id );

	/**
	 * Put a form back to a snapshot taken earlier.
	 *
	 * ⚠️ ONLY WHAT STILL GOES TO US IS PUT BACK.
	 *
	 * The snapshot can be months old. If somebody has since changed a notification
	 * by hand in the form plugin — a new person, a new address — that is a newer
	 * decision than ours, and writing the old address over it would undo their
	 * work in the name of undoing ours. A notification is restored only while it
	 * still delivers to a Humainbox address and nothing else; see still_ours().
	 *
	 * @param string               $form_id  Form identifier.
	 * @param array<string, mixed> $snapshot As returned by snapshot().
	 * @return int|false How many notifications were put back — 0 when none still went
	 *                   to us, so there was nothing to undo — or false if saving failed.
	 */
	abstract public function apply_snapshot( $form_id, $snapshot );

	/**
	 * Whether a recipient is a plain address rather than something worked out later.
	 *
	 * Shared, because every form plugin has some version of this and the wrong answer
	 * costs the same in all of them. A square bracket is the tell in Contact Form 7
	 * ([your-email], [_site_admin_email], any third-party shortcode) and a brace in
	 * most of the others; neither appears in an email address, so anything carrying
	 * one is a template and not a destination.
	 *
	 * @param string $recipient Whatever the form plugin stores.
	 * @return bool
	 */
	protected function is_a_fixed_address( $recipient ) {
		$recipient = trim( (string) $recipient );

		if ( '' === $recipient ) {
			// Nowhere is a destination, and a fixed one. It is also the single most
			// damaging thing a contact form can be set to, so it must stay changeable.
			return true;
		}

		return ! preg_match( '/[\[\]{}]/', $recipient );
	}

	/**
	 * Whether this recipient may be repointed at one address.
	 *
	 * A plain address may. So may a tag the form plugin resolves to the SAME address
	 * every time — the site's admin address is one, and refusing it would refuse the
	 * commonest default on the web along with the arrangement the customer came here
	 * to replace.
	 *
	 * Anything else with a tag in it is worked out per submission: the address the
	 * visitor typed, the author of the post they are enquiring about, a conditional
	 * rule. Those are not inconvenient fixed addresses. Overwriting one does not
	 * redirect it, it breaks it — and it breaks the case that matters most, silently.
	 *
	 * @param string   $recipient   Whatever the form plugin stores.
	 * @param string[] $static_tags Tags this plugin resolves to one unchanging address.
	 * @return bool
	 */
	protected function may_be_repointed( $recipient, array $static_tags = array() ) {
		if ( in_array( trim( (string) $recipient ), $static_tags, true ) ) {
			return true;
		}

		return $this->is_a_fixed_address( $recipient );
	}

	/**
	 * The separate addresses in one recipient field.
	 *
	 * Every one of the three plugins accepts a comma-separated list there.
	 *
	 * @param string $recipient Whatever the form plugin stores.
	 * @return string[]
	 */
	protected function addresses_in( $recipient ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $recipient ) ), 'strlen' ) );
	}

	/**
	 * How much of a form's mail already goes to us.
	 *
	 * Asked only of the notifications this plugin would change: a visitor-copy
	 * addressed with a field tag is never ours and never will be, and counting it
	 * would leave every such form permanently "partly" routed.
	 *
	 * Any Humainbox address counts, not only the one saved on this screen today. A
	 * form pointed at last year's inbox is routed through us; calling it
	 * "Unchanged" would invite pointing it again and hide where it really goes.
	 *
	 * @param string[] $recipients Recipient fields of the repointable notifications.
	 * @return string 'all', 'some' or 'none'.
	 */
	protected function routing( array $recipients ) {
		$ours  = 0;
		$total = 0;

		foreach ( $recipients as $recipient ) {
			foreach ( $this->addresses_in( $recipient ) as $address ) {
				++$total;

				if ( humainbox_is_inbox_address( $address ) ) {
					++$ours;
				}
			}
		}

		if ( 0 === $ours ) {
			return 'none';
		}

		return $ours === $total ? 'all' : 'some';
	}

	/**
	 * Whether one notification still delivers to us and only to us.
	 *
	 * The test a restore applies before writing — see apply_snapshot().
	 *
	 * @param string $recipient Whatever the form plugin stores now.
	 * @return bool
	 */
	protected function still_ours( $recipient ) {
		return 'all' === $this->routing( array( $recipient ) );
	}

	/**
	 * What somebody should know before pointing this form at us.
	 *
	 * ── Several people
	 *
	 * A form addressed to "owner@, sales@" reaches two people today. After the change
	 * it reaches Humainbox, which forwards to the recipients set on the inbox — and
	 * unless both are set there, one of them silently stops hearing about enquiries.
	 * The plugin cannot see the inbox's recipients, so it says so while the list of
	 * people is still on screen to copy.
	 *
	 * ── A copy on the side
	 *
	 * A Cc, a Bcc or a second mail template to a fixed address is not repointed: it
	 * is a separate choice the site owner made and it is not ours to take away. But
	 * that copy never passes through Humainbox, so whoever gets it keeps getting the
	 * spam — and they are usually the person who asked for the filter.
	 *
	 * @param string[] $recipients Recipient fields of the repointable notifications.
	 * @param string[] $copies     Addresses copied outside those, however they are set.
	 * @return string[]
	 */
	protected function notes( array $recipients, array $copies ) {
		$notes  = array();
		$people = array();

		foreach ( $recipients as $recipient ) {
			foreach ( $this->addresses_in( $recipient ) as $address ) {
				if ( ! humainbox_is_inbox_address( $address ) ) {
					$people[] = $address;
				}
			}
		}

		$people = array_values( array_unique( $people ) );

		if ( count( $people ) > 1 ) {
			$notes[] = sprintf(
				/* translators: 1: how many addresses, 2: the addresses, comma-separated. */
				__( 'Reaches %1$d addresses today: %2$s. Once it goes to Humainbox, only the recipients set on your Humainbox inbox get it — add each of these there.', 'humainbox' ),
				count( $people ),
				implode( ', ', $people )
			);
		}

		$bypass = array();

		foreach ( $copies as $copy ) {
			foreach ( $this->addresses_in( $copy ) as $address ) {
				if ( ! humainbox_is_inbox_address( $address ) ) {
					$bypass[] = $address;
				}
			}
		}

		$bypass = array_values( array_unique( $bypass ) );

		if ( ! empty( $bypass ) ) {
			$notes[] = sprintf(
				/* translators: %s: the addresses copied, comma-separated. */
				__( 'Also sends a copy to %s. That copy does not pass through Humainbox and is not filtered, so it is left as it is — change it in the form plugin if it should be.', 'humainbox' ),
				implode( ', ', $bypass )
			);
		}

		return $notes;
	}
}
