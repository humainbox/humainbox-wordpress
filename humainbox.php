<?php
/**
 * Plugin Name:       Humainbox
 * Plugin URI:        https://humainbox.com/integrations
 * Description:       See where every contact form on this site delivers, and point them all at a Humainbox address in one click. Keeps a copy of the original addresses so you can put them back.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Reaktör Teknoloji
 * Author URI:        https://humainbox.com/about
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       humainbox
 *
 * @package Humainbox
 */

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THIS PLUGIN IS, AND WHY IT IS NOT A CONTRADICTION
 *
 * Humainbox's whole pitch is that no plugin is required: you change one field —
 * the recipient address in your form's notification settings — and nothing else
 * about your site changes. That is still true, and this plugin does not sit in
 * the path of a single request. It is a CONFIGURATOR, not a runtime component.
 *
 * Its reason to exist is the site with six forms across four form plugins, where
 * "change one field" is really "find twelve settings screens". It does that once,
 * keeps a copy of what was there before, and can put it back.
 *
 * Delete this plugin afterwards and everything keeps working, because the address
 * lives in the form plugin's own settings, not here.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE REVIEW, TAKEN SERIOUSLY
 *
 * The .org review team rejects on specifics and each round trip costs weeks, so
 * the rules are written into the structure rather than remembered:
 *
 *  - Everything is prefixed. Functions humainbox_, classes Humainbox_, constants
 *    HUMAINBOX_, options humainbox_. No generic name is defined anywhere.
 *  - Every file starts by refusing direct access.
 *  - Every admin action checks a capability AND a nonce, in that order.
 *  - Every input is sanitized on the way in; every output is escaped at the point
 *    of output, never earlier.
 *  - No database queries. The form plugins' own public APIs are used, so a change
 *    goes through their validation and their caches instead of behind them.
 *  - No HTTP except through wp_remote_*, and none at all unless the user asks.
 *  - Nothing is ever printed on the public site. No credits, no links, no styles.
 *  - No admin notices outside this plugin's own screen. Nobody is nagged.
 *  - No account is required for the parts that work without one, and the readme
 *    says exactly what leaves the site and when.
 *
 * @link https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
 */

// Called directly? There is nothing here for you.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HUMAINBOX_VERSION', '1.0.0' );
define( 'HUMAINBOX_FILE', __FILE__ );
define( 'HUMAINBOX_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Where the chosen address is kept.
 *
 * One option holding a small array, rather than a row per setting: it is read on
 * every admin page load for this plugin and never anywhere else, and a single
 * autoloaded option is cheaper than four.
 */
define( 'HUMAINBOX_OPTION', 'humainbox_settings' );

/**
 * Where the addresses that were there BEFORE are kept.
 *
 * ⚠️ Separate from the settings on purpose. This is the only record of what a
 * site's forms used to deliver to, and losing it means an administrator cannot
 * put things back. Nothing in this plugin writes to a form without writing here
 * first — see Humainbox_Forms::apply().
 */
define( 'HUMAINBOX_BACKUP_OPTION', 'humainbox_original_recipients' );

require_once HUMAINBOX_PATH . 'includes/class-humainbox-adapter.php';
require_once HUMAINBOX_PATH . 'includes/adapters/class-humainbox-cf7-adapter.php';
require_once HUMAINBOX_PATH . 'includes/adapters/class-humainbox-wpforms-adapter.php';
require_once HUMAINBOX_PATH . 'includes/adapters/class-humainbox-gravity-adapter.php';
require_once HUMAINBOX_PATH . 'includes/class-humainbox-forms.php';
require_once HUMAINBOX_PATH . 'includes/class-humainbox-settings.php';

/**
 * Boot the admin side, and only the admin side.
 *
 * is_admin() rather than an unconditional hook: this plugin has no front-end
 * behaviour whatsoever, so on a visitor's request it should cost one function
 * call and nothing else.
 */
function humainbox_boot() {
	if ( is_admin() ) {
		Humainbox_Settings::instance()->hooks();
	}
}
add_action( 'plugins_loaded', 'humainbox_boot' );

/**
 * A link to the settings from the plugins list.
 *
 * The one place a plugin may add a link to itself without being accused of
 * hijacking the dashboard — it is on the row for this plugin, on a screen the
 * administrator opened to manage plugins.
 *
 * @param array $links Existing action links.
 * @return array
 */
function humainbox_action_links( $links ) {
	$settings = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=humainbox' ) ),
		esc_html__( 'Settings', 'humainbox' )
	);

	array_unshift( $links, $settings );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'humainbox_action_links' );
