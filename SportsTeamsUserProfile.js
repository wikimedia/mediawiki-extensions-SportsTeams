var SportsTeamsUserProfile = {
	posted: 0,
	lastBox: '',

	/**
	 * This is called when you click on the "Do you agree?" link on someone
	 * else's user profile's status update message
	 */
	voteStatus: function( id, vote ) {
		( new mw.Api() ).postWithToken( 'edit', {
			action: 'userstatus',
			what: 'votestatus',
			us_id: id,
			vote: vote,
			format: 'json'
		} ).done( function( data ) {
			SportsTeamsUserProfile.posted = 0;
			var $node = $( '#status-update a.profile-vote-status-link' );
			// Add the "1 Person Agrees"/"X People Agree" text after the date
			$( '<span>' + data.userstatus.result + '</span>' ).insertAfter( $( 'span.user-status-date:first' ) );
			// and hide the "Do you agree?" link
			$node.hide();
		} );
	},

	/**
	 * Detect whether the user pressed the Enter key and in that case, post
	 * the message.
	 *
	 * @param {Event}
	 * @param {Number}
	 * @param {Number} Sport identifier
	 * @param {Number} Team identifier
	 * @return Boolean
	 */
	detEnter: function( e, num, sport_id, team_id ) {
		var keycode;

		if ( window.event ) {
			keycode = window.event.keyCode;
		} else if ( e ) {
			keycode = e.which;
		} else {
			return true;
		}

		if ( keycode == 13 ) {
			SportsTeamsUserProfile.addMessage( num, sport_id, team_id );
			return false;
		} else {
			return true;
		}
	},

	closeMessageBox: function( num ) {
		$( '#status-update-box-' + num ).hide( 1000 );
	},

	/**
	 * Show the box for adding a status message from the user profile.
	 */
	showMessageBox: function( num, sport_id, team_id ) {
		if ( SportsTeamsUserProfile.lastBox ) {
			$( '#status-update-box-' + SportsTeamsUserProfile.lastBox ).hide( 2000 );
		}

		var addMsg = mw.msg( 'sportsteams-profile-button-add' ),
			cancelMsg = mw.msg( 'sportsteams-profile-button-cancel' ),
			statusInput, addButton, closeButton, spacing;

		spacing = ' ';

		statusInput = $( '<input/>' ).attr( {
			type: 'text',
			id: 'status_text',
			value: '',
			maxlength: 150
		} ).on( 'keypress', function( event ) {
			SportsTeamsUserProfile.detEnter( event, num, sport_id, team_id );
		} ).on( 'keyup', function() {
			SportsTeamsUserProfile.limitText();
		} );

		addButton = $( '<input/>' ).attr( {
			type: 'button',
			'class': 'site-button',
			value: addMsg
		} ).on( 'click', function( event ) {
			SportsTeamsUserProfile.addMessage( num, sport_id, team_id );
		} );

		closeButton = $( '<input/>' ).attr( {
			type: 'button',
			'class': 'site-button',
			value: cancelMsg
		} ).on( 'click', function( event ) {
			SportsTeamsUserProfile.closeMessageBox( num );
		} );

		var br = '<br />',
			counter = '<span id="status-letter-count"></span>';
		// EDGE CASE WARNING!
		// This check, as well as the one below it, are here to prevent strange
		// things from happening when a user clicks on the "add thought" link
		// on their profile more than once. Without these checks, the text
		// input as well as the "add"/"cancel" buttons would be repeteadly added
		// to the DOM and clicking "add thought" again would not make anything
		// go away.
		if ( num !== SportsTeamsUserProfile.lastBox ) {
			$( '#status-update-box-' + num ).append(
				statusInput, spacing, addButton, spacing, closeButton, br, counter
			);
		}

		if ( $( '#status-update-box-' + num ).is( ':visible' ) ) {
			$( '#status-update-box-' + num ).hide( 1000 );
		} else {
			$( '#status-update-box-' + num ).show( 1000 );
		}

		SportsTeamsUserProfile.lastBox = num;
	},

	/**
	 * Show the "X characters left" message when the user is typing a status
	 * update.
	 */
	limitText: function() {
		var len = 150 - document.getElementById( 'status_text' ).value.length;
		if ( len < 0 ) {
			var statusText = document.getElementById( 'status_text' );
			document.getElementById( 'status_text' ).value = statusText.value.slice( 0, 150 );
			len++;
		}
		$( '#status-letter-count' ).html(
			// In an ideal world, this would work (and the manual erroneously
			// claims that it does):
			//mw.message( 'sportsteams-profile-characters-remaining', len ).text()
			// But it doesn't. It shows the raw PLURAL and all, the only thing
			// that works is the character counting. So, hacks it is, then!
			mw.msg( 'sportsteams-profile-characters-remaining-hack', len )
		);
	},

	/**
	 * Add a status message from the user profile.
	 */
	addMessage: function( num, sport_id, team_id ) {
		var statusUpdateText = document.getElementById( 'status_text' ).value;
		if ( statusUpdateText && !SportsTeamsUserProfile.posted ) {
			SportsTeamsUserProfile.posted = 1;
			$( '#status-update' ).hide();

			( new mw.Api() ).postWithToken( 'edit', {
				action: 'userstatus',
				what: 'addstatus',
				sportId: sport_id,
				teamId: team_id,
				text: encodeURIComponent( statusUpdateText ),
				count: 10,
				format: 'json'
			} ).done( function( data ) {
				SportsTeamsUserProfile.posted = 0;

				if ( document.getElementById( 'status-update' ) === null ) {
					var theDiv2 = document.createElement( 'div' );
					$( theDiv2 ).addClass( 'status-container' );
					theDiv2.setAttribute( 'id', 'status-update' );
					$( theDiv2 ).insertBefore( $( '#user-page-left:first' ) );

					var theDiv = document.createElement( 'div' );
					$( theDiv ).addClass( 'user-section-heading' );
					theDiv.innerHTML = '<div class="user-section-title">' +
						mw.msg( 'sportsteams-profile-latest-thought' ) + '</div>';
					theDiv.innerHTML += '<div class="user-section-action"><a href="' +
						__more_thoughts_url__ + '" rel="nofollow">' +
						mw.msg( 'sportsteams-profile-view-all' ) + '</a></div>';
					$( theDiv ).insertBefore( $( '#user-page-left:first' ) );
				}

				$( '#status-update' ).html( data.userstatus.result ).show();

				SportsTeamsUserProfile.closeMessageBox( num );
			} );
		}
	}
};

$( document ).ready( function() {
	// "Add thought" link on your own profile
	$( 'span.status-message-add a' ).on( 'click', function() {
		var $that = $( this );
		SportsTeamsUserProfile.showMessageBox(
			$that.data( 'order' ),
			$that.data( 'sport-id' ),
			$that.data( 'team-id' )
		);
	} );

	// "Agree" links on other users' profiles
	$( 'a.profile-vote-status-link' ).on( 'click', function() {
		SportsTeamsUserProfile.voteStatus( $( this ).data( 'status-update-id' ), 1 );
	} );
} );