<?php
/**
 * Base class for managing data.
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class SportsTeams {

	/**
	 * @var User The user (object) performing actions like adding favorites
	 */
	public $user;

	/**
	 * Constructor
	 *
	 * @param User $user
	 */
	public function __construct( $user ) {
		$this->user = $user;
	}

	/**
	 * Add a sport to the database.
	 *
	 * @param string $sport_name User-supplied name of the sport
	 * @param string $sport_order
	 * @return int
	 */
	public function addSport( $sport_name, $sport_order = '' ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$dbw->insert(
			'sport',
			[
				'sport_name' => $sport_name,
				'sport_order' => $sport_order
			],
			__METHOD__
		);

		return $dbw->insertId();
	}

	/**
	 * Edit a pre-existing sport.
	 *
	 * @param int $sport_id Unique identifier of the sport
	 * @param string $sport_name User-supplied name of the sport
	 * @param string $sport_order
	 */
	public function editSport( $sport_id, $sport_name, $sport_order = '' ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$dbw->update(
			'sport',
			[
				'sport_name' => $sport_name,
				'sport_order' => $sport_order
			],
			[ 'sport_id' => intval( $sport_id ) ],
			__METHOD__
		);
	}

	/**
	 * Get all sports available in the database.
	 *
	 * @return array
	 */
	public static function getSports() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$res = $dbr->select(
			'sport',
			[ 'sport_id', 'sport_name' ],
			[],
			__METHOD__,
			[ 'ORDER BY' => 'sport_order' ]
		);

		$sports = [];
		foreach ( $res as $row ) {
			$sports[] = [
				'id' => $row->sport_id,
				'name' => $row->sport_name
			];
		}

		return $sports;
	}

	/**
	 * Get all teams for the given sport.
	 *
	 * @param int $sportId Sport ID
	 * @return array Array containing each team's name and internal ID number
	 */
	public static function getTeams( $sportId ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$res = $dbr->select(
			'sport_team',
			[ 'team_id', 'team_name', 'team_sport_id' ],
			[ 'team_sport_id' => intval( $sportId ) ],
			__METHOD__,
			[ 'ORDER BY' => 'team_name' ]
		);

		$teams = [];

		foreach ( $res as $row ) {
			$teams[] = [
				'id' => $row->team_id,
				'name' => $row->team_name
			];
		}

		return $teams;
	}

	/**
	 * Get information about a given team, like to which sport it belongs, what is
	 * its ID and name.
	 *
	 * @param int $teamId Team ID
	 * @return array
	 */
	public static function getTeam( $teamId ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$res = $dbr->select(
			'sport_team',
			[ 'team_id', 'team_name', 'team_sport_id' ],
			[ 'team_id' => intval( $teamId ) ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		$teams = [];

		foreach ( $res as $row ) {
			$teams[] = [
				'id' => $row->team_id,
				'name' => $row->team_name,
				'sport_id' => $row->team_sport_id
			];
		}

		return $teams[0];
	}

	/**
	 * Get the name (and the already supplied ID) of the given sport (ID).
	 *
	 * @param int $sportId Sport ID
	 * @return array
	 */
	public static function getSport( $sportId ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$res = $dbr->select(
			'sport',
			[ 'sport_id', 'sport_name' ],
			[ 'sport_id' => intval( $sportId ) ],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		$sports = [];

		foreach ( $res as $row ) {
			$sports[] = [
				'id' => $row->sport_id,
				'name' => $row->sport_name
			];
		}

		return $sports[0];
	}

	/**
	 * Given a sport ID and optionally also a team ID, gets the network name (team's name).
	 * When no team ID is provided, gets the sport's name.
	 *
	 * @param int $sport_id Sport ID
	 * @param int|null $team_id Team ID [optional]
	 * @return string
	 */
	public static function getNetworkName( $sport_id, $team_id ) {
		if ( $team_id ) {
			$network = self::getTeam( $team_id );
		} else {
			$network = self::getSport( $sport_id );
		}

		return $network['name'];
	}

	/**
	 * Add a sport or a sport+team combination as a favorite for the current user.
	 *
	 * @param int $sport_id Sport identifier
	 * @param int $team_id Team identifier [optional]
	 */
	public function addFavorite( $sport_id, $team_id ) {
		if ( $this->user->isRegistered() ) {
			if ( !self::isFan( $this->user, $sport_id, $team_id ) ) {
				$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
				$dbw->insert(
					'sport_favorite',
					[
						'sf_sport_id' => $sport_id,
						'sf_team_id' => $team_id,
						'sf_actor' => $this->user->getActorId(),
						'sf_order' => ( $this->getUserFavoriteTotal( $this->user ) + 1 ),
						'sf_date' => $dbw->timestamp( date( 'Y-m-d H:i:s' ) )
					],
					__METHOD__
				);
				self::clearUserCache( $this->user );
			}
		}
	}

	/**
	 * Clear cache for the given user (object).
	 *
	 * @param User $user
	 */
	public static function clearUserCache( $user ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'user', 'teams', 'actor_id', $user->getActorId() );
		$cache->delete( $key );
	}

	/**
	 * Get the current user's favorite sports and teams, either from cache or from
	 * the DB, and then store 'em in cache.
	 *
	 * @return array
	 */
	public function getUserFavorites() {
		// Try cache first
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$actorId = $this->user->getActorId();
		$key = $cache->makeKey( 'user', 'teams', 'actor_id', $actorId );
		$data = $cache->get( $key );

		if ( $data ) {
			wfDebugLog( 'SportsTeams', "Got favorite teams for actor ID {$actorId} from cache" );
			$favs = $data;
		} else {
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			wfDebugLog( 'SportsTeams', "Got favorite teams for actor ID {$actorId} from DB" );

			$res = $dbr->select(
				[ 'sport_favorite', 'sport', 'sport_team' ],
				[
					'sport_id', 'sport_name', 'team_id', 'team_name',
					'sf_actor', 'sf_order'
				],
				[ 'sf_actor' => intval( $actorId ) ],
				__METHOD__,
				[ 'ORDER BY' => 'sf_order' ],
				[
					'sport' => [ 'INNER JOIN', 'sf_sport_id = sport_id' ],
					'sport_team' => [ 'LEFT JOIN', 'sf_team_id = team_id' ]
				]
			);

			$favs = [];

			foreach ( $res as $row ) {
				$favs[] = [
					'sport_id' => $row->sport_id,
					'sport_name' => $row->sport_name,
					'team_id' => ( ( !$row->team_id ) ? 0 : $row->team_id ),
					'team_name' => $row->team_name,
					'order' => $row->sf_order
				];
			}

			$cache->set( $key, $favs );
		}

		return $favs;
	}

	/**
	 * Get the full <img> tag for the given sport team's logo image.
	 *
	 * @param int $sport_id Sport ID number
	 * @param int|bool $team_id Team ID number, 0 by default
	 * @param string $size 's' for small, 'm' for medium, 'ml' for
	 *                      medium-large and 'l' for large
	 * @return string Full <img> tag
	 */
	public static function getLogo( $sport_id, $team_id, $size ) {
		global $wgUploadPath;

		if ( $sport_id > 0 && $team_id == 0 ) {
			$logoTag = '<img src="' . $wgUploadPath . '/sport_logos/' .
				self::getSportLogo( $sport_id, $size ) .
				'" border="0" alt="" />';
		} else {
			$logoTag = '<img src="' . $wgUploadPath . '/team_logos/' .
				self::getTeamLogo( $team_id, $size ) .
				'" border="0" alt="" />';
		}

		return $logoTag;
	}

	/**
	 * Get the name of the logo image for a given sports team (identified via
	 * its ID number).
	 *
	 * @param int $id Sport team ID number
	 * @param string $size 's' for small, 'm' for medium, 'ml' for
	 *                      medium-large and 'l' for large
	 * @return string Team logo image filename
	 */
	public static function getTeamLogo( $id, $size ) {
		global $wgUploadDirectory;

		$files = glob(
			$wgUploadDirectory . '/team_logos/' . $id . '_' . $size . '*'
		);

		if ( empty( $files[0] ) ) {
			$filename = 'default_' . $size . '.gif';
		} else {
			$filename = basename( $files[0] );
		}

		return $filename;
	}

	/**
	 * Get the name of the logo image for a given sport (identified via
	 * its ID number).
	 *
	 * @param int $id Sport ID number
	 * @param string $size 's' for small, 'm' for medium, 'ml' for
	 *                      medium-large and 'l' for large
	 * @return string Sport logo image filename
	 */
	public static function getSportLogo( $id, $size ) {
		global $wgUploadDirectory;

		$files = glob(
			$wgUploadDirectory . '/sport_logos/' . $id . '_' . $size . '*'
		);

		if ( empty( $files[0] ) ) {
			$filename = 'default_' . $size . '.gif';
		} else {
			$filename = basename( $files[0] );
		}

		return $filename;
	}

	/**
	 * Get users who are fans of the given sport and/or team.
	 *
	 * @param int $sport_id Sport identifier
	 * @param int $team_id Team identifier [optional]
	 * @param int $limit LIMIT for the SQL query [optional]
	 * @param int $page Used for calculating the OFFSET for the SQL query [optional]
	 * @return array
	 */
	public static function getUsersByFavorite( $sport_id, $team_id, $limit, $page ) {
		// Try cache first
		//$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		//$key = $cache->makeKey( 'user', 'teams', $user_id );
		#$cache->delete( $key );
		//$data = $cache->get( $key );
		//if ( $data ) {
		//	wfDebugLog( 'SportsTeams', "Got favorite teams for {$user_id} from cache" );
		//	$favs = $data;
		//} else {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$where = $options = [];

		if ( $limit > 0 ) {
			$offset = 0;
			if ( $page ) {
				$offset = $page * $limit - ( $limit );
			}
			$options['OFFSET'] = intval( $offset );
			$options['LIMIT'] = intval( $limit );
		}
		if ( !$team_id ) {
			$where['sf_sport_id'] = intval( $sport_id );
			// @see the note in getUserCount() as to why this is commented out
			// $where['sf_team_id'] = 0;
		} else {
			$where['sf_team_id'] = intval( $team_id );
		}

		$res = $dbr->select(
			[ 'sport_favorite', 'sport', 'sport_team', 'actor' ],
			[
				'sport_id', 'sport_name', 'team_id', 'team_name',
				'sf_actor', 'sf_order', 'actor_name', 'actor_user'
			],
			$where,
			__METHOD__,
			$options,
			[
				'sport' => [ 'INNER JOIN', 'sf_sport_id = sport_id' ],
				'sport_team' => [ 'LEFT JOIN', 'sf_team_id = team_id' ],
				'actor' => [ 'JOIN', 'sf_actor = actor_id' ]
			]
		);

		$fans = [];

		foreach ( $res as $row ) {
			$fans[] = [
				'actor' => $row->sf_actor,
				'user_id' => $row->actor_user,
				'user_name' => $row->actor_name
			];
		}
		// $cache->set( $key, $favs );
		//}
		return $fans;
	}

	/**
	 * Used on Special:SimilarFans to get the count of users who have similar interests
	 * as the current user.
	 *
	 * @param int $limit LIMIT for the SQL query
	 * @param int $page OFFSET for the SQL query
	 * @return array Array containing similar users' user IDs and user names, or
	 *   an empty array if no users are similar to the current user
	 */
	public function getSimilarUsers( $limit = 0, $page = 0 ) {
		$actorId = $this->user->getActorId();
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$offset = 0;
		if ( $limit > 0 && $page ) {
			$offset = $page * $limit - ( $limit );
		}

		/*
		$teamRes = $dbr->select(
			'sport_favorite',
			'sf_team_id',
			[ 'sf_actor' => $actorId ],
			__METHOD__
		);

		$teamIds = [];
		foreach ( $teamRes as $teamRow ) {
			$teamIds[] = $teamRow->sf_team_id;
		}
		*/
		// need sf_id for PostgreSQL, otherwise we get this DB error:
		// "Error: 42P10 ERROR: for SELECT DISTINCT, ORDER BY expressions must appear in select list"
		$sql = "SELECT DISTINCT(sf_actor), sf_id, actor_name, actor_user
			FROM {$dbr->tableName( 'sport_favorite' )}
			JOIN {$dbr->tableName( 'actor' )} ON sf_actor = actor_id
			WHERE sf_team_id IN
				(SELECT sf_team_id FROM {$dbr->tableName( 'sport_favorite' )} WHERE sf_actor = {$actorId})
			AND sf_team_id <> 0 AND sf_actor <> {$actorId}
			ORDER BY sf_id DESC";

		$res = $dbr->query( $dbr->limitResult( $sql, $limit, $offset ), __METHOD__ );
		$fans = [];

		foreach ( $res as $row ) {
			$fans[] = [
				'user_id' => $row->actor_user,
				'user_name' => $row->actor_name
			];
		}

		return $fans;
	}

	/**
	 * Get the top fans (users who have the most points) for a given sport or
	 * sport+team combo.
	 *
	 * @param int $sport_id Sport identifier
	 * @param int $team_id Team identifier [optional]
	 * @param int $limit LIMIT for the SQL query, i.e. get this many users
	 * @param int $page OFFSET for the SQL query, for pagination [optional]
	 * @return array
	 */
	public static function getUsersByPoints( $sport_id, $team_id, $limit, $page ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$where = $options = [];

		if ( $limit > 0 ) {
			$offset = 0;
			if ( $page ) {
				$offset = $page * $limit - ( $limit );
			}
			$options['OFFSET'] = intval( $offset );
			$options['LIMIT'] = intval( $limit );
		}

		if ( !$team_id ) {
			$where['sf_sport_id'] = intval( $sport_id );
			$where['sf_team_id'] = 0;
		} else {
			$where['sf_team_id'] = intval( $team_id );
		}

		$res = $dbr->select(
			[ 'sport_favorite', 'sport', 'sport_team', 'user_stats', 'actor' ],
			[
				'sport_id', 'sport_name', 'team_id', 'team_name',
				'sf_actor', 'sf_order', 'stats_total_points',
				'actor_name', 'actor_user'
			],
			$where,
			__METHOD__,
			$options,
			[
				'sport' => [ 'INNER JOIN', 'sf_sport_id = sport_id' ],
				'sport_team' => [ 'LEFT JOIN', 'sf_team_id = team_id' ],
				'user_stats' => [ 'LEFT JOIN', 'sf_actor = stats_actor' ],
				'actor' => [ 'JOIN', 'sf_actor = actor_id' ]
			]
		);

		$fans = [];

		foreach ( $res as $row ) {
			$fans[] = [
				'user_id' => $row->actor_user,
				'user_name' => $row->actor_name,
				'points' => $row->stats_total_points
			];
		}

		return $fans;
	}

	/**
	 * Get the total amount of users who are fans of the given sport and/or team.
	 *
	 * @param int $sport_id Sport identifier
	 * @param int $team_id Team identifier [optional]
	 * @return int
	 */
	public static function getUserCount( $sport_id, $team_id ) {
		if ( !$team_id ) {
			$where = [
				'sf_sport_id' => $sport_id,
				// ashley 20 January 2020: the counts are off if we specify this condition.
				// In my test case, the DB has one sport and one team; User:Foo is a fan of
				// both the team and thus (at least implicitly, if not explicitly) the sport.
				// sport_favorite table has thus a non-zero value for both sf_sport_id _and_
				// sf_team_id. Specifying sf_team_id as zero here basically excludes User:Foo
				// and with ?sport_id=1 in the URL, the Special:ViewFans page erroneously
				// claims that the given sport has no fans when it has one fan.
				// 'sf_team_id' => 0
			];
		} else {
			$where = [ 'sf_team_id' => $team_id ];
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$count = (int)$dbr->selectField(
			'sport_favorite',
			'COUNT(*) AS the_count',
			$where,
			__METHOD__
		);

		return $count;
	}

	/**
	 * Get the amount of favorites the given user has in total.
	 *
	 * @param User $user
	 * @return int
	 */
	public function getUserFavoriteTotal( $user ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = (int)$dbr->selectField(
			'sport_favorite',
			'COUNT(*) AS the_count',
			[ 'sf_actor' => intval( $user->getActorId() ) ],
			__METHOD__
		);
		return $res;
	}

	/**
	 * How many of the given user's friends are fans of the given sport (and/or team)?
	 *
	 * @param User $user
	 * @param int $sport_id Sport identifier
	 * @param int $team_id Team identifier [optional]
	 * @return int
	 */
	public static function getFriendsCountInFavorite( $user, $sport_id, $team_id ) {
		$where = [];
		if ( !$team_id ) {
			$where = [
				'sf_sport_id' => $sport_id,
				'sf_team_id' => 0
			];
		} else {
			$where = [ 'sf_team_id' => $team_id ];
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$friends = $dbr->select(
			'user_relationship',
			'r_actor_relation',
			[ 'r_actor' => $user->getActorId(), 'r_type' => 1 ],
			__METHOD__
		);

		$actorIds = [];
		foreach ( $friends as $friend ) {
			$actorIds[] = $friend->r_actor_relation;
		}

		if ( $actorIds ) {
			$ourWhere = array_merge(
				$where,
				// @see https://www.mediawiki.org/wiki/Special:Code/MediaWiki/92016#c19527
				[ 'sf_actor' => $actorIds ]
			);
			$count = (int)$dbr->selectField(
				'sport_favorite',
				'COUNT(*) AS the_count',
				$ourWhere,
				__METHOD__
			);
		} else {
			$count = 0;
		}

		return $count;
	}

	/**
	 * How many users are similar to the given user, ie. are fans of the same teams?
	 *
	 * @param User $user
	 * @return int
	 */
	public static function getSimilarUserCount( $user ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$teamIdQuery = $dbr->select(
			'sport_favorite',
			'sf_team_id',
			[ 'sf_actor' => $user->getActorId() ],
			__METHOD__
		);

		$teamIds = [];
		foreach ( $teamIdQuery as $teamId ) {
			$teamIds[] = $teamId->sf_team_id;
		}

		if ( $teamIds ) {
			$count = (int)$dbr->selectField(
				'sport_favorite',
				'COUNT(*) AS the_count',
				[
					'sf_team_id' => $teamIds,
					'sf_team_id <> 0',
					"sf_actor <> {$user->getActorId()}"
				],
				__METHOD__
			);
		} else {
			$count = 0;
		}

		return $count;
	}

	/**
	 * Is the given user a fan of the given sports team?
	 *
	 * @param User $user User who is being checked
	 * @param int $sport_id Sport ID number
	 * @param int $team_id Team ID number [optional]
	 * @return bool True if the user is a fan, otherwise false
	 */
	public static function isFan( $user, $sport_id, $team_id ) {
		$where = [ 'sf_actor' => $user->getActorId() ];
		if ( !$team_id ) {
			$where['sf_sport_id'] = $sport_id;
			$where['sf_team_id'] = 0;
		} else {
			$where['sf_team_id'] = $team_id;
		}

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$row = $dbr->selectField(
			'sport_favorite',
			'sf_id',
			$where,
			__METHOD__
		);

		if ( !$row ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Remove the given sport or team (via its internal numeric identifier) from
	 * the favorites for the user who was passed to the class' constructor.
	 *
	 * @param int $sport_id Sport identifier
	 * @param int $team_id Team identifier [optional]
	 */
	public function removeFavorite( $sport_id, $team_id ) {
		$actorId = (int)$this->user->getActorId();
		$sport_id = (int)$sport_id;
		$team_id = (int)$team_id;
		if ( !$team_id ) {
			$where = [
				'sf_actor' => $actorId,
				'sf_sport_id' => $sport_id,
				'sf_team_id' => 0
			];
		} else {
			$where = [
				'sf_actor' => $actorId,
				'sf_team_id' => $team_id
			];
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		// Get the order of team being deleted;
		$order = (int)$dbw->selectField( 'sport_favorite', 'sf_order', $where, __METHOD__ );

		// Update orders for those less than one being deleted
		$res = $dbw->update(
			'sport_favorite',
			[ 'sf_order = sf_order - 1' ],
			[ 'sf_actor' => $actorId, "sf_order > {$order}" ],
			__METHOD__
		);

		// Finally we can remove the fav
		$dbw->delete( 'sport_favorite', $where, __METHOD__ );
	}

	/**
	 * @param int $date1
	 * @param int $date2
	 * @return array
	 */
	public static function dateDiff( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;

		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif = [];
		$dif['w'] = intval( $totalDays / 7 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

		return $dif;
	}

	/**
	 * @param array $time
	 * @param string $timeabrv
	 * @param string $timename
	 * @return string
	 */
	public static function getTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = '';
		if ( $time[$timeabrv] > 0 ) {
			$timeStr = wfMessage( "sportsteams-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if ( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	/**
	 * @param int $time
	 * @return string
	 */
	public static function getTimeAgo( $time ) {
		$timeArray = self::dateDiff( time(), $time );
		$timeStrD = self::getTimeOffset( $timeArray, 'd', 'days' );
		$timeStrH = self::getTimeOffset( $timeArray, 'h', 'hours' );
		$timeStrM = self::getTimeOffset( $timeArray, 'm', 'minutes' );
		$timeStrS = self::getTimeOffset( $timeArray, 's', 'seconds' );
		$timeStr = $timeStrD;
		if ( $timeStr < 2 ) {
			$timeStr .= $timeStrH;
			$timeStr .= $timeStrM;
			if ( !$timeStr ) {
				$timeStr .= $timeStrS;
			}
		}
		if ( !$timeStr ) {
			$timeStr = wfMessage( 'sportsteams-time-seconds', 1 )->parse();
		}
		return $timeStr;
	}
}
