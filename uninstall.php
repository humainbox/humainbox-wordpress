<?php
/**
 * Deleting the plugin.
 *
 * @package Humainbox
 */

// This file is only ever included by WordPress during uninstall. Without the constant
// it is somebody fetching it directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IS DELETED, AND THE ONE THING THAT IS NOT
 *
 * The settings go. They are ours, they are small, and nothing needs them.
 *
 * ⚠️ THE RECORD OF ORIGINAL ADDRESSES STAYS, on purpose, and against the usual
 * instinct to remove every trace.
 *
 * Deleting it would be tidy and it would be the worst thing this plugin could do.
 * That option is the only copy of where a site's forms delivered BEFORE they were
 * pointed at Humainbox. The forms themselves are not changed back here — the address
 * currently in them is the one the administrator chose, and silently reverting it on
 * uninstall would turn off their filtering without asking. So the forms keep working
 * and the way back survives: reinstalling restores the list, and the settings screen
 * shows every original address in plain text so it can be copied out at any time.
 *
 * A few hundred bytes of orphaned option against somebody losing the address their
 * enquiries used to arrive at. It is not a close decision.
 *
 * Multisite is handled the same way, per site: options are per-site, so each one is
 * cleaned where it lives rather than through a network-wide query.
 */

delete_option( 'humainbox_settings' );

if ( is_multisite() ) {
	$humainbox_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

	foreach ( $humainbox_sites as $humainbox_site_id ) {
		switch_to_blog( $humainbox_site_id );
		delete_option( 'humainbox_settings' );
		restore_current_blog();
	}
}
