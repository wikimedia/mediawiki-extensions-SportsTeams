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
	 * @param string|null $par Parameter passed to the special page, if any [unused]
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
		$block = $user->getBlock();
		if ( $block ) {
			throw new UserBlockedError( $block );
		}

		// Set the page title
		$out->setPageTitle( $this->msg( 'sportsteams-team-manager-title' ) );

		// Add CSS
		$out->addModuleStyles( 'ext.sportsTeams.manager' );

 		if ( $request->wasPosted() ) {
			// Security (anti-CSRF check) first!
			if ( !$user->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->addWikiMsg( 'sessionfailure' );
				$out->addReturnTo( $this->getPageTitle() );
				return;
			}

			// Handle the creation of a new sport here
			if ( $request->getVal( 'method' ) == 'createsport' ) {
				$st = new SportsTeams( $user );
				$id = $st->addSport( $request->getVal( 'sport_name' ) );
				if ( isset( $id ) && $id > 0 ) {
					$out->addHTML(
						'<span class="view-status">' .
						$this->msg( 'sportsteams-team-manager-sport-created' )->escaped() .
						'</span><br /><br />'
					);
				}
				return;
			} elseif ( // also handle the case where someone wants to edit a *sport*
				$request->getVal( 'method' ) == 'editsport' &&
				$request->getVal( 'sport_id' )
			)
			{
				$st = new SportsTeams( $user );
				$id = $st->editSport(
					$request->getInt( 'sport_id' ),
					$request->getVal( 'sport_name' )
				);
				return;
			}

			if ( !( $request->getInt( 'id' ) ) ) {
				$dbw = wfGetDB( DB_MASTER );
				$dbw->insert(
					'sport_team',
					[
						'team_sport_id' => $request->getInt( 's_id' ),
						'team_name' => $request->getVal( 'team_name' )
					],
					__METHOD__
				);

				$id = $dbw->insertId();
				$out->addHTML(
					'<span class="view-status">' .
					$this->msg( 'sportsteams-team-manager-created' )->escaped() .
					'</span><br /><br />'
				);
			} else {
				$id = $request->getInt( 'id' );
				$dbw = wfGetDB( DB_MASTER );
				$dbw->update(
					'sport_team',
					[
						'team_sport_id' => $request->getInt( 's_id' ),
						'team_name' => $request->getVal( 'team_name' )
					],
					[ 'team_id' => $id ],
					__METHOD__
				);

				$out->addHTML(
					'<span class="view-status">' .
					$this->msg( 'sportsteams-team-manager-saved' )->escaped() .
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
					$sport_id
				)
				{
					$out->addHTML(
						$this->displayCreateSportForm( $sport_id )
					);
					return;
				} else {
					$out->addHTML(
						'<div><b><a href="' .
						htmlspecialchars( $this->getPageTitle()->getFullURL() ) . '">' .
						$this->msg( 'sportsteams-team-manager-view-sports' )->escaped() .
						'</a></b> | <b><a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL(
							[ 'sport_id' => $sport_id, 'method' => 'edit' ]
						) ) . '">' .
						$this->msg( 'sportsteams-team-manager-add-new-team' )->escaped() . '</a></b></div><p>'
					);
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					$out->addHTML( $this->displayTeamList( $sport_id ) );
				}
			}
		}
	}

	/**
	 * The form for creating a brand new sport (since initially the database is
	 * empty, naturally).
	 *
	 * @param int|null $id Internal sport identifier
	 * @return string HTML
	 */
	private function displayCreateSportForm( $id = null ) {
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
					$this->msg( 'sportsteams-team-manager-sport-name' )->escaped() .
				'</td>
				<td width="695">
					<input type="text" size="45" class="createbox" name="sport_name" value="' . htmlspecialchars( $sportNameValue, ENT_QUOTES ) .'" />
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
						$this->msg( 'sportsteams-network-alt-logo' )->escaped() .
					'</td>
					<td width="695">' . $sport_image . '
						<p>
						<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'SportsManagerLogo' )->getFullURL( "id={$id}" ) ) . '">' .
							$this->msg( 'sportsteams-team-manager-add-replace-logo' )->escaped() .
						'</a>
					</td>
				</tr>';
		}

		// Different button text (and hidden method, which is used in execute())
		// depending on if we're editing a sport or adding one
		if ( $id ) {
			$msg = $this->msg( 'sportsteams-team-manager-edit' )->escaped();
			$method = 'editsport';
		} else {
			$msg = $this->msg( 'sportsteams-team-manager-add-sport-button' )->escaped();
			$method = 'createsport';
		}

		$form .= '<tr>
				<td colspan="2">
					<input type="hidden" name="method" value="' . $method . '" />
					<input type="hidden" name="wpEditToken" value="' . htmlspecialchars( $this->getUser()->getEditToken(), ENT_QUOTES ) . '" />
					<input type="submit" class="site-button" value="' . $msg . '" size="20" />
					<input type="button" class="site-button" value="' . $this->msg( 'cancel' )->escaped() . '" size="20" onclick="history.go(-1)" />
				</td>
			</tr>
		</table>

		</form>';
		return $form;
	}

	private function displaySportsList() {
		$output = '<div>';
		$sports = SportsTeams::getSports();

		if ( $sports ) {
			$linkRenderer = $this->getLinkRenderer();
			$pt = $this->getPageTitle();
			foreach ( $sports as $sport ) {
				$editLink = $this->msg( 'word-separator' )->escaped() .
					$linkRenderer->makeLink(
						$pt,
						$this->msg( 'sportsteams-team-manager-edit-this-sport' )->text(),
						[ 'class' => 'red-edit-link' ],
						[
							'method' => 'editsport',
							'sport_id' => $sport['id']
						]
					);
				$output .= '<div class="Item">';
				$output .= $linkRenderer->makeLink(
					$pt,
					$sport['name'],
					[],
					[ 'sport_id' => $sport['id'] ]
				);
				$output .= $editLink;
				$output .= "</div>\n";
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
	 * @param int $sport_id Sport identifier
	 * @return string HTML
	 */
	private function displayTeamList( $sport_id ) {
		$output = '<div>';
		$teams = SportsTeams::getTeams( $sport_id );
		$linkRenderer = $this->getLinkRenderer();
		$pt = $this->getPageTitle();

		foreach ( $teams as $team ) {
			$output .= '<div class="Item">' .
				$linkRenderer->makeLink(
					$pt,
					$team['name'],
					[],
					[
						'method' => 'edit',
						'sport_id' => $sport_id,
						'id' => $team['id']
					]
				) . "</div>\n";
		}

		$output .= '</div>';
		return '<div id="views">' . $output . '</div>';
	}

	/**
	 * @param int $id Team ID [optional]
	 * @return string HTML
	 */
	function displayForm( int $id ) {
		global $wgUploadPath;

		$request = $this->getRequest();

		$form = '<div><b><a href="' . htmlspecialchars( $this->getPageTitle()->getFullURL( 'sport_id=' . $request->getInt( 'sport_id' ) ) ) . '">' .
			$this->msg( 'sportsteams-team-manager-view-teams' )->escaped() . '</a></b></div><p>';

		if ( $id ) {
			$team = SportsTeams::getTeam( $id );
		} else {
			$team = [ 'id' => '', 'name' => '' ]; // prevent notices
		}

		$form .= '<form action="" method="post" enctype="multipart/form-data" name="sportsteamsmanager">';

		$form .= '<table border="0" cellpadding="5" cellspacing="0" width="500">';

		$form .= '

			<tr>
			<td width="200" class="view-form">' . $this->msg( 'sportsteams-team-manager-sport' )->escaped() . '</td>
			<td width="695">
				<select name="s_id">';
		$sports = SportsTeams::getSports();
		foreach ( $sports as $sport ) {
			$selected = '';
			$form .= Xml::option(
				$sport['name'],
				$sport['id'],
				(
					$request->getInt( 'sport_id' ) == $sport['id'] ||
					$sport['id'] == $team['sport_id']
				)
			);
		}
		$form .= '</select>

			</tr>
			<tr>
				<td width="200" class="view-form">' .
					$this->msg( 'sportsteams-team-manager-teamname' )->escaped() .
				'</td>
				<td width="695">
					<input type="text" size="45" class="createbox" name="team_name" value="' . htmlspecialchars( $team['name'], ENT_QUOTES ) . '" />
				</td>
			</tr>
			';

		if ( $id ) {
			$team_image = "<img src=\"{$wgUploadPath}/team_logos/" .
				SportsTeams::getTeamLogo( $id, 'l' ) .
				'" border="0" alt="logo" />';
			$form .= '<tr>
					<td width="200" class="view-form" valign="top">' .
						$this->msg( 'sportsteams-team-manager-team' )->escaped() .
					'</td>
					<td width="695">' . $team_image . '
						<p>
						<a href="' . htmlspecialchars( SpecialPage::getTitleFor( 'SportsTeamsManagerLogo' )->getFullURL( "id={$id}" ) ) . '">' .
							$this->msg( 'sportsteams-team-manager-add-replace-logo' )->escaped() .
						'</a>
					</td>
				</tr>';
		}

		if ( $id ) {
			$msg = $this->msg( 'sportsteams-team-manager-edit' )->escaped();
		} else {
			$msg = $this->msg( 'sportsteams-team-manager-add-team' )->escaped();
		}

		$form .= '<tr>
				<td colspan="2">
					<input type="hidden" name="id" value="' . (int)$id . '" />
					<input type="hidden" name="wpEditToken" value="' . htmlspecialchars( $this->getUser()->getEditToken(), ENT_QUOTES ) . '" />
					<input type="submit" class="site-button" value="' . $msg . '" size="20" />
					<input type="button" class="site-button" value="' . $this->msg( 'cancel' )->escaped() . '" size="20" onclick="history.go(-1)" />
				</td>
			</tr>
		</table>

		</form>';
		return $form;
	}
}
