<?php
/**
 * Elementor Forms (Elementor Pro).
 *
 * @package Humainbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A form is a widget inside a page's element tree, so a form is identified here as
 * "{post}.{element}" — one page can hold several, and the same element id means
 * nothing on another page. Forms in templates, popups and global widgets live in
 * elementor_library posts and are found the same way.
 *
 * ── The Email action
 *
 * The widget's own settings carry it: email_to, with email_to_cc and email_to_bcc
 * beside it. Elementor saves only settings that differ from their defaults, and the
 * default for email_to is the site's admin address — so a form whose email_to was
 * never touched has NO email_to, and still sends to the admin. That absence is
 * recorded in the snapshot, so a restore removes the key again rather than writing
 * today's admin address into it.
 *
 * Email 2 (email_to_2) is usually the visitor's confirmation and is never changed.
 *
 * ⚠️ SAVED THROUGH THE DOCUMENT'S OWN save(), NEVER BY WRITING THE META.
 *
 * save() encodes and slashes the tree itself, regenerates the page's CSS, clears
 * Elementor's element cache and records a revision. Writing _elementor_data directly
 * would skip all of that. And like WPForms, it strips HTML from the whole page for a
 * user without unfiltered_html — so for that user the form is listed but not offered.
 */
class Humainbox_Elementor_Adapter extends Humainbox_Adapter {

	/** How many pages with forms are read. A site with more is not a typical one. */
	const LIMIT = 200;

	public function slug() {
		return 'elementor';
	}

	public function label() {
		return 'Elementor Forms';
	}

	public function is_available() {
		return class_exists( '\Elementor\Plugin' ) && class_exists( '\ElementorPro\Modules\Forms\Module' );
	}

	/**
	 * Saving would strip HTML from the page for this user — see the class note.
	 *
	 * @return bool
	 */
	private function may_save() {
		return current_user_can( 'unfiltered_html' );
	}

	/**
	 * Posts whose Elementor data contains a form widget.
	 *
	 * @return int[]
	 */
	private function candidates() {
		$types = array_unique( array_merge( get_post_types_by_support( 'elementor' ), array( 'elementor_library' ) ) );

		$query = new WP_Query(
			array(
				'post_type'      => $types,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'posts_per_page' => self::LIMIT,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Elementor keeps its tree in post meta; this is the only way to find forms without reading every page.
				'meta_query'     => array(
					array(
						'key'     => '_elementor_data',
						'value'   => '"widgetType":"form"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * A post's Elementor document, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function document( $post_id ) {
		$document = \Elementor\Plugin::$instance->documents->get( absint( $post_id ), false );

		return is_object( $document ) && method_exists( $document, 'get_elements_data' ) ? $document : null;
	}

	/**
	 * Every form widget in a tree, by element id.
	 *
	 * @param array $elements Element tree.
	 * @return array<string, array> settings keyed by element id
	 */
	private function find_forms( array $elements ) {
		$found = array();

		foreach ( $elements as $element ) {
			if ( isset( $element['widgetType'] ) && 'form' === $element['widgetType'] && isset( $element['id'] ) ) {
				$found[ (string) $element['id'] ] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found += $this->find_forms( $element['elements'] );
			}
		}

		return $found;
	}

	/**
	 * Change one widget's settings in a tree.
	 *
	 * @param array    $elements Element tree, by reference.
	 * @param string   $id       Element id.
	 * @param callable $change   Receives the settings array by reference.
	 * @return bool Whether the element was found.
	 */
	private function change( array &$elements, $id, callable $change ) {
		foreach ( $elements as &$element ) {
			if ( isset( $element['id'] ) && (string) $element['id'] === $id && isset( $element['widgetType'] ) && 'form' === $element['widgetType'] ) {
				if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
					$element['settings'] = array();
				}

				$change( $element['settings'] );

				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->change( $element['elements'], $id, $change ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split "{post}.{element}".
	 *
	 * @param string $form_id Form identifier.
	 * @return array{0:int,1:string}
	 */
	private function split_id( $form_id ) {
		$parts = explode( '.', (string) $form_id, 2 );

		return array( absint( $parts[0] ), isset( $parts[1] ) ? sanitize_key( $parts[1] ) : '' );
	}

	/**
	 * Whether the Email action runs for this form at all.
	 *
	 * @param array $settings Widget settings.
	 * @return bool
	 */
	private function sends_email( array $settings ) {
		$actions = isset( $settings['submit_actions'] ) && is_array( $settings['submit_actions'] ) ? $settings['submit_actions'] : array( 'email' );

		return in_array( 'email', $actions, true );
	}

	/**
	 * The address Email goes to: the saved value, or the admin address it defaults to.
	 *
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function recipient_of( array $settings ) {
		return array_key_exists( 'email_to', $settings ) ? (string) $settings['email_to'] : (string) get_bloginfo( 'admin_email' );
	}

	/**
	 * Whether this form's Email recipient may be changed.
	 *
	 * @param array $settings Widget settings.
	 * @return bool
	 */
	private function repointable( array $settings ) {
		// A dynamic tag replaces the value at send time, whatever the field holds.
		if ( ! empty( $settings['__dynamic__']['email_to'] ) ) {
			return false;
		}

		return $this->sends_email( $settings ) && $this->may_be_repointed( $this->recipient_of( $settings ) );
	}

	public function forms() {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out      = array();
		$may_save = $this->may_save();

		foreach ( $this->candidates() as $post_id ) {
			$document = $this->document( $post_id );

			if ( null === $document ) {
				continue;
			}

			$page = get_the_title( $post_id );

			foreach ( $this->find_forms( (array) $document->get_elements_data() ) as $element_id => $settings ) {
				$name  = isset( $settings['form_name'] ) && '' !== (string) $settings['form_name'] ? (string) $settings['form_name'] : __( 'Form', 'humainbox' );
				$to    = $this->recipient_of( $settings );
				$fixed = $this->repointable( $settings );

				$copies = array();

				foreach ( array( 'email_to_cc', 'email_to_bcc' ) as $key ) {
					if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
						$copies[] = $settings[ $key ];
					}
				}

				$second = isset( $settings['submit_actions'] ) && is_array( $settings['submit_actions'] ) && in_array( 'email2', $settings['submit_actions'], true );
				$to_2   = isset( $settings['email_to_2'] ) && is_string( $settings['email_to_2'] ) ? $settings['email_to_2'] : '';

				// Email 2 to a fixed address is a copy outside the filter, like CF7's Mail (2).
				if ( $second && '' !== trim( $to_2 ) && $this->may_be_repointed( $to_2 ) ) {
					$copies[] = $to_2;
				}

				if ( ! $this->sends_email( $settings ) ) {
					$reason = __( 'This form does not send an email notification, so there is no address to change.', 'humainbox' );
				} elseif ( ! $fixed ) {
					$reason = __( 'Sends to an address worked out for each submission (a field or a dynamic tag) — change this one in Elementor.', 'humainbox' );
				} elseif ( ! $may_save ) {
					$reason = __( 'Your account on this site is not allowed to save HTML, and Elementor would strip it from this page while saving the change. Change the address in Elementor, or ask an administrator who can.', 'humainbox' );
				} else {
					$reason = '';
				}

				$changeable = $fixed && $may_save;

				$out[] = array(
					'id'         => $post_id . '.' . $element_id,
					/* translators: 1: the form's name, 2: the page or template it is on. */
					'title'      => sprintf( __( '%1$s (on %2$s)', 'humainbox' ), $name, '' !== $page ? $page : '#' . $post_id ),
					'recipient'  => $this->sends_email( $settings ) ? $to : '',
					'aside'      => $changeable && $second && '' !== trim( $to_2 ) && ! $this->may_be_repointed( $to_2 ),
					'changeable' => $changeable,
					'reason'     => $reason,
					'routing'    => $fixed ? $this->routing( array( $to ) ) : 'none',
					'notes'      => $changeable ? $this->notes( array( $to ), $copies ) : array(),
				);
			}
		}

		return $out;
	}

	public function set_recipient( $form_id, $recipient ) {
		if ( ! $this->is_available() || ! $this->may_save() ) {
			return false;
		}

		list( $post_id, $element_id ) = $this->split_id( $form_id );
		$document                     = $this->document( $post_id );

		if ( null === $document || '' === $element_id ) {
			return false;
		}

		$elements = (array) $document->get_elements_data();
		$forms    = $this->find_forms( $elements );

		if ( ! isset( $forms[ $element_id ] ) || ! $this->repointable( $forms[ $element_id ] ) ) {
			return false;
		}

		$this->change(
			$elements,
			$element_id,
			function ( array &$settings ) use ( $recipient ) {
				$settings['email_to'] = $recipient;
			}
		);

		return (bool) $document->save( array( 'elements' => $elements ) );
	}

	/**
	 * Whether the key existed is part of the snapshot: absent means "the admin
	 * address, whatever it is when the form is sent", and a restore puts that back.
	 *
	 * @param string $form_id Form identifier.
	 * @return array
	 */
	public function snapshot( $form_id ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		list( $post_id, $element_id ) = $this->split_id( $form_id );
		$document                     = $this->document( $post_id );

		if ( null === $document ) {
			return array();
		}

		$forms = $this->find_forms( (array) $document->get_elements_data() );

		if ( ! isset( $forms[ $element_id ] ) ) {
			return array();
		}

		return array(
			'exists' => array_key_exists( 'email_to', $forms[ $element_id ] ),
			'to'     => array_key_exists( 'email_to', $forms[ $element_id ] ) ? (string) $forms[ $element_id ]['email_to'] : '',
		);
	}

	public function apply_snapshot( $form_id, $snapshot ) {
		if ( ! $this->is_available() || ! $this->may_save() || ! isset( $snapshot['exists'] ) ) {
			return false;
		}

		list( $post_id, $element_id ) = $this->split_id( $form_id );
		$document                     = $this->document( $post_id );

		if ( null === $document ) {
			return false;
		}

		$elements = (array) $document->get_elements_data();
		$forms    = $this->find_forms( $elements );

		// A form deleted since is not recreated.
		if ( ! isset( $forms[ $element_id ] ) ) {
			return false;
		}

		// Changed by hand since we pointed it at us: theirs now, not ours to undo.
		if ( ! $this->still_ours( $this->recipient_of( $forms[ $element_id ] ) ) ) {
			return 0;
		}

		$this->change(
			$elements,
			$element_id,
			function ( array &$settings ) use ( $snapshot ) {
				if ( $snapshot['exists'] ) {
					$settings['email_to'] = (string) $snapshot['to'];
				} else {
					unset( $settings['email_to'] );
				}
			}
		);

		return $document->save( array( 'elements' => $elements ) ) ? 1 : false;
	}
}
