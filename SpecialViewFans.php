<?php

class ViewFans extends UnlistedSpecialPage {

	/**
	 * @var String: name of the network (sports team)
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
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		global $wgUploadPath;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$output = '';

		/**
		 * Get query string variables
		 */
		$page = $request->getInt( 'page', 1 );
		$sport_id = $request->getVal( 'sport_id' );
		$team_id = $request->getVal( 'team_id' );

		/**
		 * Error message for teams/sports that do not exist (from URL)
		 */
		if ( !$team_id && !$sport_id ) {
			$out->setPageTitle( $this->msg( 'sportsteams-network-woops-title' )->text() );
			$output = '<div class="relationship-request-message">' .
				$this->msg( 'sportsteams-network-woops-text' )->text() . '</div>';
			$output .= '<div class="relationship-request-buttons">';
			$output .= '<input type="button" class="site-button" value="' .
				$this->msg( 'sportsteams-network-main-page' )->text() .
				"\" onclick=\"window.location='" .
				htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
			if ( $user->isLoggedIn() ) {
				$output .= ' <input type="button" class="site-button" value="' .
					$this->msg( 'sportsteams-network-your-profile' )->text() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::makeTitle( NS_USER, $user->getName() )->getFullURL() ) . "'\"/>";
			}
		  	$output .= '</div>';
			$out->addHTML( $output );
			return false;
		}

		// Add CSS
		$out->addModules( 'ext.sportsTeams' );

		$relationships = array();
		$friends = array();
		$foes = array();
		if ( $user->isLoggedIn() ) {
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
				'" border="0" alt="' . $this->msg( 'sportsteams-network-alt-logo' )->plain() . '" />';
		} else {
			$sport = SportsTeams::getSport( $sport_id );
			$this->network = $sport['name'];
			$fanMessageName = 'sportsteams-network-num-fans-sport';
			$team_image = "<img src=\"{$wgUploadPath}/team_logos/" .
				SportsTeams::getSportLogo( $sport_id, 'l' ) .
				'" border="0" alt="' . $this->msg( 'sportsteams-network-alt-logo' )->plain() . '" />';
		}
		$homepage_title = SpecialPage::getTitleFor( 'FanHome' );

		$total = SportsTeams::getUserCount( $sport_id, $team_id );

		/* Get all fans */
		$fans = SportsTeams::getUsersByFavorite(
			$sport_id, $team_id, $per_page, $page
		);

		$out->setPageTitle( $this->msg( 'sportsteams-network-network-fans', $this->network )->text() );

		$output .= '<div class="friend-links">';
		$output .= Linker::link(
			$homepage_title,
			$this->msg( 'sportsteams-network-back-to-network', $this->network )->text(),
			array(),
			array(
				'sport_id' => $sport_id,
				'team_id' => $team_id
			)
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

				$output .= "<div class=\"relationship-item\">
						<div class=\"relationship-image\"><a href=\"{$loopUser->getFullURL()}\">{$avatar_img}</a></div>
						<div class=\"relationship-info\">
						<div class=\"relationship-name\">
							<a href=\"{$loopUser->getFullURL()}\">{$fan['user_name']}</a>";

				$output .= '</div>
					<div class="relationship-actions">';
				if ( in_array( $fan['user_id'], $friends ) ) {
					$output .= '	<span class="profile-on">' . $this->msg( 'sportsteams-your-friend' )->text() . '</span> ';
				}
				if ( in_array( $fan['user_id'], $foes ) ) {
					$output .= '	<span class="profile-on">' . $this->msg( 'sportsteams-your-foe' )->text() . '</span> ';
				}
				if ( $fan['user_name'] != $user->getName() ) {
					$pipeList = array();
					if ( !in_array( $fan['user_id'], $relationships ) ) {
						$ar = SpecialPage::getTitleFor( 'AddRelationship' );
						$pipeList[] = Linker::link(
							$ar,
							$this->msg( 'sportsteams-add-as-friend' )->text(),
							array(),
							array( 'user' => $fan['user_name'], 'rel_type' => '1' )
						);
						$pipeList[] = Linker::link(
							$ar,
							$this->msg( 'sportsteams-add-as-foe' )->text(),
							array(),
							array( 'user' => $fan['user_name'], 'rel_type' => '2' )
						);
					}
					$pipeList[] = Linker::link(
						SpecialPage::getTitleFor( 'GiveGift' ),
						$this->msg( 'sportsteams-give-a-gift' )->text(),
						array(),
						array( 'user' => $fan['user_name'] )
					);
					$output .= $this->getLanguage()->pipeList( $pipeList );
					//$output .= "<p class=\"relationship-link\"><a href=\"index.php?title=Special:ChallengeUser&user={$fan['user_name']}\"><img src=\"images/common/challengeIcon.png\" border=\"0\" alt=\"issue challenge\"/> issue challenge</a></p>";
					$output .= $this->msg( 'word-separator' )->text();
					$output .= '<div class="cleared"></div>';
				}
				$output .= '</div>';

				$output .= '<div class="cleared"></div></div>';

				$output .= '</div>';
				if ( $x == count( $fans ) || $x != 1 && $x % $per_row == 0 ) {
					$output .= '<div class="cleared"></div>';
				}
				$x++;
			}
		}

		/**
		 * Build next/prev navigation
		 */
		$numofpages = $total / $per_page;

		if ( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';
			if ( $page > 1 ) {
				$output .= Linker::link(
					$this->getPageTitle(),
					$this->msg( 'sportsteams-prev' )->plain(),
					array(),
					array(
						'page' => ( $page - 1 ),
						'sport_id' => $sport_id,
						'team_id' => $team_id
					)
				) . $this->msg( 'word-separator' )->plain();
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
				    $output .= Linker::link(
						$this->getPageTitle(),
						$i,
						array(),
						array(
							'page' => ( $i ),
							'sport_id' => $sport_id,
							'team_id' => $team_id
						)
					) . $this->msg( 'word-separator' )->plain();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->plain() . Linker::link(
					$this->getPageTitle(),
					$this->msg( 'sportsteams-next' )->plain(),
					array(),
					array(
						'page' => ( $page + 1 ),
						'sport_id' => $sport_id,
						'team_id' => $team_id
					)
				);
			}

			$output .= '</div>';
		}

		$out->addHTML( $output );
	}

	function getRelationships( $rel_type ) {
		$rel = new UserRelationship( $this->getUser()->getName() );
		$relationships = $rel->getRelationshipIDs( $rel_type );
		return $relationships;
	}
}