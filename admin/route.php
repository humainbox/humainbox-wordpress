<?php
/**
 * The product's whole mechanic, in one line, above the table.
 *
 * ── Why a drawing and not another paragraph
 *
 * The screen is a table of addresses, and a table of addresses cannot show a CHANGE.
 * Somebody reading it has to hold "it goes here now" and "it would go there instead"
 * in their head at the same time, from two columns, and then work out what the thing
 * in the middle does. That is the entire idea of the product and it was the one thing
 * the screen never showed.
 *
 * ── It REPORTS. That is the whole licence for it being here.
 *
 * A drawing that looks the same whatever the site is doing is a picture of our
 * marketing, and marketing does not belong on somebody's settings screen. Every
 * caption under every node is a fact about this site right now: how many forms were
 * found, which address is saved, how many are actually routed through it. Grey and
 * unconnected until they are.
 *
 * The captions used to say what each node DOES — "reads each submission" — and that
 * was the explanatory version of the same drawing. The sentence under the page title
 * says what Humainbox does, once. This says what it is doing here.
 *
 * @var string $humainbox_address Saved address, or ''.
 * @var int    $humainbox_total   How many forms were found.
 * @var int    $humainbox_pointed How many already go to us.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$humainbox_live = '' !== $humainbox_address && $humainbox_pointed > 0;
?>
<div class="humainbox-route">
	<div class="humainbox-node">
		<span class="humainbox-node-mark" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
				<rect x="3" y="4" width="18" height="16" rx="2"/>
				<path stroke-linecap="round" d="M7 9h10M7 13h10M7 17h5"/>
			</svg>
		</span>
		<span class="humainbox-node-name"><?php echo esc_html__( 'Your forms', 'humainbox' ); ?></span>
		<span class="humainbox-node-note">
			<?php
			printf(
				/* translators: %d: how many forms were found on this site. */
				esc_html( _n( '%d found on this site', '%d found on this site', $humainbox_total, 'humainbox' ) ),
				esc_html( number_format_i18n( $humainbox_total ) )
			);
			?>
		</span>
	</div>

	<div class="humainbox-link <?php echo $humainbox_live ? 'humainbox-link-live' : ''; ?>" aria-hidden="true"></div>

	<div class="humainbox-node humainbox-node-ours">
		<span class="humainbox-node-mark">
			<?php
			$humainbox_mark_size = 26;
			require HUMAINBOX_PATH . 'admin/mark.php';
			?>
		</span>
		<span class="humainbox-node-name"><?php echo esc_html__( 'Humainbox', 'humainbox' ); ?></span>
		<span class="humainbox-node-note">
			<?php if ( '' === $humainbox_address ) : ?>
				<?php echo esc_html__( 'no address saved', 'humainbox' ); ?>
			<?php else : ?>
				<?php /* Their own address, read back to them. The most status-like
				         thing this node can say, and a free check that what they
				         pasted is what was saved. */ ?>
				<code style="font-size:11px"><?php echo esc_html( $humainbox_address ); ?></code>
			<?php endif; ?>
		</span>
	</div>

	<div class="humainbox-link <?php echo $humainbox_live ? 'humainbox-link-live' : ''; ?>" aria-hidden="true"></div>

	<div class="humainbox-node">
		<span class="humainbox-node-mark" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
				<path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.6a1 1 0 00-.7.3l-2.4 2.4a1 1 0 01-.7.3h-3.2a1 1 0 01-.7-.3l-2.4-2.4a1 1 0 00-.7-.3H4"/>
			</svg>
		</span>
		<span class="humainbox-node-name"><?php echo esc_html__( 'Whoever answers', 'humainbox' ); ?></span>
		<span class="humainbox-node-note">
			<?php
			if ( '' === $humainbox_address ) {
				echo esc_html__( 'unchanged', 'humainbox' );
			} elseif ( $humainbox_pointed > 0 ) {
				printf(
					/* translators: %d: how many forms are pointed at Humainbox. */
					esc_html( _n( '%d form is routed this way', '%d forms are routed this way', $humainbox_pointed, 'humainbox' ) ),
					esc_html( number_format_i18n( $humainbox_pointed ) )
				);
			} else {
				echo esc_html__( 'nothing routed yet', 'humainbox' );
			}
			?>
		</span>
	</div>
</div>
