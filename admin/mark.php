<?php
/**
 * Our mark, drawn inline.
 *
 * ── Why inline and not an <img>
 *
 * A logo fetched from a URL is a network request on somebody else's dashboard, and an
 * image file in the plugin is a request too. This is twenty-one circles; it costs less
 * as markup than as a HTTP round trip, it inherits nothing, and it cannot fail to load
 * and leave a broken-image box on a settings screen.
 *
 * ── And why it is small, but not as small as it was
 *
 * Beside the page title, which is where WordPress itself puts an icon. A plugin that
 * opens its own screen with a banner is a plugin advertising to somebody who has
 * already installed it — the screen is a tool, not a landing page.
 *
 * ⚠️ IT WAS 24px AND AT 24px IT IS DUST. The small dots in this mark are r=3.8 in a
 * 100-unit box, which is 0.9 of a pixel at that size — the grid stops being a grid
 * and becomes a grey smudge next to the word. Seen on a screen for the first time,
 * that is exactly what it was. The main site's own brand-mark component carries the
 * same warning about the full halftone below 28px; this is the compact mark and it
 * has the same floor.
 *
 * $humainbox_mark_size, so the node in the route drawing can ask for its own without
 * a second copy of twenty-one circles.
 *
 * Literal markup with no variables in it beyond the size, so there is nothing here
 * to escape that is not a number we set.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$humainbox_mark_size = isset( $humainbox_mark_size ) ? absint( $humainbox_mark_size ) : 30;
?>
<svg width="<?php echo esc_attr( $humainbox_mark_size ); ?>" height="<?php echo esc_attr( $humainbox_mark_size ); ?>" viewBox="0 0 100 100" aria-hidden="true" focusable="false" style="vertical-align:-6px;margin-right:8px">
	<circle cx="34" cy="18" r="3.8" fill="#C9C1B3"/><circle cx="50" cy="18" r="3.8" fill="#C9C1B3"/><circle cx="66" cy="18" r="3.8" fill="#C9C1B3"/>
	<circle cx="18" cy="34" r="3.8" fill="#C9C1B3"/><circle cx="34" cy="34" r="3.8" fill="#C9C1B3"/><circle cx="66" cy="34" r="3.8" fill="#C9C1B3"/><circle cx="82" cy="34" r="3.8" fill="#C9C1B3"/>
	<circle cx="18" cy="50" r="3.8" fill="#C9C1B3"/><circle cx="34" cy="50" r="3.8" fill="#C9C1B3"/><circle cx="50" cy="50" r="3.8" fill="#C9C1B3"/><circle cx="66" cy="50" r="3.8" fill="#C9C1B3"/><circle cx="82" cy="50" r="3.8" fill="#C9C1B3"/>
	<circle cx="18" cy="66" r="3.8" fill="#C9C1B3"/><circle cx="82" cy="66" r="3.8" fill="#C9C1B3"/>
	<circle cx="50" cy="34" r="6.8" fill="#236866"/>
	<circle cx="34" cy="66" r="6.8" fill="#236866"/><circle cx="50" cy="66" r="6.8" fill="#236866"/><circle cx="66" cy="66" r="6.8" fill="#236866"/>
	<circle cx="34" cy="82" r="6.8" fill="#236866"/><circle cx="50" cy="82" r="6.8" fill="#236866"/><circle cx="66" cy="82" r="6.8" fill="#236866"/>
</svg>
