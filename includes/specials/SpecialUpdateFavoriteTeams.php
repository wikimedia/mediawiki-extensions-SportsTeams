<?php
/**
 * The messages used by this special page are provided by SocialProfile's
 * UserProfile, since this file used to be originally a part of UserProfile and
 * the messages in question weren't taken out when SocialProfile was released.
 * I should probably move 'em to SportsTeams' i18n file one day.
 *
 * @file
 * @version r25
 */
class UpdateFavoriteTeams extends UnlistedSpecialPage {

	public $favorite_counter = 1;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'UpdateFavoriteTeams' );
	}

	private function getFavorites() {
		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			'sport_favorite',
			[ 'sf_sport_id', 'sf_team_id' ],
			[ 'sf_actor' => $this->getUser()->getActorId() ],
			__METHOD__,
			[ 'ORDER BY' => 'sf_order' ]
		);

		$favorites = [];

		foreach ( $res as $row ) {
			$favorites[] = [
				'sport_id' => $row->sf_sport_id,
				'team_id' => $row->sf_team_id
			];
		}

		return $favorites;
	}

	private function getSportsDropdown( $selected_sport_id = 0, $selected_team_id = 0 ) {
		global $wgExtensionAssetsPath;

		$favCount = (int)$this->favorite_counter;
		// Set surrent sport dropdown - show first one, or saved team
		if ( $favCount == 1 || $selected_sport_id > 0 ) {
			$style = 'display: block;';
		} else {
			$style = 'display: none;';
		}

		$output = '';

		$remove_link = '';
		if ( $selected_sport_id || $selected_team_id ) {
			$remove_link = "<a href=\"javascript:void(0)\" class=\"remove-link\" data-selected-sport-id=\"{$selected_sport_id}\" data-selected-team-id=\"{$selected_team_id}\">
				<img src=\"{$wgExtensionAssetsPath}/SportsTeams/resources/images/closeIcon.gif\" border=\"0\"/>
			</a>";
		}

		$output .= "<div id=\"fav_{$favCount}\" style=\"{$style};padding-bottom: 15px;\">
			<p class=\"profile-update-title\">" .
				$this->msg(
					'sportsteams-updatefavoriteteams-favorite',
					$favCount
				)->parse() . " {$remove_link}</p>
				<p class=\"profile-update-unit-left\"> " .
					$this->msg( 'user-profile-sports-sport' )->escaped() .
				" </p>
				<p class=\"profile-update-unit-right\">
				<select name=\"sport_{$favCount}\" id=\"sport_{$favCount}\">
					<option value=\"0\">-</option>";

		// Build Sport Option HTML
		$sports = SportsTeams::getSports();
		foreach ( $sports as $sport ) {
			$output .= Xml::option(
				$sport['name'],
				$sport['id'],
				( $sport['id'] == $selected_sport_id )
			);
		}
		$output .= '</select>';
		$output .= '</p>
			<div class="visualClear"></div>';

		// If loading previously saved teams, we need to build the options for
		// the associated sport to show the team they already have selected
		$team_opts = '';
		$teams = [];

		if ( $selected_team_id > 0 ) {
			$teams = SportsTeams::getTeams( $selected_sport_id );
		}

		foreach ( $teams as $team ) {
			$team_opts .= Xml::option(
				$team['name'],
				$team['id'],
				( $team['id'] == $selected_team_id )
			);
		}

		$output .= '<p class="profile-update-unit-left">' .
			$this->msg( 'sportsteams-updatefavoriteteams-team' )->escaped() . "</p>
				<p class=\"profile-update-unit\">
				<select name=\"team_{$favCount}\" id=\"team_{$favCount}\">
					{$team_opts}
				</select>
				</p>
				<div class=\"visualClear\"></div>

			</div>";

		$this->favorite_counter++;

		return $output;
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the special page, if any
	 */
	public function execute( $par ) {
		global $wgExtensionsAssetsPath;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// This is like core Special:Preferences, so you need to be logged in
		// to use this special page
		if ( !$user->isRegistered() ) {
			$out->setPageTitle( $this->msg( 'user-profile-sports-notloggedintitle' ) );
			$out->addHTML( $this->msg( 'user-profile-sports-notloggedintext' )->escaped() );
			return;
		}

		// If the database is in read-only mode, bail out
		$this->checkReadOnly();

		$sports = SportsTeams::getSports();
		// Error message when there are no sports in the database
		if ( empty( $sports ) ) {
			$out->setPageTitle( $this->msg( 'sportsteams-error-no-sports-title' ) );
			$out->addWikiMsg( 'sportsteams-error-no-sports-message' );
			return;
		}

		// Set the page title
		$out->setPageTitle( $this->msg( 'user-profile-sports-title' ) );

		// Add CSS (from SocialProfile), DoubleCombo.js and UpdateFavoriteTeams.js files to the page output
		$out->addModuleStyles( [
			'ext.socialprofile.userprofile.tabs.css',
			'ext.socialprofile.special.updateprofile.css',
		] );
		$out->addModules( 'ext.sportsTeams.updateFavoriteTeams' );

		// This is annoying so I took it out for now.
		//$output = '<h1>' . $this->msg( 'user-profile-sports-title' )->escaped() . '</h1>';

		// Build the top navigation tabs
		// @todo FIXME/CHECKME: This requires site admins to manually edit [[MediaWiki:Update_profile_nav]]
		// to add something like * Special:UpdateFavoriteTeams|user-profile-section-sportsteams there
		// and that's not exactly ideal
		$output = UserProfile::getEditProfileNav( $this->msg( 'user-profile-section-sportsteams' )->escaped() );

		$output .= '<div class="profile-info">';

		// If the request was POSTed, add/delete teams accordingly
		if ( $request->wasPosted() ) {
			$s = new SportsTeams( $user );

			if ( $request->getVal( 'action' ) == 'delete' ) {
				$s->removeFavorite(
					$request->getInt( 's_id' ),
					$request->getInt( 't_id' )
				);
				SportsTeams::clearUserCache( $user );
				$out->addHTML(
					'<br /><br /><span class="profile-on">' .
						$this->msg( 'user-profile-sports-teamremoved' )->escaped() .
					'</span><br /><br />'
				);
			}

			if ( $request->getVal( 'favorites' ) ) {
				// Clear user cache
				SportsTeams::clearUserCache( $user );

				$dbw = wfGetDB( DB_MASTER );
				// Reset old favorites
				$res = $dbw->delete(
					'sport_favorite',
					[ 'sf_actor' => $user->getActorId() ],
					__METHOD__
				);

				$items = explode( '|', $request->getVal( 'favorites' ) );
				foreach ( $items as $favorite ) {
					if ( $favorite ) {
						$atts = explode( ',', $favorite );
						$sport_id = (int)$atts[0];
						$team_id = (int)$atts[1];

						if ( !$team_id ) {
							$team_id = 0;
						}
						// Assuming you have chosen one favorite sport + team, DoubleCombo JS will
						// show the drop-down for favorite #2, but the values will initially be "-"
						// (empty) for both sport and team drop-downs. If you don't touch either,
						// they will still exist in the WebRequest object and if we don't explicitly
						// check for the existence of a non-zero sport ID, we'd end up saving one
						// bogus entry into the DB which would have both sf_sport_id _and_ sf_team_id = 0.
						if ( $sport_id > 0 ) {
							$s->addFavorite( $sport_id, $team_id );
						}
					}
				}
				$out->addHTML(
					'<br /><br /><span class="profile-on">' .
						$this->msg( 'user-profile-sports-teamsaved' )->escaped() .
					'</span><br /><br />'
				);
			}
		}

		$favorites = $this->getFavorites();
		foreach ( $favorites as $favorite ) {
			$output .= $this->getSportsDropdown(
				htmlspecialchars( $favorite['sport_id'] ),
				htmlspecialchars( $favorite['team_id'] )
			);
		}

		$output .= '<div>';
		if ( count( $favorites ) > 0 ) {
			$output .= '<div style="display: block" id="add_more"></div>';
		}

		for ( $x = 0; $x <= ( 20 - count( $favorites ) ); $x++ ) {
			$output .= $this->getSportsDropdown();
		}

		$output .= '<form action="" name="sports" method="post">
			<input type="hidden" value="" name="favorites" />
			<input type="hidden" value="save" name="action" />';

		if ( count( $favorites ) > 0 ) {
			$output .= '<input type="button" class="profile-update-button" id="update-favorite-teams-add-more-button" value="' .
				$this->msg( 'user-profile-sports-addmore' )->escaped() . '" />';
		}

		$output .= '<input type="button" class="profile-update-button" value="' .
			$this->msg( 'user-profile-update-button' )->escaped() . '" id="update-favorite-teams-save-button" />
			</form>
			<form action="" name="sports_remove" method="post">
				<input type="hidden" value="delete" name="action" />
				<input type="hidden" value="" name="s_id" />
				<input type="hidden" value="" name="t_id" />
			</form>
			<!--
				Epic hack time! Here used to be some inline JS to set UpdateFavoriteTeams.fav_count but as of
				MediaWiki 1.17 (first ResourceLoader MW), we can\'t do that so instead we have to resort to this
				ugly hack here.
			-->
			<div id="fav_count" style="display:none;">' . ( ( count( $favorites ) ) ? count( $favorites ) : 1 ) .'</div>
			</div>
		</div>';

		$out->addHTML( $output );
	}
}
