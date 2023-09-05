<?php
/**
 * A special page that lists the most popular networks.
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 */

class TopNetworks extends SpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'TopNetworks' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the special page, if any [unused]
	 */
	public function execute( $par ) {
		global $wgUploadPath;

		// Variables
		$request = $this->getRequest();
		$out = $this->getOutput();
		$output = '';
		$direction = $request->getVal( 'direction' );
		$type = $request->getVal( 'type' );
		$sport = $request->getInt( 'sport' );

		// Direction
		if ( $direction == 'worst' ) {
			$order = 'ASC';
			$adj = 'least';
		} else {
			$order = 'DESC';
			$adj = 'most';
		}

		// Type
		if ( $type == 'sport' ) {
			$type_title = 'sports';
		} else {
			$type_title = 'teams';
		}

		// Sport
		$where = [];
		if ( $sport ) {
			$where['team_sport_id'] = $sport;
		}

		// Set the page title
		// For grep: sportsteams-top-network-team-title-least-sports,
		// sportsteams-top-network-team-title-least-teams,
		// sportsteams-top-network-team-title-most-sports,
		// sportsteams-top-network-team-title-most-teams
		$out->setPageTitle( $this->msg( 'sportsteams-top-network-team-title-' . $adj . '-' . $type_title )->text() );

		// Add CSS
		$out->addModuleStyles( 'ext.sportsTeams' );

		// Database handler
		$dbr = wfGetDB( DB_MASTER );

		// Teams
		$res = $dbr->select(
			[ 'sport_team', 'sport_favorite' ],
			[ 'team_id', 'team_name', 'team_sport_id', 'sf_team_id', 'COUNT(*) AS network_user_count' ],
			$where,
			__METHOD__,
			[
				'GROUP BY' => 'team_id, team_name, team_sport_id, sf_team_id',
				'ORDER BY' => "network_user_count {$order}",
				'LIMIT' => 50
			],
			[
				'sport_favorite' => [ 'INNER JOIN', 'sf_team_id = team_id' ]
			]
		);

		// Sports
		$res_sport = $dbr->select(
			[ 'sport_favorite', 'sport' ],
			[
				'COUNT(sf_sport_id) AS sport_count', 'sf_sport_id', 'sport_name'
			],
			[],
			__METHOD__,
			[
				'GROUP BY' => 'sf_sport_id, sport_name',
				'ORDER BY' => "sport_count {$order}",
				'LIMIT' => 50
			],
			[ 'sport' => [ 'INNER JOIN', 'sf_sport_id = sport_id' ] ]
		);

		// Navigation
		$navMenu = $this->getNavigationMenu( $direction, $type, $sport, $order, $adj );

		$output .= $navMenu;

		// List Networks
		$output .= '<div class="top-networks">';

		// Set counter
		$x = 1;

		if ( $type == 'sport' ) {
			$fanHome = SpecialPage::getTitleFor( 'FanHome' );
			if ( $res_sport->numRows() === 0 ) {
				$output .= $this->msg( 'specialpage-empty' )->escaped();
			} else {
				foreach ( $res_sport as $row_sport ) {
					// More variables
					$user_count = $row_sport->sport_count;
					$sport = htmlspecialchars( $row_sport->sport_name );
					$sport_id = $row_sport->sf_sport_id;

					// Get team logo
					$sport_image = '<img src="' . $wgUploadPath . '/sport_logos/' .
						SportsTeams::getSportLogo( $sport_id, 's' ) .
						'" border="0" alt="logo" />';

					$output .= "<div class=\"network-row\">
						<span class=\"network-number\">{$x}.</span>
						<span class=\"network-team\">
							{$sport_image}
							<a href=\"" . htmlspecialchars( $fanHome->getFullURL( [ 'sport_id' => $sport_id ] ) ) . "\">{$sport}</a>
						</span>
						<span class=\"network-count\">" .
							$this->msg(
								'sportsteams-count-fans',
								$user_count
							)->parse() .
						'</span>
						<div class="visualClear"></div>
					</div>';
					$x++;
				}
			}
		} else {
			if ( $res->numRows() === 0 ) {
				$output .= $this->msg( 'specialpage-empty' )->escaped();
			} else {
				foreach ( $res as $row ) {
					// More variables
					$user_count = $row->network_user_count;
					$team = htmlspecialchars( $row->team_name );
					$team_id = $row->sf_team_id;
					$sport_id = $row->team_sport_id;

					// Get team logo
					$team_image = '<img src="' . $wgUploadPath . '/team_logos/' .
						SportsTeams::getTeamLogo( $team_id, 's' ) .
						'" border="0" alt="logo" />';

					$fanHome = SpecialPage::getTitleFor( 'FanHome' );
					$output .= "<div class=\"network-row\">
						<span class=\"network-number\">{$x}.</span>
						<span class=\"network-team\">
							{$team_image}
							<a href=\"" . htmlspecialchars( $fanHome->getFullURL( [
								'sport_id' => $sport_id,
								'team_id' => $team_id
							] ) ) . "\">{$team}</a>
						</span>
						<span class=\"network-count\">" .
							$this->msg(
								'sportsteams-count-fans',
								$user_count
							)->parse() .
						'</span>
						<div class="visualClear"></div>
					</div>';
					$x++;
				}
			}
		}

		$output .= '</div>
		<div class="visualClear"></div>';

		$out->addHTML( $output );
	}

	/**
	 * Get the navigation menu specific to this special page.
	 *
	 * @param string|null $direction 'worst' or 'best'
	 * @param string|null $type Either 'sport' for an individual sport or unset
	 * @param int|null $sport Sport ID
	 * @param string $order ASC for ascending, DESC for descending
	 * @param string $adj Adjective, either 'least' or 'most'
	 * @return string HTML for the navigation menu
	 */
	protected function getNavigationMenu( $direction, $type, $sport, $order, $adj ) {
		$output = '';
		$dbr = wfGetDB( DB_REPLICA );
		$pt = $this->getPageTitle();

		// Navigation
		$output .= '<div class="top-networks-navigation">
			<h1>' . $this->msg( 'sportsteams-top-network-most-popular' )->escaped() . '</h1>';

		if ( !( $sport ) && !( $type ) && !( $direction ) ) {
			$output .= '<p><b>' . $this->msg( 'sportsteams-top-network-teams' )->escaped() . '</b></p>';
		} elseif ( !( $sport ) && !( $type ) && ( $direction == 'best' ) ) {
			$output .= '<p><b>' . $this->msg( 'sportsteams-top-network-teams' )->escaped() . '</b></p>';
		} else {
			$output .= '<p><a href="' . htmlspecialchars( $pt->getFullURL(
				[ 'direction' => 'best' ]
			) ) . '">' . $this->msg( 'sportsteams-top-network-teams' )->escaped() . '</a></p>';
		}

		if ( !( $sport ) && ( $type == 'sport' ) && ( $direction == 'best' ) ) {
			$output .= '<p><b>' . $this->msg( 'sportsteams-top-network-sports' )->escaped() . '</b></p>';
		} else {
			$output .= '<p><a href="' . htmlspecialchars( $pt->getFullURL(
				[ 'type' => 'sport', 'direction' => 'best' ]
			) ) . '">' . $this->msg( 'sportsteams-top-network-sports' )->escaped() . '</a></p>';
		}

		$output .= '<h1 style="margin-top:15px !important;">' .
			$this->msg( 'sportsteams-top-network-least-popular' )->escaped() . '</h1>';

		if ( !( $sport ) && !( $type ) && ( $direction == 'worst' ) ) {
			$output .= '<p><b>' . $this->msg( 'sportsteams-top-network-teams' )->escaped() . '</b></p>';
		} else {
			$output .= '<p><a href="' . htmlspecialchars( $pt->getFullURL(
				[ 'direction' => 'worst' ]
			) ) . '">' . $this->msg( 'sportsteams-top-network-teams' )->escaped() . '</a></p>';
		}

		if ( !( $sport ) && ( $type == 'sport' ) && ( $direction == 'worst' ) ) {
			$output .= '<p><b>' . $this->msg( 'sportsteams-top-network-sports' ) . '</b></p>';
		} else {
			$output .= '<p><a href="' . htmlspecialchars( $pt->getFullURL(
				[ 'type' => 'sport', 'direction' => 'worst' ]
			) ) . '">' . $this->msg( 'sportsteams-top-network-sports' )->escaped() . "</a></p>";
		}

		// for grep: sportsteams-top-network-most-pop-by-sport,
		// sportsteams-top-network-least-pop-by-sport
		$output .= '<h1 style="margin-top:15px !important;">' .
			$this->msg( 'sportsteams-top-network-' . strtolower( $adj ) . '-pop-by-sport' )->escaped() . '</h1>';

		$res_sport_nav = $dbr->select(
			[ 'sport', 'sport_team' ],
			[ 'sport_id', 'sport_name', 'team_sport_id' ],
			[],
			__METHOD__,
			[
				'GROUP BY' => 'sport_name, sport_id, team_sport_id',
				'ORDER BY' => 'sport_id'
			],
			[
				'sport_team' => [ 'INNER JOIN', 'sport_id = team_sport_id' ]
			]
		);

		$out = $this->getOutput();

		foreach ( $res_sport_nav as $row_sport_nav ) {
			$sport_id = $row_sport_nav->sport_id;
			$sport_name = htmlspecialchars( $row_sport_nav->sport_name );

			if ( $sport_id == $sport ) {
				$output .= "<p><b>{$sport_name}</b></p>";
				// For grep: sportsteams-top-network-least-team-title,
				// sportsteams-top-network-most-team-title
				$out->setPageTitle(
					$this->msg( 'sportsteams-top-network-' . strtolower( $adj ) . '-team-title',
						$row_sport_nav->sport_name )->escaped()
				);
			} else {
				$output .= '<p><a href="' . htmlspecialchars( $pt->getFullURL(
					[ 'direction' => $direction, 'sport' => $sport_id ]
				) ) . '">' . $sport_name . '</a></p>';
			}
		}

		$output .= '</div>';

		return $output;
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
