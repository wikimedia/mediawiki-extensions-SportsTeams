<?php
/**
 * The hooked functions here are responsible for adding favorite networks and
 * latest thought to social profile pages.
 *
 * @file
 */

class SportsTeamsUserProfile {

	public static function showFavoriteTeams( $user_profile ) {
		global $wgUser, $wgOut, $wgUploadPath;

		$output = '';
		$user_id = $user_profile->user_id;

		// Add CSS and JS
		$wgOut->addModuleStyles( 'ext.sportsTeams.userprofile.module.favoriteteams.css' );
		$wgOut->addModules( 'ext.sportsTeams.userProfile' );

		$add_networks_title = SpecialPage::getTitleFor( 'UpdateFavoriteTeams' );

		$st = new SportsTeams( $wgUser );
		$favs = $st->getUserFavorites();

		if ( $favs ) {
			$output .= '<div class="user-section-heading">
			<div class="user-section-title">' .
				wfMessage( 'sportsteams-profile-networks' )->escaped() .
			'</div>
			<div class="user-section-actions">
				<div class="action-right">';
			if ( $user_profile->isOwner() ) {
				$output .= Linker::link(
					$add_networks_title,
					wfMessage( 'sportsteams-profile-add-network' )->escaped()
				);
			}
			$output .= '</div>
				<div class="visualClear"></div>
			</div>
		</div>
		<div class="network-container">';

			foreach ( $favs as $fav ) {
				$homepage_title = SpecialPage::getTitleFor( 'FanHome' );

				$status_link = '';
				if ( $wgUser->getId() == $user_id ) {
					$status_link = ' <span class="status-message-add"> - <a href="javascript:void(0);" data-order="' .
						$fav['order'] . '" data-sport-id="' . $fav['sport_id'] .
						'" data-team-id="' . $fav['team_id'] . '" rel="nofollow">' .
						wfMessage( 'sportsteams-profile-add-thought' )->escaped() . '</a></span>';
				}

				$network_update_message = '';

				// Originally the following two lines of code were not present and
				// thus $user_updates was always undefined
				$s = new UserStatus( $wgUser );
				$user_updates = $s->getStatusMessages(
					$user_id, $fav['sport_id'], $fav['team_id'], 1, 1
				);

				// Added empty() check
				if ( !empty( $user_updates[$fav['sport_id'] . '-' . $fav['team_id']] ) ) {
					$network_update_message = $user_updates[$fav['sport_id'] . '-' . $fav['team_id']];
				}

				if ( $fav['team_name'] ) {
					$display_name = $fav['team_name'];
					$logo = "<img src=\"{$wgUploadPath}/team_logos/" .
						SportsTeams::getTeamLogo( $fav['team_id'], 's' ) .
						'" border="0" alt="" />';
				} else {
					$display_name = $fav['sport_name'];
					$logo = "<img src=\"{$wgUploadPath}/sport_logos/" .
						SportsTeams::getSportLogo( $fav['sport_id'], 's' ) .
						'" border="0" alt="" />';
				}

				$homepageLink = Linker::link(
					$homepage_title,
					$display_name,
					[],
					[
						'sport_id' => $fav['sport_id'],
						'team_id' => $fav['team_id']
					]
				);
				$output .= "<div class=\"network\">
					{$logo}
					{$homepageLink}
					{$status_link}
				</div>

				<div class=\"status-update-box\" id=\"status-update-box-{$fav['order']}\" style=\"display:none\"></div>";
			}

			$output .= '<div class="visualClear"></div>
			</div>';
		} elseif ( $user_profile->isOwner() ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'sportsteams-profile-networks' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">
						<a href="' . htmlspecialchars( $add_networks_title->getFullURL() ) . '">' .
							wfMessage( 'sportsteams-profile-add-network' )->escaped() . '</a>
					</div>
					<div class="visualClear"></div>
				</div>
			</div>
			<div class="no-info-container">' .
				wfMessage( 'sportsteams-profile-no-networks' )->escaped() .
			'</div>';
		}

		$wgOut->addHTML( $output );
	}

	public static function showLatestThought( $user_profile ) {
		global $wgUser, $wgOut;

		$user_id = $user_profile->user_id;
		$s = new UserStatus( $wgUser );
		$user_update = $s->getStatusMessages( $user_id, 0, 0, 1, 1 );
		$user_update = ( !empty( $user_update[0] ) ? $user_update[0] : [] );

		// Safe URLs
		$more_thoughts_link = SpecialPage::getTitleFor( 'UserStatus' );
		$thought_link = SpecialPage::getTitleFor( 'ViewThought' );

		$output = '';

		// Add CSS
		$wgOut->addModuleStyles( 'ext.sportsTeams.userprofile.module.latestthought.css' );

		if ( $user_update ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'sportsteams-profile-latest-thought' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">
						<a href="' . htmlspecialchars( $more_thoughts_link->getFullURL( 'user=' . $user_profile->user_name ) ) .
						'" rel="nofollow">' . wfMessage( 'sportsteams-profile-view-all' )->escaped() . '</a>
					</div>
					<div class="visualClear"></div>
				</div>
			</div>';

			$vote_count = $vote_link = '';
			// If someone agrees with the most recent status update, show the count
			// next to the timestamp to the owner of the status update
			// After all, there's no point in showing "0 people agree with this"...
			if (
				$wgUser->getName() == $user_update['user_name'] &&
				$user_update['plus_count'] > 0
			)
			{
				$vote_count = wfMessage(
					'sportsteams-profile-num-agree'
				)->numParams(
					$user_update['plus_count']
				)->parse();
			}

			$view_thought_link = Linker::link(
				$thought_link,
				$vote_count,
				[],
				[ 'id' => $user_update['id'] ]
			);

			// Allow registered users who are not owners of this status update to
			// vote for it unless they've already voted; if they have voted, show
			// the amount of people who agree with the status update
			if ( $wgUser->isLoggedIn() && $wgUser->getName() != $user_update['user_name'] ) {
				if ( !$user_update['voted'] ) {
					$vote_link = "<a class=\"profile-vote-status-link\" href=\"javascript:void(0);\" data-status-update-id=\"{$user_update['id']}\" rel=\"nofollow\">" .
						wfMessage( 'sportsteams-profile-do-you-agree' )->escaped() . '</a>';
				} else {
					$vote_count = wfMessage(
						'sportsteams-profile-num-agree'
					)->numParams(
						$user_update['plus_count']
					)->parse();
				}
			}

			$output .= '<div class="status-container" id="status-update">
				<div id="status-update" class="status-message">' .
					SportsTeams::getLogo( $user_update['sport_id'], $user_update['team_id'], 's' ) .
					"{$user_update['text']}
				</div>
				<div class=\"user-status-profile-vote\">
					<span class=\"user-status-date\">" .
						wfMessage( 'sportsteams-profile-ago', SportsTeams::getTimeAgo( $user_update['timestamp'] ) )->parse() .
					"</span>
					{$vote_link} {$view_thought_link}
				</div>
			</div>";
		} else {
			$output .= '<script type="text/javascript">var __more_thoughts_url__ = "' .
				htmlspecialchars( $more_thoughts_link->getFullURL( 'user=' . $user_profile->user_name ) ) .
			'";</script>';
		}

		$wgOut->addHTML( $output );
	}

}