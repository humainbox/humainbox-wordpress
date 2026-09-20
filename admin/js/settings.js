/**
 * The two pieces of behaviour this screen needs, and no more.
 *
 * ── Why a file and not an inline <script>
 *
 * Enqueued, versioned and cacheable, loaded on exactly one screen, and reviewable as
 * a file rather than as a string embedded in PHP. The strings come from PHP through
 * wp_localize_script so they are translatable — a confirm dialog written in English
 * inside a JavaScript file is a dialog that stays English in every language.
 *
 * ⚠️ NOTHING HERE IS SECURITY. The confirm is a courtesy; the capability check and
 * the nonce live on the server, where a user cannot decline them.
 */
( function () {
	'use strict';

	/**
	 * Select-all, per table.
	 *
	 * Scoped to the header's own table rather than the document: the restore table and
	 * each form plugin's table have their own, and one of them ticking all of the
	 * others would be a way to restore forms somebody never looked at.
	 */
	document.querySelectorAll( '.humainbox-select-all' ).forEach( function ( toggle ) {
		toggle.addEventListener( 'change', function () {
			var table = toggle.closest( 'table' );

			if ( ! table ) {
				return;
			}

			table.querySelectorAll( 'tbody .humainbox-form' ).forEach( function ( box ) {
				box.checked = toggle.checked;
			} );
		} );
	} );

	/**
	 * A last look before rewriting live forms.
	 *
	 * This action changes where a working contact form sends its enquiries. It is
	 * reversible — the original address is saved before anything is written — but it
	 * happens on a production site the moment the button is pressed, and the count is
	 * the part somebody most often gets wrong.
	 */
	var form = document.getElementById( 'humainbox-apply-form' );

	if ( ! form || typeof window.humainboxL10n === 'undefined' ) {
		return;
	}

	form.addEventListener( 'submit', function ( event ) {
		var chosen = form.querySelectorAll( '.humainbox-form:checked' ).length;

		if ( 0 === chosen ) {
			event.preventDefault();
			window.alert( window.humainboxL10n.nothingSelected );

			return;
		}

		var question = ( 1 === chosen ? window.humainboxL10n.confirmOne : window.humainboxL10n.confirmMany )
			.replace( '%1$d', chosen )
			.replace( '%2$s', form.dataset.humainboxAddress || '' );

		if ( ! window.confirm( question ) ) {
			event.preventDefault();
		}
	} );
}() );
