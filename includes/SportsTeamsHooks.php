<?php

class SportsTeamsHooks {

	/**
	 * Adds the "favorite team or sport" drop-down menus to the signup page.
	 *
	 * Based on GPL-licensed code from [[mw:Extension:CCAgreement]] by Josef
	 * Martiňák.
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function addSportsTeamsToSignupPage( &$out, &$skin ) {
		$title = $out->getTitle();

		// Only do our magic if we're on the account creation page...
		if ( $title->isSpecial( 'CreateAccount' ) ) {
			$sports = SportsTeams::getSports();
			// ...*and* we have some sports & teams configured
			if ( !empty( $sports ) ) {
				$bodyText = $out->getHTML();

				$output = '<div>
					<label for="sport_1">' .
				wfMessage( 'sportsteams-signup-select' )->escaped() .
			'</label>
				<select name="sport_1" id="sport_1">
					<option value="0">-</option>';

				// Build sport option HTML
				foreach ( $sports as $sport ) {
					$output .= '<option value="' . htmlspecialchars( $sport['id'] ) . '">' . htmlspecialchars( $sport['name'] ) . "</option>\n";
				}

				$output .= '</select>
			</div>
			<div>
				<label for="team_1">' .
					wfMessage( 'sportsteams-signup-team' )->escaped() .
				'</label>
				<select name="team_1" id="team_1">
					<option value="0">-</option>
				</select>
			</div>
			<div>
				<label for="thought">' .
					wfMessage( 'sportsteams-signup-thought' )->escaped() .
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
				$bodyText = preg_replace(
					'/<div class=\"mw-htmlform-field-HTMLSubmitField mw-ui-vform-field\">/',
					$output . '<div class="mw-htmlform-field-HTMLSubmitField mw-ui-vform-field">',
					$bodyText
				);

				// DoubleCombo is needed for populating the team drop-down menu's
				// contents once the user has picked a team
				$out->addModules( 'ext.sportsTeams.doubleCombo' );

				// Output the new HTML
				$out->addHTML( $bodyText );
			}
		}
	}

	/**
	 * If the user chose a sports team from the drop-down menu on the signup
	 * form, add the user to that network, and if they also supplied a thought
	 * about a team, post it.
	 *
	 * @param User $user User object for the created user
	 * @param bool $autocreated Whether this was an auto-creation or not
	 */
	public static function addFavoriteTeam( $user, $autocreated ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		// This code used to read the values of the cookies set in DoubleCombo.js;
		// the cookie index names for the first two variables differ. In JS they
		// are 'sports_sid' and 'sports_tid', but on the PHP side they are 'sport_1'
		// and 'team_1'. 'thought' is always 'thought', though.
		if ( $request->getInt( 'sport_1' ) ) {
			$sport_id = $request->getInt( 'sport_1' );
			$team_id = $request->getInt( 'team_1' );
			$thought = $request->getVal( 'thought' );

			if ( $sport_id != 0 ) {
				$s = new SportsTeams( $user );
				$s->addFavorite( $sport_id, $team_id );

				if ( $thought ) {
					// @todo FIXME: *technically speaking* passing $user
					// to the UserStatus constructor IS wrong, /but/
					// it doesn't matter right now because we're only
					// calling addStatus() here. If we were to call e.g.
					// getStatusMessages(), then it WOULD matter and it
					// 100% WOULD result in a bug.
					$b = new UserStatus( $user );
					$m = $b->addStatus( $sport_id, $team_id, $thought );
				}
			}
		}
	}

	/**
	 * Creates SportsTeams's new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dbExt = '';

		if ( !in_array( $updater->getDB()->getType(), [ 'mysql', 'sqlite' ] ) ) {
			$dbExt = ".{$updater->getDB()->getType()}";
		}

		$updater->addExtensionTable( 'sport', __DIR__ . "/../sql/sportsteams$dbExt.sql" );
		$updater->addExtensionTable( 'sport_favorite', __DIR__ . "/../sql/sportsteams$dbExt.sql" );
		$updater->addExtensionTable( 'sport_team', __DIR__ . "/../sql/sportsteams$dbExt.sql" );
	}

}
