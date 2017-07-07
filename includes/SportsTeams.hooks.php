<?php
/**
 * @file
 */
class SportsTeamsHooks {

	/**
	 * Adds the "favorite team or sport" drop-down menus to the signup page.
	 *
	 * Ideally this should be hooked into the UserCreateForm hook, except that
	 * much like Special:UserLogin and its underlying code, said hook sucks
	 * way too much.
	 *
	 * Based on GPL-licensed code from [[mw:Extension:CCAgreement]] by Josef
	 * Martiňák.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function addSportsTeamsToSignupPage( &$out, &$skin ) {
		$context = $out;
		$title = $context->getTitle();
		$request = $context->getRequest();

		$signupParamIsSet = false;

		// Only do our magic if we're on the login page
		if ( $title->isSpecial( 'Userlogin' ) || $title->isSpecial( 'CreateAccount' ) ) {
			if ( $title->isSpecial( 'Userlogin' ) ) {
				$kaboom = explode( '/', $title->getText() );

				// Catch [[Special:UserLogin/signup]]
				if ( isset( $kaboom[1] ) && $kaboom[1] == 'signup' ) {
					$signupParamIsSet = true;
				}
			} else {
				// We're on Special:CreateAccount (MW 1.27 and newer only)
				$signupParamIsSet = true;
			}

			// Both index.php?title=Special:UserLogin&type=signup and
			// Special:UserLogin/signup are valid, obviously
			if (
				$request->getVal( 'type' ) == 'signup' ||
				$signupParamIsSet
			)
			{
				$bodyText = $out->getHTML();

				$output = '<div>
					<label for="sport_1">' .
				wfMessage( 'sportsteams-signup-select' )->plain() .
			'</label>
				<select name="sport_1" id="sport_1">
					<option value="0">-</option>';

				// Build sport option HTML
				$sports = SportsTeams::getSports();
				foreach ( $sports as $sport ) {
					$output .= "<option value=\"{$sport['id']}\">{$sport['name']}</option>\n";
				}

				$output .= '</select>
			</div>
			<div>
				<label for="team_1">' .
					wfMessage( 'sportsteams-signup-team' )->plain() .
				'</label>
				<select name="team_1" id="team_1">
					<option value="0">-</option>
				</select>
			</div>
			<div>
				<label for="thought">' .
					wfMessage( 'sportsteams-signup-thought' )->plain() .
				'</label>
				<!-- <input tabindex="6" class="lr-input" type="text" id="thought" name="thought" /> -->
				<!--
					Standard input looks a tad bit too small, IMO.
					The maximum length is after all 150 characters, not 50 or so, and thus it should be visually obvious.
				-->
				<textarea tabindex="6" class="lr-input" id="thought" name="thought" maxlength="150" style="width: 150%; height: 80px;"></textarea>
			</div>';
				// This is needed to prevent the duplication of the form (:P)
				// and also for injecting our custom HTML into the right place
				$out->clearHTML();

				// Append the sport/team selector to the output
				if ( $title->isSpecial( 'CreateAccount' ) ) {
					$bodyText = preg_replace(
						'/<div class=\"mw-htmlform-field-HTMLSubmitField mw-ui-vform-field\">/',
						$output . '<div class="mw-htmlform-field-HTMLSubmitField mw-ui-vform-field">',
						$bodyText
					);
				} else {
					$bodyText = preg_replace(
						'/<div class=\"mw-submit\">/',
						$output . '<div class="mw-submit">',
						$bodyText
					);
				}

				// DoubleCombo is needed for populating the team drop-down menu's
				// contents once the user has picked a team
				$out->addModules( 'ext.sportsTeams.doubleCombo' );

				// Output the new HTML
				$out->addHTML( $bodyText );
			}
		}

		return true;
	}

	/**
	 * If the user chose a sports team from the drop-down menu on the signup
	 * form, add the user to that network, and if they also supplied a thought
	 * about a team, post it.
	 *
	 * @param User $user User object representing the newly created account-to-be
	 * @return bool
	 */
	public static function addFavoriteTeam( $user ) {
		if ( isset( $_COOKIE['sports_sid'] ) ) {
			$sport_id = $_COOKIE['sports_sid'];
			$team_id = $_COOKIE['sports_tid'];
			$thought = $_COOKIE['thought'];

			if ( !$team_id ) {
				$team_id = 0;
			}

			if ( $sport_id != 0 ) {
				$s = new SportsTeams();
				$s->addFavorite( $user->getId(), $sport_id, $team_id );

				if ( $thought ) {
					$b = new UserStatus();
					$m = $b->addStatus( $sport_id, $team_id, $thought );
				}
			}
		}

		return true;
	}

	/**
	 * Creates SportsTeams's new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dbExt = '';

		/*
		if ( !in_array( $updater->getDB()->getType(), array( 'mysql', 'sqlite' ) ) ) {
			$dbExt = ".{$updater->getDB()->getType()}";
		}
		*/

		$updater->addExtensionUpdate( array( 'addTable', 'sport', __DIR__ . "/../sql/sportsteams$dbExt.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'sport_favorite', __DIR__ . "/../sql/sportsteams$dbExt.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'sport_team', __DIR__ . "/../sql/sportsteams$dbExt.sql", true ) );

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['sport_favorite'] = array( 'sf_user_name', 'sf_user_id' );
		return true;
	}
}
