/**
 * DoubleCombo is the system responsible for populating one dropdown menu with
 * data after an another one has been changed (the JS "change" event has been
 * triggered).
 *
 * This is used on Special:UpdateFavoriteTeams to populate the initially empty
 * "Team" dropdown menu after the user has picked a sport from the "Sport"
 * dropdown menu.
 */
var DoubleCombo = {
	/**
	 * @param {String} Identifier of the <select> element to be populated w/ data
	 * @param {Number} Sport identifier, used when calling the backend API module
	 */
	update: function( elementId, sportId ) {
		$.get(
			mw.util.wikiScript( 'api' ), {
				action: 'sportsteams',
				'sportId': sportId,
				format: 'json'
			},
			function( data ) {
				// @todo FIXME: stupid check, data is always defined, maybe
				// check for data.error (error message) -> if it's present,
				// display the error text in an alert(), else build the opts
				if ( data ) {
					var opts = '';
					document.getElementById( elementId ).options.length = 0;
					opts = data.sportsteams.result;

					$( '#' + elementId ).append(
						$( '<option></option>' )
							.attr( 'value', 0 )
							.text( '-' )
					);

					for ( var x = 0; x <= opts.options.length - 1; x++ ) {
						$( '#' + elementId ).append(
							$( '<option></option>' )
								.attr( 'id', opts.options[x]['id'] )
								.attr( 'value', opts.options[x]['id'] )
								.text( opts.options[x]['name'] )
						);
					}
				} else {
					alert( 'Error in DoubleCombo.js, DoubleCombo.update: AJAX request returned no data?!' );
				}
			}
		);
	}
};

$( document ).ready( function() {
	$( 'p.profile-update-unit-right select' ).on( 'change', function() {
		var counter = $( this ).attr( 'id' ).replace( /sport_/, '' );
		// if the <option>'s value is 0 (i.e. the displayed text is "-"), don't
		// even try updating things as it'll just die horribly with some obscure
		// JS error about result being undefined
		if ( $( this ).val() > 0 ) {
			DoubleCombo.update( 'team_' + counter, $( this ).val() );
		}
	} );

	// Signup page stuff (Special:UserLogin/signup on MW 1.26 and older,
	// Special:CreateAccount on MW 1.27+)
	if (
		mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Userlogin' ||
		mw.config.get( 'wgCanonicalSpecialPageName' ) === 'CreateAccount'
	)
	{
		$( 'select#sport_1' ).on( 'change', function() {
			var $val = $( this ).val();
			document.cookie = 'sports_sid=' + $val;
			DoubleCombo.update( 'team_1', $val );
		} );

		$( 'select#team_1' ).on( 'change', function() {
			var $val = $( this ).val();
			document.cookie = 'sports_tid=' + $val;
		} );

		$( '#thought' ).on( 'change', function() {
			var $val = $( this ).val();
			document.cookie = 'thought=' + $val;
		} );
	}
} );