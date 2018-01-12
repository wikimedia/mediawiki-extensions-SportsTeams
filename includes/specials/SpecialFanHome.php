<?php
/**
 * A special page for viewing networks; the page displays some info about the
 * network, some of its fans, a map (requires a working Google Maps API key)
 * that shows where in the world the network's fans are located in, a listing
 * of the newest status updates (requires UserStatus) and some related blog
 * posts.
 *
 * @file
 * @ingroup Extensions
 */
class FanHome extends UnlistedSpecialPage {

	public $friends, $foes, $relationships, $network_count,
		$friends_network_count;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'FanHome' );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the special page or null
	 */
	public function execute( $par ) {
		global $wgSportsTeamsGoogleAPIKey;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $user->isLoggedIn() ) {
			$this->friends = $this->getRelationships( 1 );
			$this->foes = $this->getRelationships( 2 );
			$this->relationships = array_merge( $this->friends, $this->foes );
		} else {
			// Prevent fatals (+1 notice) for anonymous users
			$this->friends = $this->foes = $this->relationships =
				$fan_info = '';
		}

		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );

		// If there's neither a sports ID nor a team ID, show an error message.
		// @todo FIXME: I don't like this; we should be showing a listing of
		// all networks or something instead of basically telling the user to
		// go away.
		if ( !$sport_id && !$team_id ) {
			$out->setPageTitle( $this->msg( 'sportsteams-network-woops-title' )->plain() );
			$output = '<div class="relationship-request-message">' .
				$this->msg( 'sportsteams-network-woops-text' )->plain() . '</div>';
			$output .= '<div class="relationship-request-buttons">';
			$output .= '<input type="button" class="site-button" value="' .
				$this->msg( 'sportsteams-network-main-page' )->plain() .
				"\" onclick=\"window.location='" .
				htmlspecialchars( Title::newMainPage()->getFullURL() ) . "'\"/>";
			if ( $user->isLoggedIn() ) {
				$output .= ' <input type="button" class="site-button" value="' .
					$this->msg( 'sportsteams-network-your-profile' )->plain() .
					"\" onclick=\"window.location='" .
					htmlspecialchars( Title::makeTitle( NS_USER, $user->getName() )->getFullURL() ) . "'\"/>";
			}
			$output .= '</div>';
			$out->addHTML( $output );
			return true;
		}

		$this->network_count = SportsTeams::getUserCount( $sport_id, $team_id );
		$this->friends_network_count = SportsTeams::getFriendsCountInFavorite(
			$user->getId(),
			$sport_id,
			$team_id
		);

		if ( $team_id ) {
			$team = SportsTeams::getTeam( $team_id );
			$this->network = $team['name'];
		} else {
			$sport = SportsTeams::getSport( $sport_id );
			$this->network = $sport['name'];
		}

		$team_image = SportsTeams::getLogo( $sport_id, $team_id, 'l' );

		$homepage_title = Title::makeTitle( NS_MAIN, $this->network );
		$view_fans_title = SpecialPage::getTitleFor( 'ViewFans' );
		$join_fans_title = SpecialPage::getTitleFor( 'AddFan' );
		$leave_fans_title = SpecialPage::getTitleFor( 'RemoveFan' );

		// Set the page title
		$out->setPageTitle( $this->msg( 'sportsteams-network-fan-network', $this->network )->text() );

		// Add CSS & JS
		$out->addModules( array( 'ext.sportsTeams', 'ext.sportsTeams.fanHome' ) );

		// Ashish Datta
		// Add the script for the maps and set the onload() handler
		// DON'T FORGET TO CHANGE KEY WHEN YOU CHANGE DOMAINS
		// @note As of 12 August 2011, http://code.google.com/apis/maps/documentation/javascript/v2/
		// states that the version 2 of Google Maps API has been deprecated.
		// Furthermore, as of 26 August 2013, https://developers.google.com/maps/documentation/javascript/v2/
		// states V2 of the Google Maps API will go away (de facto, anyway)
		// after 19 November 2013 after which users requesting V2 API will be
		// served a special, wrapped version of the V3 API
		$out->addScript( "<script src=\"http://maps.google.com/maps?file=api&amp;v=2.x&amp;key={$wgSportsTeamsGoogleAPIKey}\" type=\"text/javascript\"></script>" );
		$out->addScript( $this->getMap() );
		// this originally used setOnloadHandler; addOnloadHook() won't work
		$out->addScript( '<script>jQuery( document ).ready( function() { loadMap(); } );</script>' );

		// If the user is a member of this network, visually indicate that and
		// offer a link for leaving the network; otherwise if they're a logged-in
		// user who isn't a member of the network, offer them a "join" link
		if ( SportsTeams::isFan( $user->getId(), $sport_id, $team_id ) ) {
			$fan_info = '<p><span class="profile-on">' .
				$this->msg( 'sportsteams-network-you-are-fan' )->text() . '</span></p>';
			$fan_info .= '<p><span>';
			$fan_info .= Linker::link(
				$leave_fans_title,
				$this->msg( 'sportsteams-network-leave-network' )->text(),
				array( 'style' => 'text-decoration: none;' ),
				array( 'sport_id' => $sport_id, 'team_id' => $team_id )
			);
			$fan_info .= '</span></p>';
		} elseif ( $user->isLoggedIn() ) {
			$fan_info = '<p><span class="profile-on">';
			$fan_info .= Linker::link(
				$join_fans_title,
				$this->msg( 'sportsteams-network-join-network' )->text(),
				array( 'style' => 'text-decoration: none;' ),
				array( 'sport_id' => $sport_id, 'team_id' => $team_id )
			);
			$fan_info .= '</span></p>';
		}

		$output = '';

		$output .= '<div class="fan-top">';

		$output .= '<div class="fan-top-left">';
		$output .= '<h1>' . $this->msg( 'sportsteams-network-info' )->text() . '</h1>';
		$output .= '<div class="network-info-left">';
		$output .= $team_image;
		$output .= '<p>' . $this->msg( 'sportsteams-network-logo' )->text() . '</p>';
		$output .= '</div>';
		$output .= '<div class="network-info-right">';
		$output .= '<p>' . $this->msg( 'sportsteams-network-fans-col' )->text() . ' <a href="' .
			$view_fans_title->getFullURL(
				array(
					'sport_id' => $sport_id,
					'team_id' => $team_id
				)
			) . "\">{$this->network_count}</a></p>";
		// For registered users, show the amount of their friends who also
		// belong to this network
		if ( $user->isLoggedIn() ) {
			$output .= '<p>' . $this->msg(
				'sportsteams-network-friends-col'
			)->numParams( $this->friends_network_count )->parse() . '</p>';
		}
		$output .= $fan_info;
		$output .= '</div>';
		$output .= '<div class="visualClear"></div>';
		$output .= '</div>';
		$this_count = count( SportsTeams::getUsersByFavorite( $sport_id, $team_id, 7, 0 ) );
		$output .= '<div class="fan-top-right">';
		$output .= '<h1>' . $this->msg( 'sportsteams-network-fans', $this->network )->text() . '</h1>';
		$output .= '<p style="margin:-8px 0px 0px 0px; color:#797979;">' .
			$this->msg(
				'sportsteams-network-fan-display',
				$this_count,
				$view_fans_title->getFullURL( array(
					'sport_id' => $sport_id, 'team_id' => $team_id
				) ),
				$this->network_count
			)->text() . '</p>';
		$output .= $this->getFans();
		$output .= '</div>';

		$output .= '<div class="visualClear"></div>';
		$output .= '</div>';

		$output .= '<div class="fan-left">';

		// Latest Network User Updates
		$updates_show = 25;
		$s = new UserStatus();
		$output .= '<div class="network-updates">';
		$output .= '<h1 class="network-page-title">' .
			$this->msg( 'sportsteams-network-latest-thoughts' )->text() . '</h1>';
		$output .= '<div style="margin-bottom:10px;">
			<a href="' . SportsTeams::getFanUpdatesURL( $sport_id, $team_id ) . '">' .
				$this->msg( 'sportsteams-network-all-thoughts' )->text() . '</a>
		</div>';
		// Registered users (whether they're members of the network or not) can
		// post new status updates on the network's page from the network's
		// page
		if ( $user->isLoggedIn() ) {
			$output .= "\n<script type=\"text/javascript\">
				var __sport_id__ = {$sport_id};
				var __team_id__ = {$team_id};
				var __updates_show__ = {$updates_show};
				var __user_status_link__ = '" . SpecialPage::getTitleFor( 'UserStatus' )->getFullURL() . "';</script>\n";
			$output .= "<div class=\"user-status-form\">
				<span class=\"user-name-top\">{$user->getName()}</span> <input type=\"text\" name=\"user_status_text\" id=\"user_status_text\" size=\"40\" maxlength=\"150\" />
				<input id=\"add-status-btn\" type=\"button\" value=\"" . $this->msg( 'sportsteams-add-button' )->text() . '" class="site-button" />
			</div>';
		}
		$output .= '<div id="network-updates">';
		$output .= $s->displayStatusMessages(
			0, $sport_id, $team_id, $updates_show, 1/*$page*/
		);
		$output .= '</div>';

		$output .= '</div></div>';

		$output .= '<div class="fan-right">';

		// Network location map
		$output .= '<div class="fan-map">';
		$output .= '<h1 class="network-page-title">' .
			$this->msg( 'sportsteams-network-fan-locations' )->text() . '</h1>';
		$output .= '<div class="gMap" id="gMap"></div>
			<div class="gMapInfo" id="gMapInfo"></div>';
		$output .= '</div>';

		// Top network fans
		$output .= '<div class="top-fans">';
		$output .= '<h1 class="network-page-title">' .
			$this->msg( 'sportsteams-network-top-fans' )->text() . '</h1>';
		$tfr = SpecialPage::getTitleFor( 'TopUsersRecent' );
		/*
		$output .= "<p class=\"fan-network-sub-text\">
				<a href=\"" . htmlspecialchars( $tfr->getFullURL( 'period=weekly' )  ). '">' .
					$this->msg( 'sportsteams-network-top-fans-week' )->text() .
				"</a> -
				<a href=\"{$view_fans_title->getFullURL( array( 'sport_id' => $sport_id, 'team_id' => $team_id ))}\">" .
					$this->msg( 'sportsteams-network-complete-list' )->text() . '</a>
			</p>';
		*/
		$output .= '<p class="fan-network-sub-text">';
		$output .= Linker::link(
			$tfr,
			$this->msg( 'sportsteams-network-top-fans-week' )->plain(),
			array(),
			array( 'period' => 'weekly' )
		);
		$output .= ' - ';
		$output .= Linker::link(
			$view_fans_title,
			$this->msg( 'sportsteams-network-complete-list' )->plain(),
			array(),
			array( 'sport_id' => $sport_id, 'team_id' => $team_id )
		);
		$output .= '</p>';
		$output .= $this->getTopFans();
		$output .= '</div>';

		$output .= '<div class="network-articles">';
		$output .= '<h1 class="network-page-title">' .
			$this->msg( 'sportsteams-network-articles', $this->network )->text() . '</h1>';
		$output .= '<p class="fan-network-sub-text">';
		if ( class_exists( 'BlogPage' ) ) { // @todo CHECKME: is there any point in this check?
			$createBlogPage = SpecialPage::getTitleFor( 'CreateBlogPage' );
			$output .= Linker::link(
				$createBlogPage,
				$this->msg( 'sportsteams-network-write-article' )->text()
			);
			$output .= ' - ';
		}
		$output .= Linker::link(
			$homepage_title,
			$this->msg( 'sportsteams-network-main-page' )->text()
		);
		$output .= '</p>';
		$output .= $this->getArticles();
		$output .= '</div>';

		$output .= '</div>';
		$output .= '<div class="visualClear"></div>';

		$out->addHTML( $output );
	}

	/**
	 * Ashish Datta
	 * GMaps code
	 * TODO:
	 * - The team images need to be cleaned up.
	 * - The team logos need some shadows.
	 * - The Google Maps Geocoder produces weird results sometimes:
	 * ie: New York, California geocodes to somewhere in CA instead of failing.
	 */
	function getMap() {
		global $wgUploadPath;

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );

		// maybe error check this to make sure the file exists...
		if ( $team_id ) {
			$team_image = $wgUploadPath . '/teams_logos/' .
				SportsTeams::getTeamLogo( $team_id, 'l' );
		} else {
			$team_image = $wgUploadPath . '/sport_logos/' .
				SportsTeams::getSportLogo( $sport_id, 'l' );
		}

		// stores the userIDs for this network; needs to have some content to
		// prevent Database::makeList from chocking up in the DB call a few lines
		// below...
		$userIDs = array( 0 );
		$fanLocations = array(); // stores the locations on the map
		$fanStates = array(); // stores the states along with the fans from that state

		$markerCode = '';

		$output = '';

		$fans = SportsTeams::getUsersByFavorite( $sport_id, $team_id, 7, 0 );

		// go through all the fans for this network
		// grab their userIDs and save HTML for their mini-profiles
		foreach ( $fans as $fan ) {
			$fanInfo = array();

			$loopUser = Title::makeTitle( NS_USER, $fan['user_name'] );
			$avatar = new wAvatar( $fan['user_id'], 'l' );

			$out = "<p class=\"map-avatar-image\">
				<a href=\"{$loopUser->getFullURL()}\">{$avatar->getAvatarURL()}</a></p>
				<p class=\"map-avatar-info\"> <a href=\"{$loopUser->getFullURL()}\">{$fan['user_name']}</a>";

			$fanInfo['divHTML'] = $out;
			$fanInfo['URL'] = $loopUser->getFullURL();
			$fanInfo['user_name'] = $fan['user_name'];

			$userIDs[$fan['user_id']] = $fanInfo;
		}

		// Get the info about the fans; only select fans that have country info
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			'user_profile',
			array(
				'up_user_id', 'up_location_country', 'up_location_city',
				'up_location_state'
			),
			array(
				'up_user_id' => array_keys( $userIDs ),
				'up_location_country IS NOT NULL'
			),
			__METHOD__
		);

		foreach ( $res as $row ) {
			$topLoc = '';
			$loc = '';

			$userInfo = array();
			$userInfo['user_id'] = $row->up_user_id;
			$userInfo['user_name'] = $userIDs[$row->up_user_id]['user_name'];

			// case everything nicely
			$country = ucwords( strtolower( $row->up_location_country ) );
			$state = ucwords( strtolower( $row->up_location_state ) );
			$city = ucwords( strtolower( $row->up_location_city ) );

			// if the fan is in the US geocode by city, state
			if ( $country == 'United States' ) {
				// if the user's profile doesn't have a city, only use a state
				if ( strlen( $city ) > 0 && strlen( $state ) > 0 ) {
					$loc = $city . ', ' . $state;
					$topLoc = $state;
				} elseif ( strlen( $state ) > 0 ) {
					$loc = $state;
					$topLoc = $state;
				} else {
					$loc = $country;
					$topLoc = $country;
				}
			} else { // if they are non-US then geocode by city, country
				if ( strlen( $city ) > 0 && strlen( $country ) > 0 ) {
					$loc = $city . ', ' . $country;
					$topLoc = $country;
				} else {
					$loc = $country;
					$topLoc = $country;
				}
			}

			// build a hash table using higher locations as keys and arrays of fans as objects
			if ( !array_key_exists( $topLoc, $fanStates ) ) {
				$fanStates[$topLoc] = array();
				$fanStates[$topLoc][] = $userInfo;
			} else {
				$fanStates[$topLoc][] = $userInfo;
			}

			// htmlentities( $userIDs[$row->up_user_id]['divHTML'] )
			// JavaScript to place the marker
			//
			// @note Newlines and tab characters are trimmed from the HTML
			// since their presence would mess up the JS code
			$markerCode .= "geocoder.getLatLng( '" . $loc . "',
								function( point ) {
									if ( !point ) {
										geocoder.getLatLng( '" . $state . "',
											function( point ) {
												var nPoint = new GPoint( point.x + ( Math.random() * .12 ), point.y + ( Math.random() * .12 ) );
												var gMark = FanHome.createMarker( nPoint, \"" .
													str_replace( array( "\n", "\t" ), '', addslashes( $userIDs[$row->up_user_id]['divHTML'] ) ) .
													'<br />' . $loc . "</p>\", '" .
													$userIDs[$row->up_user_id]['URL'] . "', map
												);
												mgr.addMarker( gMark, 6 );
											}
										);
									} else {
							";

			// this is the first fan at $loc
			if ( !in_array( $loc, $fanLocations ) ) {
				$fanLocations[] = $loc;
			} else {
				// there is already a placemark at $loc so add some jitter
				$markerCode .= "var point = new GPoint( point.x + ( Math.random() * .1 ), point.y + ( Math.random() * .1 ) );";
			}

			$markerCode .= "var gMark = FanHome.createMarker(point, \"" .
				str_replace( array( "\n", "\t" ), '', addslashes( $userIDs[$row->up_user_id]['divHTML'] ) ) .
				'<br />' . $loc . "</p>\", '" .
				$userIDs[$row->up_user_id]['URL'] . "', map);
							mgr.addMarker( gMark, 6 );
							}} );	";

		}

		// helper function to compare the $fanStates objects
		function cmpFanStates( $a, $b ) {
			if ( $a['user_id'] < $b['user_id'] ) {
				return 1;
			} else {
				return -1;
			}
		}

		// at the state level markers include the 5 newest users
		foreach ( $fanStates as $state => $users ) {
			usort( $users, 'cmpFanStates' );

			$userList = '';

			for ( $i = 0; $i < ( count( $users ) < 5 ? count( $users ) : 5 ); $i++ ) {
				$userList .= $users[$i]['user_name'] . '<br />';
			}

			$markerCode .= "geocoder.getLatLng( '" . $state . "' ,
								function( point ) {
									if ( point ) {
										mgr.addMarker(

									FanHome.createTopMarker( point, '<div id=\"gMapStateInfo\" class=\"gMapStateInfo\"> <div class=\"fan-location-blurb-title\">" .
										$this->msg( 'sportsteams-network-newest', $state )->text() .
										"</div><div class=\"user-list\">" . $userList .
										"<div><div style=\"font-size:10px; color:#797979;\">" .
										$this->msg( 'sportsteams-network-clicktozoom' )->text() . "</div></div>', map ), 1, 5 );
									}
								}
							);";
		}

		// script
		$output .= "<script language=\"javascript\">var __team_image__ = \"{$team_image}\";


// loads everything onto the map
function loadMap() {
	if ( GBrowserIsCompatible() ) {
		var geocoder = new GClientGeocoder();
		var map = new GMap2( document.getElementById( 'gMap' ) );

		// make sure to clean things up
		window.onunload = GUnload;

		geocoder.setBaseCountryCode( 'US' );

		map.setCenter( new GLatLng( 37.0625, -95.677068 ), 3 );
		map.addControl( new GSmallZoomControl() );
		var mgr = new GMarkerManager( map );

		" . $markerCode . "

		mgr.refresh();
	}
}

</script>";

		return $output;
	}

	/**
	 * Get the articles related to this network (articles where at least one
	 * category matches the name of this network).
	 *
	 * @return String: HTML
	 */
	function getArticles() {
		global $wgMemc;

		// Try cache first
		$key = $wgMemc->makeKey( 'fanhome', 'network-articles', 'six' );
		$data = $wgMemc->get( $key );

		if ( $data != '' ) {
			wfDebugLog( 'FanHome', 'Got network articles from cache' );
			$articles = $data;
		} else {
			wfDebugLog( 'FanHome', 'Got network articles from DB' );
			$dbr = wfGetDB( DB_REPLICA );
			// Code sporked from Rob Church's NewestPages extension
			$res = $dbr->select(
				array( 'page', 'categorylinks' ),
				array(
					'page_namespace', 'page_id', 'page_title',
					'page_is_redirect'
				),
				array(
					'cl_from = page_id',
					'page_namespace' => ( defined( 'NS_BLOG' ) ? NS_BLOG : 500 ),
					'page_is_redirect' => 0,
					'cl_to ' . $dbr->buildLike(
						$dbr->anyString(),
						$this->network,
						$dbr->anyString()
					)
				),
				__METHOD__,
				array( 'ORDER BY' => 'page_id DESC', 'LIMIT' => 6 )
			);

			$articles = array();
			foreach ( $res as $row ) {
				$articles[] = array(
					'title' => $row->page_title,
					'id' => $row->page_id
				);
			}

			// Cache in memcached for 15 minutes
			$wgMemc->set( $key, $articles, 60 * 15 );
		}

		$html = '<div class="listpages-container">';
		if ( empty( $articles ) ) {
			$html .= $this->msg( 'sportsteams-no-articles' )->text();
		} else {
			foreach ( $articles as $article ) {
				$titleObj = Title::makeTitle( NS_BLOG, $article['title'] );
				$votes = self::getVotesForPage( $article['id'] );
				$html .= '<div class="listpages-item">
				<div class="listpages-votebox">
				<div class="listpages-votebox-number">' .
					$votes .
				'</div>
				<div class="listpages-votebox-text">' .
					$this->msg(
						'sportsteams-articles-votes',
						$votes
					)->parse() .
					'</div>
				</div>
				<a href="' . htmlspecialchars( $titleObj->getFullURL() ) . '">' .
					$titleObj->getText() .
				'</a>
			</div>
			<div class="visualClear"></div>';
			}
		}
		$html .= '</div>'; // .listpages-container

		return $html;
	}

	/**
	 * Get the amount (COUNT(*)) of votes for the given page, identified via
	 * its ID and cache this info in memcached for 15 minutes.
	 *
	 * Copypasta from extensions/BlogPage/BlogPage.php.
	 *
	 * @param $id Integer: page ID
	 * @return Integer: amount of votes
	 */
	public static function getVotesForPage( $id ) {
		global $wgMemc;

		// Try cache first
		$key = $wgMemc->makeKey( 'fanhome', 'vote', 'count' );
		$data = $wgMemc->get( $key );

		if ( $data != '' ) {
			wfDebugLog( 'FanHome', "Got vote count for the page with ID {$id} from cache" );
			$voteCount = $data;
		} else {
			wfDebugLog( 'FanHome', "Got vote count for the page with ID {$id} from DB" );
			$dbr = wfGetDB( DB_REPLICA );
			$voteCount = (int)$dbr->selectField(
				'Vote',
				'COUNT(*) AS count',
				array( 'vote_page_id' => intval( $id ) ),
				__METHOD__
			);
			// Store in memcached for 15 minutes
			$wgMemc->set( $key, $voteCount, 60 * 15 );
		}

		return $voteCount;
	}

	function getRelationships( $rel_type ) {
		$rel = new UserRelationship( $this->getUser()->getName() );
		$relationships = $rel->getRelationshipIDs( $rel_type );
		return $relationships;
	}

	function getTopFans() {
		$lang = $this->getLanguage();
		$request = $this->getRequest();

		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );

		$output = '<div class="top-fans">';
		$fans = SportsTeams::getUsersByPoints( $sport_id, $team_id, 15, 0 );
		$x = 1;

		foreach ( $fans as $fan ) {
			$user = Title::makeTitle( NS_USER, $fan['user_name'] );
			$user_name = $fan['user_name'];
			$user_name_short = $lang->truncate( $user_name, 12 );
			$avatar = new wAvatar( $fan['user_id'], 'm' );
			$output .= "<div class=\"top-fan-row\">
				<span class=\"top-fan-num\">{$x}.</span> <span class=\"top-fan\">" .
					$avatar->getAvatarURL() . ' <a href="' . $user->getFullURL() . '">' .
						$user_name_short .
					'</a>
				</span>
				<span class="top-fan-points"><b>' .
					$this->msg(
						'sportsteams-network-points',
						$lang->formatNum( $fan['points'] )
					)->parse() . '</b></span>
			</div>';
			$x++;
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Display the names and avatars of users who are fans of a given sport or
	 * team.
	 *
	 * @return String: HTML
	 */
	function getFans() {
		$lang = $this->getLanguage();
		$request = $this->getRequest();

		$sport_id = $request->getInt( 'sport_id' );
		$team_id = $request->getInt( 'team_id' );

		$output = '<div class="fans">';
		$fans = SportsTeams::getUsersByFavorite( $sport_id, $team_id, 7, 0 );
		foreach ( $fans as $fan ) {
			$user = Title::makeTitle( NS_USER, $fan['user_name'] );
			$avatar = new wAvatar( $fan['user_id'], 'l' );

			$fan_name = $lang->truncate( $fan['user_name'], 12 );

			$output .= "<p class=\"fan\">
				<a href=\"{$user->getFullURL()}\">{$avatar->getAvatarURL()}</a><br>
				<a href=\"{$user->getFullURL()}\">{$fan_name}</a>
			</p>";
		}

		$output .= '<div class="visualClear"></div></div>';

		return $output;
	}

}