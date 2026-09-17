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
			$mail = $form->prop( 'mail' );

			$out[] = array(
				'id'        => (string) $form->id(),
				'title'     => (string) $form->title(),
				'recipient' => isset( $mail['recipient'] ) ? (string) $mail['recipient'] : '',
			);
		}

		return $out;
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

		// One key. Everything else in the template is the site owner's work.
		$mail['recipient'] = $recipient;

		$form->set_properties( array( 'mail' => $mail ) );

		return (bool) $form->save();
	}
}
