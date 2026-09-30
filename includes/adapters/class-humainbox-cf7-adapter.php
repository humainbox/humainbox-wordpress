<?php
/**
 * Contact Form 7.
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The most common contact form on the web, and the one with the most surprising
 * storage: the recipient is one key inside a mail TEMPLATE, which also carries the
 * subject, the body, the headers and an attachments list.
 *
 * ⚠️ set_properties() REPLACES the whole 'mail' property, so the template has to be
 * read, one key changed, and the rest handed back untouched. Building a fresh array
 * with just a recipient in it would wipe the site's mail body — which is the kind of
 * mistake that is invisible until an enquiry arrives empty.
 */
class Humainbox_Cf7_Adapter extends Humainbox_Adapter {

	/**
	 * The one mail tag that means the same thing on every submission.
	 *
	 * ⚠️ WITHOUT THIS EXCEPTION THE PLUGIN DOES NOTHING ON A TYPICAL SITE. Contact Form
	 * 7 ships "Contact form 1" delivering to [_site_admin_email], so refusing every
	 * bracketed recipient refuses the single commonest case on the web — and the one
	 * the customer most wants changed, since "the site admin gets the enquiries" is
	 * precisely the arrangement they came here to replace.
	 *
	 * It is safe because it is static: it resolves to the site's admin address and to
	 * nothing else, whoever submits and from wherever. [_post_author_email] and every
	 * field tag are worked out per submission and stay refused.
	 */
	const STATIC_TAGS = array( '[_site_admin_email]' );

	public function slug() {
		return 'cf7';
	}

	public function label() {
		return 'Contact Form 7';
	}

	public function is_available() {
		return class_exists( 'WPCF7_ContactForm' );
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();

		/*
		 * find() rather than a WP_Query on the post type: it is Contact Form 7's own
		 * accessor, so it returns objects already wired to that plugin's properties
		 * and respects anything it does to the query.
		 */
		foreach ( WPCF7_ContactForm::find( array( 'posts_per_page' => 200 ) ) as $form ) {
			$mail      = $form->prop( 'mail' );
			$recipient = isset( $mail['recipient'] ) ? (string) $mail['recipient'] : '';
			$fixed     = $this->may_be_repointed( $recipient, self::STATIC_TAGS );

			$out[] = array(
				'id'         => (string) $form->id(),
				'title'      => (string) $form->title(),
				'recipient'  => $recipient,
				'changeable' => $fixed,
				'reason'     => $fixed ? '' : __( 'Delivers to a mail tag worked out for each submission — change this one in Contact Form 7, where you can see the whole template.', 'humainbox' ),
				'routing'    => $fixed ? $this->routing( array( $recipient ) ) : 'none',
				'notes'      => $fixed ? $this->notes( array( $recipient ), $this->copies( $form ) ) : array(),
			);
		}

		return $out;
	}

	/**
	 * Everywhere else this form's mail goes, besides the recipient we change.
	 *
	 * Cc and Bcc live as free text in the template's additional headers, one header
	 * per line. Mail (2) is a second template of its own: usually the visitor's
	 * receipt, addressed with a field tag and none of our business, but sometimes a
	 * second copy to a colleague at a fixed address — and that copy is never
	 * filtered.
	 *
	 * @param WPCF7_ContactForm $form The form.
	 * @return string[]
	 */
	private function copies( $form ) {
		$copies = array();
		$mail   = $form->prop( 'mail' );

		if ( is_array( $mail ) && ! empty( $mail['additional_headers'] ) ) {
			preg_match_all( '/^\s*b?cc\s*:\s*(.+?)\s*$/im', (string) $mail['additional_headers'], $found );
			$copies = array_merge( $copies, $found[1] );
		}

		$second = $form->prop( 'mail_2' );

		if ( is_array( $second ) && ! empty( $second['active'] ) && isset( $second['recipient'] )
			&& '' !== trim( (string) $second['recipient'] )
			&& $this->may_be_repointed( $second['recipient'], self::STATIC_TAGS ) ) {
			$copies[] = (string) $second['recipient'];
		}

		return $copies;
	}

	public function set_recipient( $form_id, $recipient ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		$form = WPCF7_ContactForm::get_instance( absint( $form_id ) );

		if ( ! $form ) {
			return false;
		}

		$mail = $form->prop( 'mail' );

		if ( ! is_array( $mail ) ) {
			return false;
		}

		/*
		 | ⚠️ REFUSED HERE AS WELL AS HIDDEN IN THE INTERFACE.
		 |
		 | The screen gives a mail-tag recipient no checkbox, so this should be
		 | unreachable — and "should be unreachable" is exactly the guard that is worth
		 | having, because the token arrives in a POST body and the form could have
		 | changed between the page being drawn and the button being pressed.
		 |
		 | A real site made this concrete: [custom-post-author-email-shortcode] resolves
		 | to the author of the listing being enquired about. Replacing it with one
		 | address sends every listing's enquiries to the wrong person, and nothing
		 | anywhere reports it.
		 */
		if ( ! $this->may_be_repointed( isset( $mail['recipient'] ) ? $mail['recipient'] : '', self::STATIC_TAGS ) ) {
			return false;
		}

		// One key. Everything else in the template is the site owner's work.
		$mail['recipient'] = $recipient;

		$form->set_properties( array( 'mail' => $mail ) );

		return (bool) $form->save();
	}

	public function snapshot( $form_id ) {
		$form = WPCF7_ContactForm::get_instance( absint( $form_id ) );

		if ( ! $form ) {
			return array();
		}

		$mail = $form->prop( 'mail' );

		return array( 'recipient' => isset( $mail['recipient'] ) ? (string) $mail['recipient'] : '' );
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		if ( ! isset( $snapshot['recipient'] ) ) {
			return false;
		}

		$form = WPCF7_ContactForm::get_instance( absint( $form_id ) );

		if ( ! $form ) {
			return false;
		}

		$mail = $form->prop( 'mail' );

		if ( ! is_array( $mail ) ) {
			return false;
		}

		// Changed by hand since we pointed it at us: theirs now, not ours to undo.
		if ( ! $this->still_ours( isset( $mail['recipient'] ) ? $mail['recipient'] : '' ) ) {
			return 0;
		}

		// Putting a mail tag BACK is allowed; only changing one is refused. This is
		// restoring what the site owner had, not choosing it for them.
		$mail['recipient'] = (string) $snapshot['recipient'];

		$form->set_properties( array( 'mail' => $mail ) );

		return $form->save() ? 1 : false;
	}
}
