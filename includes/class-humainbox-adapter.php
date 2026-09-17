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
	 * @return array<int, array{id:string,title:string,recipient:string}>
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
}
