<?php
/**
 * A special page to add new networks and edit existing ones.
 *
 * @file
 * @ingroup Extensions
 */
class SportsTeamsManager extends SpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'SportsTeamsManager', 'sportsteamsmanager' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// If the user isn't allowed to access this page, display an error
		if ( !$user->isAllowed( 'sportsteamsmanager' ) ) {
			throw new PermissionsError( 'sportsteamsmanager' );
		}

		// Show a message if the database is in read-only mode
		$this->checkReadOnly();

		// If the user is blocked, s/he doesn't need to access this page
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Set the page title
		$out->setPageTitle( $this->msg( 'sportsteams-team-manager-title' )->plain() );

		// Add CSS
		$out->addModules( 'ext.sportsTeams.manager' );

 		if ( $request->wasPosted() ) {
			// Handle the creation of a new sport here
			if ( $request->getVal( 'method' ) == 'createsport' ) {
				$st = new SportsTeams();
				$id = $st->addSport( $request->getVal( 'sport_name' ) );
				if ( isset( $id ) && $id > 0 ) {
					$out->addHTML(
						'<span class="view-status">' .
						$this->msg( 'sportsteams-team-manager-sport-created' )->plain() .
						'</span><br /><br />'
					);
				}
				return;
			} elseif ( // also handle the case where someone wants to edit a *sport*
				$request->getVal( 'method' ) == 'editsport' &&
				$request->getVal( 'sport_id' )
			)
			{
				$st = new SportsTeams();
				$id = $st->editSport(
					$request->getVal( 'sport_id' ),
					$request->getVal( 'sport_name' )
				);
				return;
			}

			if ( !( $request->getInt( 'id' ) ) ) {
				$dbw = wfGetDB( DB_MASTER );
				$dbw->insert(
					'sport_team',
					array(
						'team_sport_id' => $request->getInt( 's_id' ),
						'team_name' => $request->getVal( 'team_name' )
					),
					__METHOD__
				);

				$id = $dbw->insertId();
				$out->addHTML(
					'<span class="view-status">' .
					$this->msg( 'sportsteams-team-manager-created' )->text() .
					'</span><br /><br />'
				);
			} else {
				$id = $request->getInt( 'id' );
				$dbw = wfGetDB( DB_MASTER );
				$dbw->update(
					'sport_team',
				/* SET */array(
						'team_sport_id' => $request->getInt( 's_id' ),
						'team_name' => $request->getVal( 'team_name' )
					),
				/* WHERE */array( 'team_id' => $id ),
					__METHOD__
				);

				$out->addHTML(
					'<span class="view-status">' .
					$this->msg( 'sportsteams-team-manager-saved' )->text() .
					'</span><br /><br />'
				);
			}

			$out->addHTML( $this->displayForm( $id ) );
		} else {
			$id = $request->getInt( 'id' );
			$sport_id = $request->getInt( 'sport_id' );

			if ( $id || $request->getVal( 'method' ) == 'edit' ) {
				$out->addHTML( $this->displayForm( $id ) );
			} else {
				if ( !$sport_id ) {
					$out->addHTML( $this->displaySportsList() );
				} elseif (
					$request->getVal( 'method' ) == 'editsport' &&
					$request->getVal( 'sport_id' )
				)
				{
					$out->addHTML(
						$this->displayCreateSportForm(
							$request->getVal( 'sport_id' )
						)
					);
					return;
				} else {
					$out->addHTML(
						'<div><b><a href="' .
						htmlspecialchars( $this->getPageTitle()->getFullURL() ) . '">' .
						$this->msg( 'sportsteams-team-manager-view-sports' )->text() .
						'</a></b> | <b><a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL(
							array( 'sport_id' => $sport_id, 'method' => 'edit' )
						) ) . '">' .
						$this->msg( 'sportsteams-team-manager-add-new-team' )->text() . '</a></b></div><p>'
					);
					$out->addHTML( $this->displayTeamList( $sport_id ) );
				}
			}
		}
	}

	/**
	 * The form for creating a brand new sport (since initially the database is
	 * empty, naturally).
	 *
	 * @param $id Integer: if set, internal sport identifier, otherwise null
	 * @return String: HTML
	 */
	function displayCreateSportForm( $id = null ) {
		$sportNameValue = '';

		// If we're editing a sport that already exists, preload its name into
		// the text field
		if ( isset( $id ) ) {
			$sport = SportsTeams::getSport( $id );
			$sportNameValue = ( isset( $sport['name'] ) ? $sport['name'] : '' );
		}

		$form = '<form action="" method="post" enctype="multipart/form-data" name="sportsteamsmanager">';

		$form .= '<table border="0" cellpadding="5" cellspacing="0" width="500">';

		$form .= '
			<tr>
				<td width="200" class="view-form">' .
					$this->msg( 'sportsteams-team-manager-sport-name' )->plain() .
				'</td>
				<td width="695">
					<input type="text" size="45" class="createbox" name="sport_name" value="' . $sportNameValue .'" />
				</td>
			</tr>
			';
		// The GUI intentionally omits sport_order because I have no clue what
		// it's supposed to be and Aaron & Dave aren't around anymore to answer
		// my questions...

		if ( $id ) {
			$sport_image = SportsTeams::getLogo( $id, false, 'l' );
			$form .= '<tr>
					<td width="200" class="view-form" valign="top">' .
						$this->msg( 'sportsteams-network-alt-logo' )->plain() .
					'</td>
					<td width="695">' . $sport_image . '
						<p>
						<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'SportsManagerLogo' )->getFullURL( "id={$id}" ) ) . '">' .
							$this->msg( 'sportsteams-team-manager-add-replace-logo' )->text() .
						'</a>
					</td>
				</tr>';
		}

		// Different button text (and hidden method, which is used in execute())
		// depending on if we're editing a sport or adding one
		if ( $id ) {
			$msg = $this->msg( 'sportsteams-team-manager-edit' )->plain();
			$method = 'editsport';
		} else {
			$msg = $this->msg( 'sportsteams-team-manager-add-sport-button' )->plain();
			$method = 'createsport';
		}

		$form .= '<tr>
				<td colspan="2">
					<input type="hidden" name="method" value="' . $method . '" />
					<input type="button" class="site-button" value="' . $msg . '" size="20" onclick="document.sportsteamsmanager.submit()" />
					<input type="button" class="site-button" value="' . $this->msg( 'cancel' )->plain() . '" size="20" onclick="history.go(-1)" />
				</td>
			</tr>
		</table>

		</form>';
		return $form;
	}

	function displaySportsList() {
		$output = '<div>';
		$sports = SportsTeams::getSports();

		if ( $sports ) {
			foreach ( $sports as $sport ) {
				$editLink = $this->msg( 'word-separator' )->plain() . '<a href="' .
					htmlspecialchars( $this->getPageTitle()->getFullURL( array(
						'method' => 'editsport',
						'sport_id' => $sport['id']
					) ) ) .
					'" class="red-edit-link">' .
					$this->msg( 'sportsteams-team-manager-edit-this-sport' )->plain() .
					'</a>';
				$output .= '<div class="Item">
				<a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( "sport_id={$sport['id']}" ) ) . "\">{$sport['name']}</a>{$editLink}
			</div>\n";
			}
		} else {
			$output .= $this->msg( 'sportsteams-team-manager-db-is-empty' )->parse();
			$output .= $this->displayCreateSportForm();
		}

		$output .= '</div>';
		return '<div id="views">' . $output . '</div>';
	}

	/**
	 * Display all teams for a given sport (via its internal identifier number).
	 *
	 * @param $sport_id Integer: sport identifier
	 * @return String: HTML
	 */
	function displayTeamList( $sport_id ) {
		$output = '<div>';
		$teams = SportsTeams::getTeams( $sport_id );

		foreach ( $teams as $team ) {
			$output .= '<div class="Item">' .
				Linker::link(
					$this->getPageTitle(),
					$team['name'],
					array(),
					array(
						'method' => 'edit',
						'sport_id' => $sport_id,
						'id' => $team['id']
					)
				) . "</div>\n";
		}

		$output .= '</div>';
		return '<div id="views">' . $output . '</div>';
	}

	function displayForm( $id ) {
		global $wgUploadPath;

		$request = $this->getRequest();

		$form = '<div><b><a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'sport_id=' . $request->getInt( 'sport_id' ) ) ) . '">' .
			$this->msg( 'sportsteams-team-manager-view-teams' )->text() . '</a></b></div><p>';

		if ( $id ) {
			$team = SportsTeams::getTeam( $id );
		} else {
			$team = array( 'id' => '', 'name' => '' ); // prevent notices
		}

		$form .= '<form action="" method="post" enctype="multipart/form-data" name="sportsteamsmanager">';

		$form .= '<table border="0" cellpadding="5" cellspacing="0" width="500">';

		$form .= '

			<tr>
			<td width="200" class="view-form">' . $this->msg( 'sportsteams-team-manager-sport' )->text() . '</td>
			<td width="695">
				<select name="s_id">';
		$sports = SportsTeams::getSports();
		foreach ( $sports as $sport ) {
			$selected = '';
			if (
				$request->getInt( 'sport_id' ) == $sport['id'] ||
				$sport['id'] == $team['sport_id']
			)
			{
				$selected = ' selected';
			}
			$form .= "<option{$selected} value=\"{$sport['id']}\">{$sport['name']}</option>";
		}
		$form .= '</select>

			</tr>
			<tr>
				<td width="200" class="view-form">' .
					$this->msg( 'sportsteams-team-manager-teamname' )->text() .
				'</td>
				<td width="695">
					<input type="text" size="45" class="createbox" name="team_name" value="' . $team['name'] . '" />
				</td>
			</tr>
			';

		if ( $id ) {
			$team_image = "<img src=\"{$wgUploadPath}/team_logos/" .
				SportsTeams::getTeamLogo( $id, 'l' ) .
				'" border="0" alt="logo" />';
			$form .= '<tr>
					<td width="200" class="view-form" valign="top">' .
						$this->msg( 'sportsteams-team-manager-team' )->text() .
					'</td>
					<td width="695">' . $team_image . '
						<p>
						<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'SportsTeamsManagerLogo' )->getFullURL( "id={$id}" ) ) . '">' .
							$this->msg( 'sportsteams-team-manager-add-replace-logo' )->text() .
						'</a>
					</td>
				</tr>';
		}

		if ( $id ) {
			$msg = $this->msg( 'sportsteams-team-manager-edit' )->plain();
		} else {
			$msg = $this->msg( 'sportsteams-team-manager-add-team' )->plain();
		}

		$form .= '<tr>
				<td colspan="2">
					<input type="hidden" name="id" value="' . $id . '" />
					<input type="button" class="site-button" value="' . $msg . '" size="20" onclick="document.sportsteamsmanager.submit()" />
					<input type="button" class="site-button" value="' . $this->msg( 'cancel' )->plain() . '" size="20" onclick="history.go(-1)" />
				</td>
			</tr>
		</table>

		</form>';
		return $form;
	}
}
