<?php
/**
 * The settings screen markup.
 *
 * ⚠️ EVERY value printed here is escaped AT THE POINT OF OUTPUT, never earlier and
 * never once "upstream". Form titles and recipient addresses come out of other
 * plugins' storage, which means they came from whoever typed them — and a form titled
 * with a script tag is not a hypothetical, it is what a compromised site looks like
 * from in here.
 *
 * ── Written for somebody in wp-admin, not for somebody on our website
 *
 * The person reading this has already installed the plugin. They do not need to be
 * sold to; they need to know what the button does before they press it, in the
 * vocabulary of the screen they are standing on — forms, notifications, recipients.
 * The one piece of persuasion on the page is the sentence about the first week, and
 * it is here because it is the answer to the question somebody about to rewrite their
 * live forms' recipients is actually asking.
 *
 * Variables are provided by Humainbox_Settings::render().
 *
 * @var array $settings  Stored settings.
 * @var array $inventory Forms grouped by plugin.
 * @var array $backup    Original recipients.
 * @var array|null $notice Message from the last action.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$humainbox_address = $settings['address'];

// Counted once, used in three sentences. A screen that cannot say how many forms it
// found is a screen somebody has to count by hand before they trust it.
$humainbox_total    = 0;
$humainbox_pointed  = 0;
$humainbox_active   = array();
$humainbox_inactive = array();

foreach ( $inventory as $humainbox_group ) {
	if ( ! $humainbox_group['available'] ) {
		$humainbox_inactive[] = $humainbox_group['label'];
		continue;
	}

	$humainbox_active[] = $humainbox_group;
	$humainbox_total   += count( $humainbox_group['forms'] );

	// Asked of the adapter, not worked out from the recipient line: that line joins
	// every notification, and a form rightly keeping its visitor-copy never equals
	// the saved address however completely it has been pointed at us.
	foreach ( $humainbox_group['forms'] as $humainbox_form ) {
		if ( isset( $humainbox_form['routing'] ) && 'all' === $humainbox_form['routing'] ) {
			++$humainbox_pointed;
		}
	}
}
?>
<div class="wrap">
	<h1>
		<?php require HUMAINBOX_PATH . 'admin/mark.php'; ?>
		<?php echo esc_html__( 'Humainbox', 'humainbox' ); ?>
	</h1>

	<?php
	/*
	 | ⚠️ ONE LINE, UNDER THE TITLE, AND NOT A SECTION.
	 |
	 | Somebody may open this screen without knowing what the plugin is — an agency
	 | developer in a client's dashboard, whoever inherited the site. That is a real
	 | gap and one sentence closes it.
	 |
	 | It is not a block, a card or a heading, because the person reading it has
	 | already installed this. A settings screen that opens by explaining the product
	 | is a brochure, and a brochure is the specific thing a plugin review rejects.
	 | The full account is in the readme, which is where wordpress.org shows it.
	 */
	?>
	<p class="description humainbox-prose" style="margin:.25em 0 1.25em">
		<?php
		echo esc_html__(
			'Humainbox stops spam — including messages written by AI — from reaching you through your contact forms, without a CAPTCHA. Real enquiries still reach you, and nothing is deleted.',
			'humainbox'
		);
		?>
	</p>

	<?php
	/*
	 | What it stops and what it lets through, side by side.
	 |
	 | Somebody who has never heard of Humainbox needs one answer before anything else
	 | on this screen makes sense: "what will it do to my enquiries?". Two short lists
	 | answer it faster than a paragraph. Every item is taken from what the classifier
	 | is actually told to do — ClassificationPrompt in the service — and nothing here
	 | promises more than that.
	 |
	 | A <details>, open only until an address is saved: after that it is reference,
	 | and a settings screen that keeps explaining the product to somebody already
	 | using it is a brochure.
	 */
	?>
	<details class="humainbox-explain" <?php echo '' === $settings['address'] ? 'open' : ''; ?>>
		<summary><?php echo esc_html__( 'What does Humainbox stop — and what does it let through?', 'humainbox' ); ?></summary>
		<div class="humainbox-explain-grid">
			<div>
				<h3 class="humainbox-explain-held"><?php echo esc_html__( 'Held back as spam', 'humainbox' ); ?></h3>
				<ul>
					<li><?php echo esc_html__( 'SEO, link-building and "rank higher on Google" offers', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Web design, app and offshore development pitches', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Crypto, traffic and directory-listing schemes', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Template messages sent to thousands of sites at once', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'All of the above when written fluently by AI — the kind CAPTCHAs no longer stop', 'humainbox' ); ?></li>
				</ul>
			</div>
			<div>
				<h3 class="humainbox-explain-through"><?php echo esc_html__( 'Always reaches you', 'humainbox' ); ?></h3>
				<ul>
					<li><?php echo esc_html__( 'Enquiries, quote requests and bookings', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Complaints and support questions', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Job applications, press and partnership approaches', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Suppliers introducing themselves', 'humainbox' ); ?></li>
					<li><?php echo esc_html__( 'Anything it is unsure about — when in doubt, it delivers', 'humainbox' ); ?></li>
				</ul>
			</div>
		</div>
		<p class="description humainbox-prose">
			<?php echo esc_html__( 'Held messages are never deleted: they stay readable in your Humainbox panel with the reason they were held, and one click sends them on. It adds no CAPTCHA and changes nothing your visitors see.', 'humainbox' ); ?>
		</p>
	</details>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['text'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php require HUMAINBOX_PATH . 'admin/route.php'; ?>

	<h2><?php echo esc_html__( 'Your Humainbox address', 'humainbox' ); ?></h2>

	<?php if ( '' === $humainbox_address ) : ?>
		<?php
		/*
		 | ⚠️ THE STEPS ARE HERE AND ONLY HERE — in the empty state, where the screen
		 | has room and the question somebody actually has is "what do I do now".
		 |
		 | Once an address is saved they are gone. Instructions that stay on a screen
		 | after they have been followed are the definition of clutter, and they are
		 | also what turns a settings page into a brochure, which is the fastest way to
		 | fail a plugin review.
		 |
		 | Three, not five. The fourth and fifth are on the table below, where they
		 | happen.
		 */
		?>
		<div class="notice notice-info inline" style="margin:0 0 1.5em;padding:.75em 1em">
			<p class="humainbox-prose" style="margin:.25em 0 .75em">
				<strong><?php echo esc_html__( 'New to Humainbox?', 'humainbox' ); ?></strong>
				<?php echo esc_html__( 'Your forms send their enquiries to a Humainbox address instead of straight to you. Humainbox holds back the spam and forwards every real enquiry to the people you choose. Nothing changes on your website.', 'humainbox' ); ?>
			</p>
			<p style="margin:0 0 .5em"><?php echo esc_html__( 'To set it up:', 'humainbox' ); ?></p>
			<ol class="humainbox-prose" style="margin:0 0 .75em 1.5em">
				<li>
					<?php /* The link is its own element rather than a %s inside a
					         translated sentence: printf-ing raw markup into an escaped
					         string is the shape every escaping sniff flags, and the one
					         a reviewer stops on even when it is safe. */ ?>
					<a href="https://humainbox.com" target="_blank" rel="noopener noreferrer">humainbox.com</a>
					&mdash; <?php echo esc_html__( 'create a free account. No card needed.', 'humainbox' ); ?>
				</li>
				<li><?php echo esc_html__( 'Choose who should receive your enquiries. Anyone other than you gets one email asking them to confirm.', 'humainbox' ); ?></li>
				<li><?php echo esc_html__( 'Copy your inbox address (under Inboxes), paste it below and send a test message.', 'humainbox' ); ?></li>
				<li><?php echo esc_html__( 'Tick the forms you want protected and connect them.', 'humainbox' ); ?></li>
			</ol>
			<p class="description" style="margin:0 0 .5em"><?php echo esc_html__( 'You do not need an account to see the table of your forms below.', 'humainbox' ); ?></p>
			<p style="margin:0">
				<a href="https://humainbox.com/integrations/wordpress" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'How Humainbox decides what is spam', 'humainbox' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'humainbox_save' ); ?>
		<input type="hidden" name="action" value="humainbox_save">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="humainbox_address"><?php echo esc_html__( 'Address', 'humainbox' ); ?></label>
				</th>
				<td>
					<input
						name="humainbox_address"
						id="humainbox_address"
						type="email"
						class="regular-text code"
						autocomplete="off"
						placeholder="<?php echo esc_attr__( 'something@in.humainbox.com', 'humainbox' ); ?>"
						value="<?php echo esc_attr( $humainbox_address ); ?>">
					<p class="description">
						<?php
						echo esc_html__(
							'Your inbox address, from your Humainbox account under Inboxes. It ends in @in.humainbox.com. Saving it does not change any form yet.',
							'humainbox'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save address', 'humainbox' ) ); ?>
	</form>

	<?php if ( '' !== $humainbox_address ) : ?>
		<?php /* Its own form, because forms cannot nest, and right under the address it
		         checks: the one test that catches a mistyped address before any
		         enquiry is sent into it. See Humainbox_Settings::handle_test(). */ ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="humainbox-test">
			<?php wp_nonce_field( 'humainbox_test' ); ?>
			<input type="hidden" name="action" value="humainbox_test">
			<?php submit_button( __( 'Send a test message', 'humainbox' ), 'secondary', 'submit', false ); ?>
			<span class="description">
				<?php echo esc_html__( 'Sends one email to the address above, the same way your forms do. It should appear in your Humainbox panel within a minute — if it does not, check the address before connecting any form.', 'humainbox' ); ?>
			</span>
		</form>
	<?php endif; ?>

	<h2>
		<?php echo esc_html__( 'Forms on this site', 'humainbox' ); ?>
		<?php if ( $humainbox_total > 0 ) : ?>
			<span class="title-count"><?php echo esc_html( number_format_i18n( $humainbox_total ) ); ?></span>
		<?php endif; ?>
	</h2>

	<?php if ( 0 === $humainbox_total ) : ?>
		<p class="description humainbox-prose">
			<?php
			if ( empty( $humainbox_active ) ) {
				echo esc_html__(
					'No Contact Form 7, WPForms or Gravity Forms forms were found on this site. If your forms are built with something else, you can still protect them: paste your Humainbox address into their notification settings by hand.',
					'humainbox'
				);
			} else {
				echo esc_html__( 'No forms found in the plugins that are active.', 'humainbox' );
			}
			?>
		</p>
	<?php else : ?>

		<?php /* The reassurance, immediately above the control it is about. Somebody
		         hovering over a button that rewrites their live forms' recipients is
		         asking one question, and it is this one. */ ?>
		<div class="humainbox-assure">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3z"/>
				<path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12l1.8 1.8 3.5-3.6"/>
			</svg>
			<span>
				<strong><?php echo esc_html__( 'Nothing is blocked in the first week.', 'humainbox' ); ?></strong>
				<?php
				echo esc_html__(
					'A new Humainbox inbox starts in dry run: every enquiry is still delivered, while Humainbox shows you what it would have held as spam. You switch filtering on when you are ready. From now on, enquiries go to the recipients you set in Humainbox — anyone who has not yet confirmed their address gets nothing until they do. Each form\'s current address is saved here, and one button puts it back.',
					'humainbox'
				);
				?>
			</span>
		</div>

		<?php
		/*
		 | ⚠️ ONE TABLE, TWO ACTIONS.
		 |
		 | Undo used to be its own section below, with its own table listing the same
		 | forms again. Every form appeared twice, and the one question somebody has
		 | about a form — where does it go now, where did it go before, is it
		 | protected — was split across two tables they had to match up by title.
		 | Now each row carries its original address, and the two buttons act on
		 | whatever is ticked.
		 |
		 | One admin_post action for both, with the button deciding which: a form can
		 | only carry one nonce, and two handlers sharing one nonce would be two
		 | handlers each trusting a check made for the other.
		 */
		$humainbox_shown = array();

		/*
		 | "[_site_admin_email]" means nothing to somebody who did not build the form.
		 | The two tags this plugin will change resolve to one known address, so the
		 | screen can say which — the rest are left as they are and left unexplained.
		 */
		$humainbox_tag_note = function ( $recipient ) {
			foreach ( array( '[_site_admin_email]', '{admin_email}' ) as $tag ) {
				if ( false !== strpos( (string) $recipient, $tag ) ) {
					return '<p class="description">' . esc_html(
						sprintf(
							/* translators: 1: a form plugin's tag, 2: the site's admin email address. */
							__( '%1$s is your site\'s admin email: %2$s', 'humainbox' ),
							$tag,
							get_bloginfo( 'admin_email' )
						)
					) . '</p>';
				}
			}

			return '';
		};
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			id="humainbox-forms-form"
			data-humainbox-address="<?php echo esc_attr( $humainbox_address ); ?>">
			<?php wp_nonce_field( 'humainbox_forms' ); ?>
			<input type="hidden" name="action" value="humainbox_forms">

			<?php foreach ( $humainbox_active as $humainbox_group ) : ?>
				<?php if ( empty( $humainbox_group['forms'] ) ) { continue; } ?>

				<h3><?php echo esc_html( $humainbox_group['label'] ); ?></h3>

				<table class="widefat striped humainbox-forms">
					<thead>
						<tr>
							<td class="check-column">
								<?php /* Select-all. WordPress ships this on its own list
								         tables and a table without one is a table somebody
								         clicks twelve times. */ ?>
								<label class="screen-reader-text" for="humainbox-all-<?php echo esc_attr( $humainbox_group['slug'] ); ?>">
									<?php echo esc_html__( 'Select all forms', 'humainbox' ); ?>
								</label>
								<input type="checkbox" class="humainbox-select-all"
									id="humainbox-all-<?php echo esc_attr( $humainbox_group['slug'] ); ?>">
							</td>
							<th scope="col"><?php echo esc_html__( 'Form', 'humainbox' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Sends enquiries to', 'humainbox' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Before Humainbox', 'humainbox' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Spam filter', 'humainbox' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $humainbox_group['forms'] as $humainbox_form ) : ?>
							<?php
							$humainbox_token      = Humainbox_Forms::key( $humainbox_group['slug'], $humainbox_form['id'] );
							$humainbox_field_id   = 'humainbox-form-' . sanitize_html_class( $humainbox_group['slug'] . '-' . $humainbox_form['id'] );
							$humainbox_routing    = isset( $humainbox_form['routing'] ) ? $humainbox_form['routing'] : 'none';
							$humainbox_nowhere    = '' === $humainbox_form['recipient'];
							// Absent means changeable: an adapter that has not learned to
							// say otherwise must not have its forms silently withheld.
							$humainbox_changeable = ! isset( $humainbox_form['changeable'] ) || $humainbox_form['changeable'];
							$humainbox_original   = isset( $backup[ $humainbox_token ] ) ? $backup[ $humainbox_token ] : null;
							// A form this plugin changed can always be put back, even one that
							// has since become something it would not change today.
							$humainbox_selectable = $humainbox_changeable || null !== $humainbox_original;
							$humainbox_shown[]    = $humainbox_token;
							?>
							<tr>
								<th scope="row" class="check-column">
									<?php if ( $humainbox_selectable ) : ?>
										<label class="screen-reader-text" for="<?php echo esc_attr( $humainbox_field_id ); ?>">
											<?php
											printf(
												/* translators: %s: the form's title. */
												esc_html__( 'Select %s', 'humainbox' ),
												esc_html( '' !== $humainbox_form['title'] ? $humainbox_form['title'] : __( '(untitled)', 'humainbox' ) )
											);
											?>
										</label>
										<input
											type="checkbox"
											class="humainbox-form"
											name="humainbox_forms[]"
											id="<?php echo esc_attr( $humainbox_field_id ); ?>"
											value="<?php echo esc_attr( $humainbox_token ); ?>">
									<?php else : ?>
										<?php /* No checkbox at all. A disabled one is a control
										         somebody tries to press; an absent one, with the
										         reason written beside it, is an explanation. */ ?>
										<span aria-hidden="true"></span>
									<?php endif; ?>
								</th>
								<td>
									<strong><?php echo esc_html( '' !== $humainbox_form['title'] ? $humainbox_form['title'] : __( '(untitled)', 'humainbox' ) ); ?></strong>
									<?php if ( ! $humainbox_changeable && ! empty( $humainbox_form['reason'] ) ) : ?>
										<p class="description" style="margin:.25em 0 0">
											<?php echo esc_html( $humainbox_form['reason'] ); ?>
										</p>
									<?php endif; ?>
									<?php if ( $humainbox_changeable && ! empty( $humainbox_form['notes'] ) ) : ?>
										<?php foreach ( $humainbox_form['notes'] as $humainbox_note ) : ?>
											<p class="description humainbox-note"><?php echo esc_html( $humainbox_note ); ?></p>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $humainbox_nowhere ) : ?>
										<?php /* A form with no recipient sends its notifications
										         nowhere. That is worth saying out loud on a site
										         whose owner believes it is collecting enquiries. */ ?>
										<span class="humainbox-nobody">
											<span class="humainbox-dot humainbox-dot-none" aria-hidden="true"></span>
											<?php echo esc_html__( 'nobody — this form notifies no one', 'humainbox' ); ?>
										</span>
									<?php else : ?>
										<code><?php echo esc_html( $humainbox_form['recipient'] ); ?></code>
										<?php echo $humainbox_tag_note( $humainbox_form['recipient'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_html() in $humainbox_tag_note. ?>
										<?php if ( ! empty( $humainbox_form['aside'] ) ) : ?>
											<p class="description"><?php echo esc_html__( 'Also emails a copy to an address from the form itself, usually the visitor — left as it is.', 'humainbox' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( null === $humainbox_original ) : ?>
										<span class="humainbox-left-alone" aria-label="<?php echo esc_attr__( 'Not changed by this plugin', 'humainbox' ); ?>">&mdash;</span>
									<?php else : ?>
										<code><?php echo esc_html( '' !== $humainbox_original['recipient'] ? $humainbox_original['recipient'] : __( '(nowhere)', 'humainbox' ) ); ?></code>
										<?php echo $humainbox_tag_note( $humainbox_original['recipient'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_html() in $humainbox_tag_note. ?>
										<?php if ( 'none' === $humainbox_routing ) : ?>
											<?php /* Said before the button, not only after it: restore
											         will leave this one alone, and why. */ ?>
											<p class="description humainbox-note"><?php echo esc_html__( 'Changed by hand since — restoring leaves it as it is.', 'humainbox' ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! $humainbox_changeable && 'all' !== $humainbox_routing ) : ?>
										<span class="humainbox-left-alone">
											<span class="humainbox-dot humainbox-dot-held" aria-hidden="true"></span>
											<?php echo esc_html__( 'Cannot change here', 'humainbox' ); ?>
										</span>
									<?php elseif ( 'all' === $humainbox_routing ) : ?>
										<span>
											<span class="humainbox-dot humainbox-dot-live" aria-hidden="true"></span>
											<?php echo esc_html__( 'Connected', 'humainbox' ); ?>
										</span>
									<?php elseif ( 'some' === $humainbox_routing ) : ?>
										<?php /* Our address sits beside somebody else's, so that person
										         still gets every submission unfiltered. */ ?>
										<span>
											<span class="humainbox-dot humainbox-dot-held" aria-hidden="true"></span>
											<?php echo esc_html__( 'Partly connected', 'humainbox' ); ?>
										</span>
									<?php else : ?>
										<span class="humainbox-left-alone">
											<span class="humainbox-dot" aria-hidden="true"></span>
											<?php echo esc_html__( 'Not connected', 'humainbox' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<p class="submit humainbox-actions">
				<?php /* <button>, not submit_button(): the two share one form, and the
				         clicked button's value is what tells the handler which to do. */ ?>
				<button type="submit" name="humainbox_do" value="apply" class="button button-primary"
					<?php disabled( '' === $humainbox_address ); ?>>
					<?php echo esc_html__( 'Connect selected forms to Humainbox', 'humainbox' ); ?>
				</button>
				<?php if ( ! empty( $backup ) ) : ?>
					<button type="submit" name="humainbox_do" value="restore" class="button">
						<?php echo esc_html__( 'Restore selected to original address', 'humainbox' ); ?>
					</button>
				<?php endif; ?>
			</p>

			<?php if ( '' === $humainbox_address ) : ?>
				<p class="description"><?php echo esc_html__( 'Save your Humainbox address above first.', 'humainbox' ); ?></p>
			<?php elseif ( $humainbox_pointed > 0 ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: number protected by Humainbox, 2: total forms. */
						esc_html( _n( '%1$d of %2$d form is connected to Humainbox.', '%1$d of %2$d forms are connected to Humainbox.', $humainbox_total, 'humainbox' ) ),
						esc_html( number_format_i18n( $humainbox_pointed ) ),
						esc_html( number_format_i18n( $humainbox_total ) )
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $backup ) ) : ?>
				<p class="description humainbox-prose">
					<?php echo esc_html__( 'Restoring sends a form\'s enquiries straight to its original address again, without spam protection. Original addresses are kept even if you delete this plugin.', 'humainbox' ); ?>
				</p>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( ! empty( $humainbox_inactive ) ) : ?>
		<p class="description humainbox-prose">
			<?php
			printf(
				/* translators: %s: comma-separated list of form plugin names. */
				esc_html__( 'Not active on this site: %s.', 'humainbox' ),
				esc_html( implode( ', ', $humainbox_inactive ) )
			);
			?>
			<?php echo esc_html__( 'Forms built with other plugins are not listed and are never touched.', 'humainbox' ); ?>
		</p>
	<?php endif; ?>

	<?php
	/*
	 | ⚠️ THE ONE CASE THE TABLE CANNOT SHOW: a saved original for a form that is no
	 | longer listed — deleted, or its plugin deactivated. It cannot be restored from
	 | here, but the address it used is still the only record of where its enquiries
	 | went, so it is shown rather than silently kept out of sight.
	 */
	$humainbox_orphans = array_diff_key( $backup, array_flip( isset( $humainbox_shown ) ? $humainbox_shown : array() ) );
	?>
	<?php if ( ! empty( $humainbox_orphans ) ) : ?>
		<h2><?php echo esc_html__( 'Saved addresses for forms no longer here', 'humainbox' ); ?></h2>
		<p class="description humainbox-prose">
			<?php echo esc_html__( 'These forms were connected to Humainbox, but have since been deleted or their form plugin is inactive, so they cannot be restored from here. Where they used to send:', 'humainbox' ); ?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Form', 'humainbox' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Before Humainbox', 'humainbox' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $humainbox_orphans as $humainbox_token => $humainbox_entry ) : ?>
					<tr>
						<td><strong><?php echo esc_html( '' !== $humainbox_entry['title'] ? $humainbox_entry['title'] : $humainbox_token ); ?></strong></td>
						<td><code><?php echo esc_html( '' !== $humainbox_entry['recipient'] ? $humainbox_entry['recipient'] : __( '(nowhere)', 'humainbox' ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
