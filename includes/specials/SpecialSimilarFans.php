<?php

class SimilarFans extends SpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'SimilarFans' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the special page, if any [unused]
	 */
	public function execute( $par ) {
		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$user = $this->getUser();
		$linkRenderer = $this->getLinkRenderer();

		/**
		 * Redirect non-logged in users to Login Page
		 * It will automatically return them to the SimilarFans page
		 */
		if ( $user->getId() == 0 ) {
			$out->setPageTitle( $this->msg( 'sportsteams-woops' )->plain() );
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $login->getFullURL( 'returnto=Special:SimilarFans' ) );
			return false;
		}

		// Add CSS
		$out->addModuleStyles( 'ext.sportsTeams' );

		$output = '';

		/**
		 * Get query string variables
		 */
		$page = $this->getRequest()->getInt( 'page', 1 );

		$friends = $foes = $relationships = [];
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

		$total = SportsTeams::getSimilarUserCount( $user );

		/* Get all fans */
		$st = new SportsTeams( $user );
		$fans = $st->getSimilarUsers( $per_page, $page );

		$out->setPageTitle( $this->msg( 'sportsteams-similar-fans' )->text() );

		//$output .= '<div class="friend-links">';
		//$output .= "<a href=\"{$homepage_title->getFullURL()}&sport_id={$sport_id}&team_id={$team_id}\">< Back to Network Home</a>";
		//$output .= '</div>';

		/* Show total fan count */
		$output .= '<div class="relationship-count">' .
			$this->msg( 'sportsteams-num-similar', $total )->parse();
		$output .= '</div>';

		if ( $fans ) {
			$x = 1;

			$rr = SpecialPage::getTitleFor( 'RemoveRelationship' );
			$ar = SpecialPage::getTitleFor( 'AddRelationship' );

			foreach ( $fans as $fan ) {
				$user_name_display = $lang->truncateForVisual( $fan['user_name'], 30 );

				$loopUser = Title::makeTitle( NS_USER, $fan['user_name'] );
				$avatar = new wAvatar( $fan['user_id'], 'ml' );
				$avatar_img = $avatar->getAvatarURL();
				$safeUserURL = htmlspecialchars( $loopUser->getFullURL(), ENT_QUOTES );
				$safeUserName = htmlspecialchars( $user_name_display, ENT_QUOTES );

				$output .= "<div class=\"relationship-item\">
							<div class=\"relationship-image\"><a href=\"{$safeUserURL}\">{$avatar_img}</a></div>
							<div class=\"relationship-info\">
								<div class=\"relationship-name\"><a href=\"{$safeUserURL}\">{$safeUserName}</a>";

				$output .= '</div>
					<div class="relationship-actions">';

				$pipeList = [];
				if ( in_array( $fan['actor'], $friends ) ) {
					$pipeList[] = $linkRenderer->makeLink(
						$rr,
						$this->msg( 'sportsteams-remove-as-friend' )->text(),
						[],
						[ 'user' => $loopUser->getText() ]
					);
				}
				if ( in_array( $fan['actor'], $foes ) ) {
					$pipeList[] = $linkRenderer->makeLink(
						$rr,
						$this->msg( 'sportsteams-remove-as-foe' )->text(),
						[],
						[ 'user' => $loopUser->getText() ]
					);
				}
				if ( $fan['user_name'] != $user->getName() ) {
					if ( !in_array( $fan['actor'], $relationships ) ) {
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
					//$output .= "<p class=\"relationship-link\"><a href=\"index.php?title=Special:ChallengeUser&user={$fan['user_name']}\"><img src=\"images/common/challengeIcon.png\" border=\"0\" alt=\"issue challenge\"/> issue challenge</a></p>";
					$output .= $lang->pipeList( $pipeList );
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
			$output .= '<div class="page-nav">';
			if ( $page > 1 ) {
				$output .= $linkRenderer->makeLink(
					$this->getPageTitle(),
					$this->msg( 'sportsteams-prev' )->plain(),
					[],
					[ 'page' => ( $page - 1 ) ]
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
						$this->getPageTitle(),
						(string)$i,
						[],
						[ 'page' => $i ]
					) . $this->msg( 'word-separator' )->escaped();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->escaped() . $linkRenderer->makeLink(
					$this->getPageTitle(),
					$this->msg( 'sportsteams-next' )->plain(),
					[],
					[ 'page' => ( $page + 1 ) ]
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

	protected function getGroupName() {
		return 'users';
	}
}
