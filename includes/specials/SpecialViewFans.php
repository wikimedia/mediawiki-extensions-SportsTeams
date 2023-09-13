<?php

class ViewFans extends UnlistedSpecialPage {

	/**
	 * @var string Name of the network (sports team)
	 */
	public $network;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'ViewFans' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the special page, if any [unused]
	 */
	public function execute( $par ) {
		global $wgUploadPath;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$linkRenderer = $this->getLinkRenderer();

		$output = '';

		$this->setHeaders();

		/**
		 * Get query string variables
		 */
		$page = $request->getInt( 'page', 1 );
		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );

		/**
		 * Error message for teams/sports that do not exist (from URL)
		 */
		if ( !$team_id && !$sport_id ) {
			$out->setPageTitle( $this->msg( 'sportsteams-network-woops-title' )->text() );
			$output = '<div class="relationship-request-message">' .
				$this->msg( 'sportsteams-network-woops-text' )->escaped() . '</div>';
			$output .= '<div class="relationship-request-buttons">';
			$output .= '<input type="button" class="site-button" value="' .
				$this->msg( 'sportsteams-network-main-page' )->escaped() .
				"\" onclick=\"window.location='" .
				htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
			if ( $user->isRegistered() ) {
				$output .= ' <input type="button" class="site-button" value="' .
					$this->msg( 'sportsteams-network-your-profile' )->escaped() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::makeTitle( NS_USER, $user->getName() )->getFullURL() ) . "'\"/>";
			}
		  	$output .= '</div>';
			$out->addHTML( $output );
			return false;
		}

		// Add CSS
		$out->addModuleStyles( 'ext.sportsTeams' );

		$relationships = [];
		$friends = [];
		$foes = [];
		if ( $user->isRegistered() ) {
			$friends = $this->getRelationships( 1 );
			$foes = $this->getRelationships( 2 );
			$relationships = array_merge( $friends, $foes );
		}

		/**
		 * Set up config for page / default values
		 */
		$per_page = 50;
		$per_row = 2;

		if ( $team_id ) {
			$team = SportsTeams::getTeam( $team_id );
			$this->network = $team['name'];

			// Different i18n message depending on what we're viewing; for a team,
			// we need "the", i.e. "The Mets have X fans", but for a sport, the
			// "the" has to be omitted. Also, have/has distinction.
			$fanMessageName = 'sportsteams-network-num-fans';

			$team_image = "<img src=\"{$wgUploadPath}/team_logos/" .
				SportsTeams::getTeamLogo( $team_id, 'l' ) .
				'" border="0" alt="' . $this->msg( 'sportsteams-network-alt-logo' )->escaped() . '" />';
		} else {
			$sport = SportsTeams::getSport( $sport_id );
			$this->network = $sport['name'];
			$fanMessageName = 'sportsteams-network-num-fans-sport';
			$team_image = "<img src=\"{$wgUploadPath}/team_logos/" .
				SportsTeams::getSportLogo( $sport_id, 'l' ) .
				'" border="0" alt="' . $this->msg( 'sportsteams-network-alt-logo' )->escaped() . '" />';
		}

		$homepage_title = SpecialPage::getTitleFor( 'FanHome' );
		$total = SportsTeams::getUserCount( $sport_id, $team_id );

		/* Get all fans */
		$fans = SportsTeams::getUsersByFavorite(
			$sport_id, $team_id, $per_page, $page
		);

		$out->setPageTitle( $this->msg( 'sportsteams-network-network-fans', $this->network )->text() );

		$output .= '<div class="friend-links">';
		$output .= $linkRenderer->makeLink(
			$homepage_title,
			$this->msg( 'sportsteams-network-back-to-network', $this->network )->text(),
			[],
			[
				'sport_id' => $sport_id,
				'team_id' => $team_id
			]
		);
		$output .= '</div>';

		/* Show total fan count */
		$output .= '<div class="friend-message">' .
			$this->msg(
				$fanMessageName,
				$this->network,
				$total
			)->parse();
		$output .= '</div>';

		if ( $fans ) {
			$x = 1;

			foreach ( $fans as $fan ) {
				$loopUser = Title::makeTitle( NS_USER, $fan['user_name'] );
				$avatar = new wAvatar( $fan['user_id'], 'l' );
				$avatar_img = $avatar->getAvatarURL();
				$escapedUserName = htmlspecialchars( $fan['user_name'], ENT_QUOTES );
				$escapedUserPageURL = htmlspecialchars( $loopUser->getFullURL(), ENT_QUOTES );

				$output .= "<div class=\"relationship-item\">
						<div class=\"relationship-image\"><a href=\"{$escapedUserPageURL}\">{$avatar_img}</a></div>
						<div class=\"relationship-info\">
						<div class=\"relationship-name\">
							<a href=\"{$escapedUserPageURL}\">{$escapedUserName}</a>";

				$output .= '</div>
					<div class="relationship-actions">';
				if ( in_array( $fan['actor'], $friends ) ) {
					$output .= '	<span class="profile-on">' . $this->msg( 'sportsteams-your-friend' )->escaped() . '</span> ';
				}
				if ( in_array( $fan['actor'], $foes ) ) {
					$output .= '	<span class="profile-on">' . $this->msg( 'sportsteams-your-foe' )->escaped() . '</span> ';
				}
				if ( $fan['user_name'] != $user->getName() ) {
					$pipeList = [];
					if ( !in_array( $fan['actor'], $relationships ) ) {
						$ar = SpecialPage::getTitleFor( 'AddRelationship' );
						$pipeList[] = $linkRenderer->makeLink(
							$ar,
							$this->msg( 'sportsteams-add-as-friend' )->text(),
							[],
							[ 'user' => $fan['user_name'], 'rel_type' => '1' ]
						);
						$pipeList[] = $linkRenderer->makeLink(
							$ar,
							$this->msg( 'sportsteams-add-as-foe' )->text(),
							[],
							[ 'user' => $fan['user_name'], 'rel_type' => '2' ]
						);
					}
					$pipeList[] = $linkRenderer->makeLink(
						SpecialPage::getTitleFor( 'GiveGift' ),
						$this->msg( 'sportsteams-give-a-gift' )->text(),
						[],
						[ 'user' => $fan['user_name'] ]
					);
					$output .= $this->getLanguage()->pipeList( $pipeList );
					//$output .= "<p class=\"relationship-link\"><a href=\"index.php?title=Special:ChallengeUser&user={$fan['user_name']}\"><img src=\"images/common/challengeIcon.png\" border=\"0\" alt=\"issue challenge\"/> issue challenge</a></p>";
					$output .= $this->msg( 'word-separator' )->escaped();
					$output .= '<div class="visualClear"></div>';
				}
				$output .= '</div>';

				$output .= '<div class="visualClear"></div></div>';

				$output .= '</div>';
				if ( $x == count( $fans ) || $x != 1 && $x % $per_row == 0 ) {
					$output .= '<div class="visualClear"></div>';
				}
				$x++;
			}
		}

		/**
		 * Build next/prev navigation
		 */
		$numofpages = $total / $per_page;

		if ( $numofpages > 1 ) {
			$pt = $this->getPageTitle();
			$output .= '<div class="page-nav">';
			if ( $page > 1 ) {
				$output .= $linkRenderer->makeLink(
					$pt,
					$this->msg( 'sportsteams-prev' )->plain(),
					[],
					[
						'page' => ( $page - 1 ),
						'sport_id' => $sport_id,
						'team_id' => $team_id
					]
				) . $this->msg( 'word-separator' )->escaped();
			}

			if ( ( $total % $per_page ) != 0 ) {
				$numofpages++;
			}
			if ( $numofpages >= 9 ) {
				$numofpages = 9 + $page;
			}

			for ( $i = 1; $i <= $numofpages; $i++ ) {
				if ( $i == $page ) {
				    $output .= ( $i . ' ' );
				} else {
				    $output .= $linkRenderer->makeLink(
						$pt,
						(string)$i,
						[],
						[
							'page' => $i,
							'sport_id' => $sport_id,
							'team_id' => $team_id
						]
					) . $this->msg( 'word-separator' )->escaped();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->escaped() . $linkRenderer->makeLink(
					$pt,
					$this->msg( 'sportsteams-next' )->plain(),
					[],
					[
						'page' => ( $page + 1 ),
						'sport_id' => $sport_id,
						'team_id' => $team_id
					]
				);
			}

			$output .= '</div>';
		}

		$out->addHTML( $output );
	}

	private function getRelationships( $rel_type ) {
		$rel = new UserRelationship( $this->getUser() );
		$relationships = $rel->getRelationshipIDs( $rel_type );
		return $relationships;
	}
}
