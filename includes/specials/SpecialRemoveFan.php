<?php
/**
 * A special page for removing yourself from a fan network.
 *
 * @file
 */
class RemoveFan extends UnlistedSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'RemoveFan' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the special page, if any [unused]
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$output = '';

		/**
		 * Get query string variables
		 */
		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );

		// Add CSS
		$out->addModules( 'ext.sportsTeams' );

		/**
		 * Error message for URL with no team and sport specified
		 */
		if ( !$team_id && !$sport_id ) {
			$out->setPageTitle( $this->msg( 'sportsteams-network-woops-title' )->plain() );
			$output = '<div class="relationship-request-message">' .
				$this->msg( 'sportsteams-network-woops-text' )->escaped() . '</div>';
			$output .= '<div class="relationship-request-buttons">';
			$output .= '<input type="button" class="site-button" value="' .
				$this->msg( 'sportsteams-network-main-page' )->escaped() . "\" onclick=\"window.location='" .
				htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
			if ( $user->isLoggedIn() ) {
				$output .= ' <input type="button" class="site-button" value="' .
					$this->msg( 'sportsteams-network-your-profile' )->escaped() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::makeTitle( NS_USER, $user->getName() )->getFullURL() ) . "'\"/>";
			}
			$output .= '</div>';
			$out->addHTML( $output );
			return false;
		}

		// If the database is in read-only mode, bail out
		$this->checkReadOnly();

		if ( $team_id ) {
			$team = SportsTeams::getTeam( $team_id );
			$name = $team['name'];
		} else {
			$sport = SportsTeams::getSport( $sport_id );
			$name = $sport['name'];
		}

		if ( $request->wasPosted() ) {
			$s = new SportsTeams( $user );
			$s->removeFavorite(
				$request->getVal( 's_id' ),
				$request->getVal( 't_id' )
			);

			$out->setPageTitle( $this->msg( 'sportsteams-network-no-longer-member', $name )->text() );
			$output .= '<div class="give-gift-message">
				<input type="button" class="site-button" value="' .
					$this->msg( 'sportsteams-network-main-page' )->escaped() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>
				<input type=\"button\" class=\"site-button\" value=\"" .
					$this->msg( 'sportsteams-network-your-profile' )->escaped() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::makeTitle( NS_USER, $user->getName() )->getFullURL() ) . "'\"/>
			</div>";
		} else {
			/**
			 * Error message if the user is not a fan
			 */
			if ( !SportsTeams::isFan( $user, $sport_id, $team_id ) == true ) {
				$out->setPageTitle( $this->msg( 'sportsteams-network-not-member', $name )->text() );
				//$output .= '<div class="relationship-request-message">' . $this->msg( 'sportsteams-network-no-need-join' )->escaped() . '</div>';
				$output .= '<div class="relationship-request-buttons">';
				$output .= '<input type="button" class="site-button" value="' .
					$this->msg( 'sportsteams-network-main-page' )->escaped() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
				if ( $user->isLoggedIn() ) {
					$output .= ' <input type="button" class="site-button" value="' .
						$this->msg( 'sportsteams-network-your-profile' )->escaped() .
						"\" onclick=\"window.location='" .
						htmlspecialchars( Title::makeTitle( NS_USER, $user->getName() )->getFullURL() ) . "'\"/>";
				}
				$output .= '</div>';
				$out->addHTML( $output );
				return false;
			}
			$out->setPageTitle( $this->msg( 'sportsteams-network-leave', $name )->text() );

			$output .= '<form action="" method="post" enctype="multipart/form-data" name="form1">

				<div class="give-gift-message" style="margin:0px 0px 0px 0px;">' .
					$this->msg( 'sportsteams-network-leave-are-you-sure', $name )->parse() .
				"</div>

				<div class=\"visualClear\"></div>
				<div class=\"give-gift-buttons\">
					<input type=\"hidden\" name=\"s_id\" value=\"{$sport_id}\" />
					<input type=\"hidden\" name=\"t_id\" value=\"{$team_id}\" />
					<input type=\"button\" class=\"site-button\" value=\"" . $this->msg( 'sportsteams-network-remove-me' )->escaped() . "\" size=\"20\" onclick=\"document.form1.submit()\" />
					<input type=\"button\" class=\"site-button\" value=\"" . $this->msg( 'cancel' )->escaped() . "\" size=\"20\" onclick=\"history.go(-1)\" />
				</div>
			</form>";
		}

		$out->addHTML( $output );
	}
}
