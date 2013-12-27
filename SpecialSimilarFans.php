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
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		$lang = $this->getLanguage();
		$out = $this->getOutput();
		$user = $this->getUser();

		/**
		 * Redirect non-logged in users to Login Page
		 * It will automatically return them to the SimilarFans page
		 */
		if ( $user->getID() == 0 ) {
			$out->setPageTitle( $this->msg( 'sportsteams-woops' )->plain() );
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $login->getFullURL( 'returnto=Special:SimilarFans' ) );
			return false;
		}

		// Add CSS
		$out->addModules( 'ext.sportsTeams' );

		$output = '';

		/**
		 * Get query string variables
		 */
		$page = $this->getRequest()->getInt( 'page', 1 );

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

		$total = SportsTeams::getSimilarUserCount( $user->getID() );

		/* Get all fans */
		$fans = SportsTeams::getSimilarUsers( $user->getID(), $per_page, $page );

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

			foreach ( $fans as $fan ) {
				$user_name_display = $lang->truncate( $fan['user_name'], 30 );

				$loopUser = Title::makeTitle( NS_USER, $fan['user_name'] );
				$avatar = new wAvatar( $fan['user_id'], 'ml' );
				$avatar_img = $avatar->getAvatarURL();

				$output .= "<div class=\"relationship-item\">
							<div class=\"relationship-image\"><a href=\"{$loopUser->getFullURL()}\">{$avatar_img}</a></div>
							<div class=\"relationship-info\">
								<div class=\"relationship-name\"><a href=\"{$loopUser->getFullURL()}\">{$user_name_display}</a>";

				$output .= '</div>
					<div class="relationship-actions">';
				$rr = SpecialPage::getTitleFor( 'RemoveRelationship' );
				$ar = SpecialPage::getTitleFor( 'AddRelationship' );
				$pipeList = array();
				if ( in_array( $fan['user_id'], $friends ) ) {
					$pipeList[] = Linker::link(
						$rr,
						$this->msg( 'sportsteams-remove-as-friend' )->text(),
						array(),
						array( 'user' => $loopUser->getText() )
					);
				}
				if ( in_array( $fan['user_id'], $foes ) ) {
					$pipeList[] = Linker::link(
						$rr,
						$this->msg( 'sportsteams-remove-as-foe' )->text(),
						array(),
						array( 'user' => $loopUser->getText() )
					);
				}
				if ( $fan['user_name'] != $user->getName() ) {
					if ( !in_array( $fan['user_id'], $relationships ) ) {
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
					//$output .= "<p class=\"relationship-link\"><a href=\"index.php?title=Special:ChallengeUser&user={$fan['user_name']}\"><img src=\"images/common/challengeIcon.png\" border=\"0\" alt=\"issue challenge\"/> issue challenge</a></p>";
					$output .= $lang->pipeList( $pipeList );
					$output .= $this->msg( 'word-separator' )->plain();
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
					array( 'page' => ( $page - 1 ) )
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
					$output .= ( $i . ' ');
				} else {
					$output .= Linker::link(
						$this->getPageTitle(),
						$i,
						array(),
						array( 'page' => $i )
					) . $this->msg( 'word-separator' )->plain();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->plain() . Linker::link(
					$this->getPageTitle(),
					$this->msg( 'sportsteams-next' )->plain(),
					array(),
					array( 'page' => ( $page + 1 ) )
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