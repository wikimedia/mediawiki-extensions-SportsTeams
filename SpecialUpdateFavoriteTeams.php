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

	var $favorite_counter = 1;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'UpdateFavoriteTeams' );
	}

	function getFavorites() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'sport_favorite',
			array( 'sf_sport_id', 'sf_team_id' ),
			array( 'sf_user_id' => $this->getUser()->getId() ),
			__METHOD__,
			array( 'ORDER BY' => 'sf_order' )
		);

		$favorites = array();

		foreach ( $res as $row ) {
			$favorites[] = array(
				'sport_id' => $row->sf_sport_id,
				'team_id' => $row->sf_team_id
			);
		}

		return $favorites;
	}

	function getSportsDropdown( $selected_sport_id = 0, $selected_team_id = 0 ) {
		global $wgExtensionAssetsPath;

		// Set surrent sport dropdown - show first one, or saved team
		if ( $this->favorite_counter == 1 || $selected_sport_id > 0 ) {
			$style = 'display: block;';
		} else {
			$style = 'display: none;';
		}

		$output = '';

		$remove_link = '';
		if ( $selected_sport_id || $selected_team_id ) {
			$remove_link = "<a href=\"javascript:void(0)\" class=\"remove-link\" data-selected-sport-id=\"{$selected_sport_id}\" data-selected-team-id=\"{$selected_team_id}\">
				<img src=\"{$wgExtensionAssetsPath}/SportsTeams/closeIcon.gif\" border=\"0\"/>
			</a>";
		}

		$output .= "<div id=\"fav_{$this->favorite_counter}\" style=\"{$style};padding-bottom: 15px;\">
			<p class=\"profile-update-title\">" .
				$this->msg(
					'sportsteams-updatefavoriteteams-favorite',
					$this->favorite_counter
				)->parse() . " {$remove_link}</p>
				<p class=\"profile-update-unit-left\"> " .
					$this->msg( 'user-profile-sports-sport' )->text() .
				" </p>
				<p class=\"profile-update-unit-right\">
				<select name=\"sport_{$this->favorite_counter}\" id=\"sport_{$this->favorite_counter}\">
					<option value=\"0\">-</option>";

		// Build Sport Option HTML
		$sports = SportsTeams::getSports();
		foreach ( $sports as $sport ) {
			$output .= "<option value=\"{$sport['id']}\"" .
				( ( $sport['id'] == $selected_sport_id ) ? ' selected' : '' ) .
				">{$sport['name']}</option>\n";
		}
		$output .= '</select>';
		$output .= '</p>
			<div class="cleared"></div>';

		// If loading previously saved teams, we need to build the options for
		// the associated sport to show the team they already have selected
		$team_opts = '';
		$teams = array();

		if ( $selected_team_id > 0 ) {
			$teams = SportsTeams::getTeams( $selected_sport_id );
		}

		foreach ( $teams as $team ) {
			$team_opts.= "<option value=\"{$team['id']}\"" .
				( ( $team['id'] == $selected_team_id ) ? ' selected' : '' ) .
				">{$team['name']}</option>";
		}

		$output .= '<p class="profile-update-unit-left">' .
			$this->msg( 'sportsteams-updatefavoriteteams-team' )->text() . "</p>
				<p class=\"profile-update-unit\">
				<select name=\"team_{$this->favorite_counter}\" id=\"team_{$this->favorite_counter}\">
					{$team_opts}
				</select>
			    </p>
				<div class=\"cleared\"></div>

			</div>";

		$this->favorite_counter++;

		return $output;
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		global $wgExtensionsAssetsPath;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// This is like core Special:Preferences, so you need to be logged in
		// to use this special page
		if ( !$user->isLoggedIn() ) {
			$out->setPageTitle( $this->msg( 'user-profile-sports-notloggedintitle' )->text() );
			$out->addHTML( $this->msg( 'user-profile-sports-notloggedintitle' )->text() );
			return;
		}

		// If the database is in read-only mode, bail out
		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			return true;
		}

		$sports = SportsTeams::getSports();
		// Error message when there are no sports in the database
		if ( empty( $sports ) ) {
			$out->setPageTitle( $this->msg( 'sportsteams-error-no-sports-title' )->plain() );
			$out->addWikiMsg( 'sportsteams-error-no-sports-message' );
			return;
		}

		// Set the page title
		$out->setPageTitle( $this->msg( 'user-profile-sports-title' )->plain() );

		// Add CSS (from SocialProfile), DoubleCombo.js and UpdateFavoriteTeams.js files to the page output
		$out->addModules( array(
			'ext.socialprofile.userprofile.css',
			'ext.sportsTeams.updateFavoriteTeams'
		) );

		// This is annoying so I took it out for now.
		//$output = '<h1>' . $this->msg( 'user-profile-sports-title' )->text() . '</h1>';

		// Build the top navigation tabs
		// @todo CHECKME: there should be a UserProfile method for building all
		// this, I think
		$output = '<div class="profile-tab-bar">';
		$output .= '<div class="profile-tab">';
		$output .= '<a href="' . SpecialPage::getTitleFor( 'UpdateProfile', 'basic' )->escapeFullURL() . '">' .
			$this->msg( 'user-profile-section-personal' )->text() . '</a>';
		$output .= '</div>';
		$output .= '<div class="profile-tab-on">';
		$output .= $this->msg( 'user-profile-section-sportsteams' )->text();
		$output .= '</div>';
		$output .= '<div class="profile-tab">';
		$output .= '<a href="' . SpecialPage::getTitleFor( 'UpdateProfile', 'custom' )->escapeFullURL() . '">' .
			/*$this->msg( 'user-profile-section-sportstidbits' )->text()*/$this->msg( 'custom-info-title' )->text() . '</a>';
		$output .= '</div>';
		$output .= '<div class="profile-tab">';
		$output .= '<a href="' . SpecialPage::getTitleFor( 'UpdateProfile', 'personal' )->escapeFullURL() . '">' .
			$this->msg( 'user-profile-section-interests' )->text() . '</a>';
		$output .= '</div>';
		$output .= '<div class="profile-tab">';
		$output .= '<a href="' . SpecialPage::getTitleFor( 'UploadAvatar' )->escapeFullURL() . '">' .
			$this->msg( 'user-profile-section-picture' )->text() . '</a>';
		$output .= '</div>';
		$output .= '<div class="profile-tab">';
		$output .= '<a href="' . SpecialPage::getTitleFor( 'UpdateProfile', 'preferences' )->escapeFullURL() . '">' .
			$this->msg( 'user-profile-section-preferences' )->text() . '</a>';
		$output .= '</div>';

		$output .= '<div class="cleared"></div>';
		$output .= '</div>';

		$output .= '<div class="profile-info">';

		// If the request was POSTed, add/delete teams accordingly
		if ( $request->wasPosted() ) {
			if ( $request->getVal( 'action' ) == 'delete' ) {
				SportsTeams::removeFavorite(
					$user->getId(),
					$request->getVal( 's_id' ),
					$request->getVal( 't_id' )
				);
				SportsTeams::clearUserCache( $user->getId() );
				$out->addHTML(
					'<br /><br /><span class="profile-on">' .
						$this->msg( 'user-profile-sports-teamremoved' )->text() .
					'</span><br /><br />'
				);
			}

			if ( $request->getVal( 'favorites' ) ) {
				// Clear user cache
				SportsTeams::clearUserCache( $user->getId() );

				$dbw = wfGetDB( DB_MASTER );
				// Reset old favorites
				$res = $dbw->delete(
					'sport_favorite',
					array( 'sf_user_id' => $user->getId() ),
					__METHOD__
				);

				$items = explode( '|', $request->getVal( 'favorites' ) );
				foreach ( $items as $favorite ) {
					if ( $favorite ) {
						$atts = explode( ',', $favorite );
						$sport_id = $atts[0];
						$team_id = $atts[1];

						if ( !$team_id ) {
							$team_id = 0;
						}
						$s = new SportsTeams();
						$s->addFavorite( $user->getId(), $sport_id, $team_id );
					}
				}
				$out->addHTML(
					'<br /><br /><span class="profile-on">' .
						$this->msg( 'user-profile-sports-teamsaved' )->text() .
					'</span><br /><br />'
				);
			}
		}

		$favorites = $this->getFavorites();
		foreach ( $favorites as $favorite ) {
			$output .= $this->getSportsDropdown(
				$favorite['sport_id'],
				$favorite['team_id']
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
				$this->msg( 'user-profile-sports-addmore' )->plain() . '" />';
		}

		$output .= '<input type="button" class="profile-update-button" value="' .
			$this->msg( 'user-profile-update-button' )->plain() . '" id="update-favorite-teams-save-button" />
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