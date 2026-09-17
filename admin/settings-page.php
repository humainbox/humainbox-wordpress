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
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Humainbox', 'humainbox' ); ?></h1>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['text'] ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php
		echo esc_html__(
			'This page shows every contact form on this site and where each one currently delivers. That much works on its own, with or without a Humainbox account.',
			'humainbox'
		);
		?>
	</p>

	<h2><?php echo esc_html__( 'Your Humainbox address', 'humainbox' ); ?></h2>

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
						class="regular-text"
						autocomplete="off"
						value="<?php echo esc_attr( $settings['address'] ); ?>">
					<p class="description">
						<?php
						echo esc_html__(
							'The address Humainbox generated for you. It is shown in your Humainbox account under Inboxes.',
							'humainbox'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save address', 'humainbox' ) ); ?>
	</form>

	<h2><?php echo esc_html__( 'Forms on this site', 'humainbox' ); ?></h2>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'humainbox_apply' ); ?>
		<input type="hidden" name="action" value="humainbox_apply">

		<?php foreach ( $inventory as $group ) : ?>
			<h3><?php echo esc_html( $group['label'] ); ?></h3>

			<?php if ( ! $group['available'] ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: name of a form plugin. */
						esc_html__( '%s is not active on this site.', 'humainbox' ),
						esc_html( $group['label'] )
					);
					?>
				</p>

			<?php elseif ( empty( $group['forms'] ) ) : ?>
				<p class="description"><?php echo esc_html__( 'No forms found.', 'humainbox' ); ?></p>

			<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<td class="check-column"></td>
						<th scope="col"><?php echo esc_html__( 'Form', 'humainbox' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Delivers to', 'humainbox' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Original', 'humainbox' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $group['forms'] as $form ) : ?>
						<?php
						$token      = Humainbox_Forms::key( $group['slug'], $form['id'] );
						$field_id   = 'humainbox-form-' . sanitize_html_class( $group['slug'] . '-' . $form['id'] );
						$has_backup = isset( $backup[ $token ] );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input
									type="checkbox"
									name="humainbox_forms[]"
									id="<?php echo esc_attr( $field_id ); ?>"
									value="<?php echo esc_attr( $token ); ?>">
							</th>
							<td>
								<label for="<?php echo esc_attr( $field_id ); ?>">
									<?php echo esc_html( '' !== $form['title'] ? $form['title'] : __( '(untitled)', 'humainbox' ) ); ?>
								</label>
							</td>
							<td><code><?php echo esc_html( '' !== $form['recipient'] ? $form['recipient'] : __( '(nowhere)', 'humainbox' ) ); ?></code></td>
							<td>
								<?php if ( $has_backup ) : ?>
									<code><?php echo esc_html( $backup[ $token ]['recipient'] ); ?></code>
								<?php else : ?>
									<span aria-hidden="true">&mdash;</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php submit_button( __( 'Point selected forms at Humainbox', 'humainbox' ) ); ?>
	</form>

	<?php if ( ! empty( $backup ) ) : ?>
		<h2><?php echo esc_html__( 'Putting them back', 'humainbox' ); ?></h2>

		<p>
			<?php
			echo esc_html__(
				'These are the addresses your forms delivered to before this plugin changed them. They are kept here, in plain text, for as long as the plugin is installed — copy anything you want to keep before deleting it.',
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
						<td class="check-column"></td>
						<th scope="col"><?php echo esc_html__( 'Form', 'humainbox' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Original address', 'humainbox' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $backup as $token => $entry ) : ?>
						<?php $restore_id = 'humainbox-restore-' . sanitize_html_class( str_replace( ':', '-', $token ) ); ?>
						<tr>
							<th scope="row" class="check-column">
								<input
									type="checkbox"
									name="humainbox_forms[]"
									id="<?php echo esc_attr( $restore_id ); ?>"
									value="<?php echo esc_attr( $token ); ?>">
							</th>
							<td>
								<label for="<?php echo esc_attr( $restore_id ); ?>">
									<?php echo esc_html( '' !== $entry['title'] ? $entry['title'] : $token ); ?>
								</label>
							</td>
							<td>
								<code><?php echo esc_html( '' !== $entry['recipient'] ? $entry['recipient'] : __( '(nowhere)', 'humainbox' ) ); ?></code>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Restore selected forms', 'humainbox' ), 'secondary' ); ?>
		</form>
	<?php endif; ?>
</div>
