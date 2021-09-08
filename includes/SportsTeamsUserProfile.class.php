<?php
/**
 * The hooked functions here are responsible for adding favorite networks and
 * latest thought to social profile pages.
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

class SportsTeamsUserProfile {

	/**
	 * @param UserProfilePage $user_profile
	 */
	public static function showFavoriteTeams( $user_profile ) {
		global $wgUploadPath;

		$output = '';

		$out = $user_profile->getContext()->getOutput();

		// Add CSS and JS
		$out->addModuleStyles( 'ext.sportsTeams.userprofile.module.favoriteteams.css' );
		$out->addModules( 'ext.sportsTeams.userProfile' );

		$add_networks_title = SpecialPage::getTitleFor( 'UpdateFavoriteTeams' );

		$st = new SportsTeams( $user_profile->profileOwner );
		$favs = $st->getUserFavorites();

		if ( $favs ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

			$output .= '<div class="user-section-heading">
			<div class="user-section-title">' .
				wfMessage( 'sportsteams-profile-networks' )->escaped() .
			'</div>
			<div class="user-section-actions">
				<div class="action-right">';
			if ( $user_profile->isOwner() ) {
				$output .= $linkRenderer->makeKnownLink(
					$add_networks_title,
					wfMessage( 'sportsteams-profile-add-network' )->text()
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
				if ( $user_profile->isOwner() ) {
					$status_link = ' <span class="status-message-add"> - <a href="javascript:void(0);" data-order="' .
						(int)$fav['order'] . '" data-sport-id="' . (int)$fav['sport_id'] .
						'" data-team-id="' . (int)$fav['team_id'] . '" rel="nofollow">' .
						wfMessage( 'sportsteams-profile-add-thought' )->escaped() . '</a></span>';
				}

				$network_update_message = '';

				// Originally the following two lines of code were not present and
				// thus $user_updates was always undefined
				$s = new UserStatus( $user_profile->profileOwner );
				$user_updates = $s->getStatusMessages(
					$user_profile->profileOwner->getActorId(),
					$fav['sport_id'],
					$fav['team_id'],
					1,
					1
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

				$homepageLink = $linkRenderer->makeKnownLink(
					$homepage_title,
					$display_name,
					[],
					[
						'sport_id' => $fav['sport_id'],
						'team_id' => $fav['team_id']
					]
				);
				$order = htmlspecialchars( $fav['order'] );
				$output .= "<div class=\"network\">
					{$logo}
					{$homepageLink}
					{$status_link}
				</div>

				<div class=\"status-update-box\" id=\"status-update-box-{$order}\" style=\"display:none\"></div>";
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

		$out->addHTML( $output );
	}

	/**
	 * @param UserProfilePage $user_profile
	 */
	public static function showLatestThought( $user_profile ) {
		$out = $user_profile->getContext()->getOutput();

		$s = new UserStatus( $user_profile->profileOwner );
		$user_update = $s->getStatusMessages( $user_profile->profileOwner->getActorId(), 0, 0, 1, 1 );
		$user_update = ( !empty( $user_update[0] ) ? $user_update[0] : [] );

		// Safe URLs
		$more_thoughts_link = SpecialPage::getTitleFor( 'UserStatus' );
		$thought_link = SpecialPage::getTitleFor( 'ViewThought' );

		$output = '';

		// Add CSS
		$out->addModuleStyles( 'ext.sportsTeams.userprofile.module.latestthoughts.css' );

		if ( $user_update ) {
			$output .= '<div class="user-section-heading">
				<div class="user-section-title">' .
					wfMessage( 'sportsteams-profile-latest-thought' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">
						<a href="' . htmlspecialchars( $more_thoughts_link->getFullURL( [ 'user' => $user_profile->profileOwner->getName() ] ) ) .
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
				$user_profile->viewingUser->getActorId() == $user_update['actor'] &&
				$user_update['plus_count'] > 0
			)
			{
				$vote_count = wfMessage(
					'sportsteams-profile-num-agree'
				)->numParams(
					$user_update['plus_count']
				)->parse();
			}

			$view_thought_link = MediaWikiServices::getInstance()->getLinkRenderer()->makeKnownLink(
				$thought_link,
				new HtmlArmor( $vote_count ),
				[],
				[ 'id' => $user_update['id'] ]
			);

			// Allow registered users who are not owners of this status update to
			// vote for it unless they've already voted; if they have voted, show
			// the amount of people who agree with the status update
			if (
				$user_profile->viewingUser->isRegistered() &&
				$user_profile->viewingUser->getActorId() != $user_update['actor']
			) {
				if ( !$user_update['voted'] ) {
					$votePage = SpecialPage::getTitleFor( 'UserStatus' )->getFullURL( [
						'action' => 'vote',
						'us_id' => (int)$user_update['id'],
						'vote' => '1'
					] );
					$votePageSafe = htmlspecialchars( $votePage, ENT_QUOTES );
					$vote_link = "<a class=\"profile-vote-status-link\" href=\"{$votePageSafe}\" data-status-update-id=\"{$user_update['id']}\" rel=\"nofollow\">" .
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
				htmlspecialchars( $more_thoughts_link->getFullURL( [ 'user' => $user_profile->profileOwner->getName() ] ) ) .
			'";</script>';
		}

		// @phan-suppress-next-line SecurityCheck-XSS False positive due to $user_update['text'] or $user_update['id'] usage
		$out->addHTML( $output );
	}

}
