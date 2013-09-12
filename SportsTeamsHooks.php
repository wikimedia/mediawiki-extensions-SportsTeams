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
	 * @param $out OutputPage
	 * @param $skin Skin
	 * @return Boolean
	 */
	public static function addSportsTeamsToSignupPage( &$out, &$skin ) {
		$context = $out;
		$title = $context->getTitle();
		$request = $context->getRequest();

		// Only do our magic if we're on the login page
		if ( $title->isSpecial( 'Userlogin' ) ) {
			$kaboom = explode( '/', $title->getText() );
			$signupParamIsSet = false;

			// Catch [[Special:UserLogin/signup]]
			if ( isset( $kaboom[1] ) && $kaboom[1] == 'signup' ) {
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

				$output = '<tr>
			<td class="mw-label"><label for="wpSelectTeamOrSport">' .
				wfMessage( 'sportsteams-signup-select' )->plain() .
			'</label></td>
				<td class="mw-input">
					<select name="sport_1" id="sport_1">
						<option value="0">-</option>';

				// Build sport option HTML
				$sports = SportsTeams::getSports();
				foreach ( $sports as $sport ) {
					$output .= "<option value=\"{$sport['id']}\">{$sport['name']}</option>\n";
				}

				$output .= '</select>
				</td>
			</tr>
			<tr>
			<td class="mw-label"><label for="wpTeam">' .
				wfMessage( 'sportsteams-signup-team' )->plain() .
			'</label></td>
				<td class="mw-input">
					<select name="team_1" id="team_1">
						<option value="0">-</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="mw-label"><label for="wpThought">' .
					wfMessage( 'sportsteams-signup-thought' )->plain() .
				'</label></td>
				<td class="mw-input">
					<!-- <input tabindex="6" class="lr-input" type="text" id="thought" name="thought" /> -->
					<!--
						Standard input looks a tad bit too small, IMO.
						The maximum length is after all 150 characters, not 50 or so, and thus it should be visually obvious.
					-->
					<textarea tabindex="6" class="lr-input" id="thought" name="thought" maxlength="150" style="width: 50%;"></textarea>
				</td>
			</tr>';

				// This is needed to prevent the duplication of the form (:P)
				// and also for injecting our custom HTML into the right place
				$out->clearHTML();

				$bodyText = preg_replace(
					"/<td class=\"mw-submit\">/",
					'<td class="mw-submit" colspan="2">',
					$bodyText
				);

				// Append the sport/team selector to the output
				$bodyText = preg_replace(
					"/(?=[^\"]*\"mw-submit)<tr>/",
					$output . '<tr>',
					$bodyText
				);

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
	 * @param $user User object representing the newly created account-to-be
	 * @return Boolean
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
				$s->addFavorite( $user->getID(), $sport_id, $team_id );

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
	 * @param $updater DatabaseUpdater
	 * @return Boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = dirname( __FILE__ );
		$dbExt = '';

		/*
		if ( !in_array( $updater->getDB()->getType(), array( 'mysql', 'sqlite' ) ) ) {
			$dbExt = ".{$updater->getDB()->getType()}";
		}
		*/

		$updater->addExtensionUpdate( array( 'addTable', 'sport', "$dir/sportsteams$dbExt.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'sport_favorite', "$dir/sportsteams$dbExt.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'sport_team', "$dir/sportsteams$dbExt.sql", true ) );

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param $renameUserSQL RenameuserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['sport_favorite'] = array( 'sf_user_name', 'sf_user_id' );
		return true;
	}
}