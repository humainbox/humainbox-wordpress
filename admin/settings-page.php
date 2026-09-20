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

	foreach ( $humainbox_group['forms'] as $humainbox_form ) {
		if ( '' !== $humainbox_address && $humainbox_form['recipient'] === $humainbox_address ) {
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
			'Humainbox checks every form submission and holds back the ones a machine wrote, so the real leads still reach you.',
			'humainbox'
		);
		?>
	</p>

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
				<strong><?php echo esc_html__( 'You do not need one of these to use this page.', 'humainbox' ); ?></strong>
				<?php echo esc_html__( 'The table below works without an address and without contacting anything.', 'humainbox' ); ?>
			</p>
			<p style="margin:0 0 .5em"><?php echo esc_html__( 'If you want one:', 'humainbox' ); ?></p>
			<ol class="humainbox-prose" style="margin:0 0 .75em 1.5em">
				<li>
					<?php /* The link is its own element rather than a %s inside a
					         translated sentence: printf-ing raw markup into an escaped
					         string is the shape every escaping sniff flags, and the one
					         a reviewer stops on even when it is safe. */ ?>
					<a href="https://humainbox.com" target="_blank" rel="noopener noreferrer">humainbox.com</a>
					&mdash; <?php echo esc_html__( 'open an account. It is free to start and there is no card.', 'humainbox' ); ?>
				</li>
				<li><?php echo esc_html__( 'Copy the address it generates for you — it is under Inboxes.', 'humainbox' ); ?></li>
				<li><?php echo esc_html__( 'Paste it below, then tick the forms you want to point at it.', 'humainbox' ); ?></li>
			</ol>
			<p style="margin:0">
				<a href="https://humainbox.com/integrations/wordpress" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'What it does with a submission', 'humainbox' ); ?>
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
							'Shown in your Humainbox account under Inboxes. Saving it here changes nothing on its own.',
							'humainbox'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save address', 'humainbox' ) ); ?>
	</form>

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
					'None of the form plugins this version understands are active here. Nothing is wrong — your forms are simply built with something else, and their notification settings are unchanged.',
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
				<strong><?php echo esc_html__( 'Pointing a form at Humainbox does not hold anything back to begin with.', 'humainbox' ); ?></strong>
				<?php
				echo esc_html__(
					'A new inbox starts in dry run: every submission is forwarded exactly as it is today, while Humainbox records what it would have held. You decide after a week of your own mail. The address each form used before is saved here either way, and putting it back is one action.',
					'humainbox'
				);
				?>
			</span>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			id="humainbox-apply-form"
			data-humainbox-address="<?php echo esc_attr( $humainbox_address ); ?>">
			<?php wp_nonce_field( 'humainbox_apply' ); ?>
			<input type="hidden" name="action" value="humainbox_apply">

			<?php foreach ( $humainbox_active as $humainbox_group ) : ?>
				<?php if ( empty( $humainbox_group['forms'] ) ) { continue; } ?>

				<h3><?php echo esc_html( $humainbox_group['label'] ); ?></h3>

				<table class="widefat striped">
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
							<th scope="col"><?php echo esc_html__( 'Notifications go to', 'humainbox' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Status', 'humainbox' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $humainbox_group['forms'] as $humainbox_form ) : ?>
							<?php
							$humainbox_token      = Humainbox_Forms::key( $humainbox_group['slug'], $humainbox_form['id'] );
							$humainbox_field_id   = 'humainbox-form-' . sanitize_html_class( $humainbox_group['slug'] . '-' . $humainbox_form['id'] );
							$humainbox_is_ours    = '' !== $humainbox_address && $humainbox_form['recipient'] === $humainbox_address;
							$humainbox_nowhere    = '' === $humainbox_form['recipient'];
							// Absent means changeable: an adapter that has not learned to
							// say otherwise must not have its forms silently withheld.
							$humainbox_changeable = ! isset( $humainbox_form['changeable'] ) || $humainbox_form['changeable'];
							?>
							<tr>
								<th scope="row" class="check-column">
									<?php if ( $humainbox_changeable ) : ?>
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
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! $humainbox_changeable ) : ?>
										<span class="humainbox-left-alone">
											<span class="humainbox-dot humainbox-dot-held" aria-hidden="true"></span>
											<?php echo esc_html__( 'Left alone', 'humainbox' ); ?>
										</span>
									<?php elseif ( $humainbox_is_ours ) : ?>
										<span>
											<span class="humainbox-dot humainbox-dot-live" aria-hidden="true"></span>
											<?php echo esc_html__( 'Humainbox', 'humainbox' ); ?>
										</span>
									<?php else : ?>
										<span class="humainbox-left-alone">
											<span class="humainbox-dot" aria-hidden="true"></span>
											<?php echo esc_html__( 'Unchanged', 'humainbox' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<?php
			submit_button(
				__( 'Point selected forms at Humainbox', 'humainbox' ),
				'primary',
				'submit',
				true,
				// Nothing to point them AT. A button that can only fail is worse than
				// one that is plainly not available yet.
				'' === $humainbox_address ? array( 'disabled' => 'disabled' ) : array()
			);
			?>

			<?php if ( '' === $humainbox_address ) : ?>
				<p class="description"><?php echo esc_html__( 'Save an address above first.', 'humainbox' ); ?></p>
			<?php elseif ( $humainbox_pointed > 0 ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: number already pointed at Humainbox, 2: total forms. */
						esc_html( _n( '%1$d of %2$d already goes to Humainbox.', '%1$d of %2$d already go to Humainbox.', $humainbox_pointed, 'humainbox' ) ),
						esc_html( number_format_i18n( $humainbox_pointed ) ),
						esc_html( number_format_i18n( $humainbox_total ) )
					);
					?>
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
			<?php echo esc_html__( 'Forms built with anything else are not listed, and nothing about them has been touched.', 'humainbox' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $backup ) ) : ?>
		<h2><?php echo esc_html__( 'Putting them back', 'humainbox' ); ?></h2>

		<p class="humainbox-prose">
			<?php
			echo esc_html__(
				'These are the addresses your forms sent to before this plugin changed them. They are kept here, in plain text, for as long as the plugin is installed — copy anything you want to keep before deleting it.',
				'humainbox'
			);
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'humainbox_restore' ); ?>
			<input type="hidden" name="action" value="humainbox_restore">

			<table class="widefat striped">
				<thead>
					<tr>
						<td class="check-column">
							<label class="screen-reader-text" for="humainbox-all-restore">
								<?php echo esc_html__( 'Select all forms', 'humainbox' ); ?>
							</label>
							<input type="checkbox" class="humainbox-select-all" id="humainbox-all-restore">
						</td>
						<th scope="col"><?php echo esc_html__( 'Form', 'humainbox' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Original address', 'humainbox' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $backup as $humainbox_token => $humainbox_entry ) : ?>
						<?php $humainbox_restore_id = 'humainbox-restore-' . sanitize_html_class( str_replace( ':', '-', $humainbox_token ) ); ?>
						<tr>
							<th scope="row" class="check-column">
								<label class="screen-reader-text" for="<?php echo esc_attr( $humainbox_restore_id ); ?>">
									<?php echo esc_html__( 'Select this form', 'humainbox' ); ?>
								</label>
								<input
									type="checkbox"
									class="humainbox-form"
									name="humainbox_forms[]"
									id="<?php echo esc_attr( $humainbox_restore_id ); ?>"
									value="<?php echo esc_attr( $humainbox_token ); ?>">
							</th>
							<td>
								<strong><?php echo esc_html( '' !== $humainbox_entry['title'] ? $humainbox_entry['title'] : $humainbox_token ); ?></strong>
							</td>
							<td>
								<code><?php echo esc_html( '' !== $humainbox_entry['recipient'] ? $humainbox_entry['recipient'] : __( '(nowhere)', 'humainbox' ) ); ?></code>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Restore selected forms', 'humainbox' ), 'secondary' ); ?>
		</form>
	<?php endif; ?>
</div>
