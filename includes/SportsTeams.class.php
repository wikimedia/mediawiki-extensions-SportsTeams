<?php
/**
 * Base class for managing data.
 *
 * @file
 * @ingroup Extensions
 */
class SportsTeams {

	/**
	 * Constructor
	 * @private
	 */
	/* private */ function __construct() { }

	/**
	 * Add a sport to the database.
	 *
	 * @param $sport_name String: user-supplied name of the sport
	 * @param $sport_order
	 */
	function addSport( $sport_name, $sport_order = '' ) {
		$dbw = wfGetDB( DB_MASTER );

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
	 * @param $sport_id Integer: unique identifier of the sport
	 * @param $sport_name String: user-supplied name of the sport
	 * @param $sport_order
	 */
	function editSport( $sport_id, $sport_name, $sport_order = '' ) {
		$dbw = wfGetDB( DB_MASTER );

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
	 * @return Array
	 */
	static function getSports() {
		$dbr = wfGetDB( DB_MASTER );

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
	 * @param $sportId Integer: sport ID
	 * @return Array: array containing each team's name and internal ID number
	 */
	static function getTeams( $sportId ) {
		$dbr = wfGetDB( DB_REPLICA );

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

	static function getTeam( $teamId ) {
		$dbr = wfGetDB( DB_MASTER );

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

	static function getSport( $sportId ) {
		$dbr = wfGetDB( DB_MASTER );

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

	static function getNetworkName( $sport_id, $team_id ) {
		if ( $team_id ) {
			$network = SportsTeams::getTeam( $team_id );
		} else {
			$network = SportsTeams::getSport( $sport_id );
		}

		return $network['name'];
	}

	public function addFavorite( $user_id, $sport_id, $team_id ) {
		if ( $user_id > 0 ) {
			$user = User::newFromId( $user_id );
			$user_name = $user->getName();

			if ( !$this->isFan( $user_id, $sport_id, $team_id ) ) {
				$dbw = wfGetDB( DB_MASTER );
				$dbw->insert(
					'sport_favorite',
					[
						'sf_sport_id' => $sport_id,
						'sf_team_id' => $team_id,
						'sf_user_id' => $user_id,
						'sf_user_name' => $user_name,
						'sf_order' => ( $this->getUserFavoriteTotal( $user_id ) + 1 ),
						'sf_date' => date( 'Y-m-d H:i:s' )
					],
					__METHOD__
				);
				$this->clearUserCache( $user_id );
			}
		}
	}

	static function clearUserCache( $user_id ) {
		global $wgMemc;
		$key = $wgMemc->makeKey( 'user', 'teams', $user_id );
		$data = $wgMemc->delete( $key );
	}

	static function getUserFavorites( $user_id, $order = 0 ) {
		global $wgMemc;

		// Try cache first
		$key = $wgMemc->makeKey( 'user', 'teams', $user_id );
		$data = $wgMemc->get( $key );

		if ( $data ) {
			wfDebugLog( 'SportsTeams', "Got favorite teams for {$user_id} from cache" );
			$favs = $data;
		} else {
			$dbr = wfGetDB( DB_MASTER );
			wfDebugLog( 'SportsTeams', "Got favorite teams for {$user_id} from DB" );

			$res = $dbr->select(
				[ 'sport_favorite', 'sport', 'sport_team' ],
				[
					'sport_id', 'sport_name', 'team_id', 'team_name',
					'sf_user_id', 'sf_user_name', 'sf_order'
				],
				[ 'sf_user_id' => intval( $user_id ) ],
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

			$wgMemc->set( $key, $favs );
		}

		return $favs;
	}

	/**
	 * Get the full <img> tag for the given sport team's logo image.
	 *
	 * @param $sport_id Integer: sport ID number
	 * @param $team_id Integer: team ID number, 0 by default
	 * @param $size String: 's' for small, 'm' for medium, 'ml' for
	 *                      medium-large and 'l' for large
	 * @return String: full <img> tag
	 */
	static function getLogo( $sport_id, $team_id = 0, $size ) {
		global $wgUploadPath;

		if ( $sport_id > 0 && $team_id == 0 ) {
			$logoTag = '<img src="' . $wgUploadPath . '/sport_logos/' .
				SportsTeams::getSportLogo( $sport_id, $size ) .
				'" border="0" alt="" />';
		} else {
			$logoTag = '<img src="' . $wgUploadPath . '/team_logos/' .
				SportsTeams::getTeamLogo( $team_id, $size ) .
				'" border="0" alt="" />';
		}

		return $logoTag;
	}

	/**
	 * Get the name of the logo image for a given sports team (identified via
	 * its ID number).
	 *
	 * @param $id Integer: sport team ID number
	 * @param $size String: 's' for small, 'm' for medium, 'ml' for
	 *                      medium-large and 'l' for large
	 * @return String: team logo image filename
	 */
	static function getTeamLogo( $id, $size ) {
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
	 * @param $id Integer: sport ID number
	 * @param $size String: 's' for small, 'm' for medium, 'ml' for
	 *                      medium-large and 'l' for large
	 * @return String: sport logo image filename
	 */
	static function getSportLogo( $id, $size ) {
		global $wgUploadDirectory;

		$files = glob(
			$wgUploadDirectory . '/sport_logos/' . $id .  '_' . $size . '*'
		);

		if ( empty( $files[0] ) ) {
			$filename = 'default_' . $size . '.gif';
		} else {
			$filename = basename( $files[0] );
		}

		return $filename;
	}

	static function getUsersByFavorite( $sport_id, $team_id, $limit, $page ) {
		global $wgMemc;

		// Try cache first
		//$key = $wgMemc->makeKey( 'user', 'teams', $user_id );
		#$wgMemc->delete( $key );
		//$data = $wgMemc->get( $key );
		//if ( $data ) {
		//	wfDebugLog( 'SportsTeams', "Got favorite teams for {$user_id} from cache" );
		//	$favs = $data;
		//} else {
			$dbr = wfGetDB( DB_REPLICA );
			$where = $options = [];

			if ( $limit > 0 ) {
				$limitvalue = 0;
				if ( $page ) {
					$limitvalue = $page * $limit - ( $limit );
				}
				//$limit_sql = " LIMIT {$limitvalue},{$limit} ";
				$options['OFFSET'] = intval( $limitvalue );
				$options['LIMIT'] = intval( $limit );
			}
			if ( !$team_id ) {
				$where['sf_sport_id'] = intval( $sport_id );
				$where['sf_team_id'] = 0;
			} else {
				$where['sf_team_id'] = intval( $team_id );
			}

			$res = $dbr->select(
				[ 'sport_favorite', 'sport', 'sport_team' ],
				[
					'sport_id', 'sport_name', 'team_id', 'team_name',
					'sf_user_id', 'sf_user_name', 'sf_order'
				],
				$where,
				__METHOD__,
				$options,
				[
					'sport' => [ 'INNER JOIN', 'sf_sport_id = sport_id' ],
					'sport_team' => [ 'LEFT JOIN', 'sf_team_id = team_id' ]
				]
			);

			$fans = [];

			foreach ( $res as $row ) {
				$fans[] = [
					'user_id' => $row->sf_user_id,
					'user_name' => $row->sf_user_name
				];
			}
			//$wgMemc->set( $key, $favs );
		//}
		return $fans;
	}

	static function getSimilarUsers( $user_id, $limit = 0, $page = 0 ) {
		$dbr = wfGetDB( DB_MASTER );

		if ( $limit > 0 ) {
			$limitvalue = 0;
			if ( $page ) {
				$limitvalue = $page * $limit - ( $limit );
			}
			$limit_sql = " LIMIT {$limitvalue},{$limit} ";
		}

		/*
		$teamRes = $dbr->select(
			'sport_favorite',
			'sf_team_id',
			[ 'sf_user_id' => $user_id ],
			__METHOD__
		);

		$teamIds = [];
		foreach ( $teamRes as $teamRow ) {
			$teamIds[] = $teamRow->sf_team_id;
		}
		*/
		$sql = "SELECT DISTINCT(sf_user_id),sf_user_name
			FROM {$dbr->tableName( 'sport_favorite' )}
			WHERE sf_team_id IN
				(SELECT sf_team_id FROM {$dbr->tableName( 'sport_favorite' )} WHERE sf_user_id ={$user_id})
			AND sf_team_id <> 0 AND sf_user_id <> {$user_id}
			ORDER BY sf_id DESC
			{$limit_sql}";

		$res = $dbr->query( $sql, __METHOD__ );
		$fans = [];

		foreach ( $res as $row ) {
			$fans[] = [
				'user_id' => $row->sf_user_id,
				'user_name' => $row->sf_user_name
			];
		}

		return $fans;
	}

	static function getUsersByPoints( $sport_id, $team_id, $limit, $page ) {
		$dbr = wfGetDB( DB_REPLICA );
		$where = $options = [];

		if ( $limit > 0 ) {
			$limitvalue = 0;
			if ( $page ) {
				$limitvalue = $page * $limit - ( $limit );
			}
			$options['OFFSET'] = intval( $limitvalue );
			$options['LIMIT'] = intval( $limit );
		}

		if ( !$team_id ) {
			$where['sf_sport_id'] = intval( $sport_id );
			$where['sf_team_id'] = 0;
		} else {
			$where['sf_team_id'] = intval( $team_id );
		}

		$res = $dbr->select(
			[ 'sport_favorite', 'sport', 'sport_team', 'user_stats' ],
			[
				'sport_id', 'sport_name', 'team_id', 'team_name',
				'sf_user_id', 'sf_user_name', 'sf_order', 'stats_total_points'
			],
			$where,
			__METHOD__,
			$options,
			[
				'sport' => [ 'INNER JOIN', 'sf_sport_id = sport_id' ],
				'sport_team' => [ 'LEFT JOIN', 'sf_team_id = team_id' ],
				'user_stats' => [ 'LEFT JOIN', 'sf_user_id = stats_user_id' ]
			]
		);

		$fans = [];

		foreach ( $res as $row ) {
			$fans[] = [
				'user_id' => $row->sf_user_id,
				'user_name' => $row->sf_user_name,
				'points' => $row->stats_total_points
			];
		}

		return $fans;
	}

	static function getUserCount( $sport_id, $team_id ) {
		if ( !$team_id ) {
			$where = [
				'sf_sport_id' => $sport_id,
				'sf_team_id' => 0
			];
		} else {
			$where = [ 'sf_team_id' => $team_id ];
		}

		$dbr = wfGetDB( DB_REPLICA );
		$count = (int)$dbr->selectField(
			'sport_favorite',
			'COUNT(*) AS the_count',
			$where,
			__METHOD__
		);

		return $count;
	}

	static function getUserFavoriteTotal( $userId ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = (int)$dbr->selectField(
			'sport_favorite',
			'COUNT(*) AS the_count',
			[ 'sf_user_id' => intval( $userId ) ],
			__METHOD__
		);
		return $res;
	}

	static function getFriendsCountInFavorite( $user_id, $sport_id, $team_id ) {
		$where = [];
		if ( !$team_id ) {
			$where = [
				'sf_sport_id' => $sport_id,
				'sf_team_id' => 0
			];
		} else {
			$where = [ 'sf_team_id' => $team_id ];
		}

		$dbr = wfGetDB( DB_REPLICA );

		$friends = $dbr->select(
			'user_relationship',
			'r_user_id_relation',
			[ 'r_user_id' => $user_id, 'r_type' => 1 ],
			__METHOD__
		);

		$uids = [];
		foreach ( $friends as $friend ) {
			$uids[] = $friend->r_user_id_relation;
		}

		if ( !empty( $uids ) ) {
			$ourWhere = array_merge(
				$where,
				// @see http://www.mediawiki.org/wiki/Special:Code/MediaWiki/92016#c19527
				[ 'sf_user_id' => $uids ]
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

	static function getSimilarUserCount( $user_id ) {
		$dbr = wfGetDB( DB_REPLICA );

		$teamIdQuery = $dbr->select(
			'sport_favorite',
			'sf_team_id',
			[ 'sf_user_id' => $user_id ],
			__METHOD__
		);

		$teamIds = [];
		foreach ( $teamIdQuery as $teamId ) {
			$teamIds[] = $teamId->sf_team_id;
		}

		if ( !empty( $teamIds ) ) {
			$count = (int)$dbr->selectField(
				'sport_favorite',
				'COUNT(*) AS the_count',
				[
					'sf_team_id' => $teamIds,
					'sf_team_id <> 0',
					"sf_user_id <> {$user_id}"
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
	 * @param $user_id Integer: user ID number
	 * @param $sport_id Integer: sport ID number
	 * @param $team_id Integer: team ID number
	 * @return Boolean: true if the user is a fan, otherwise false
	 */
	static function isFan( $user_id, $sport_id, $team_id ) {
		$where = [ 'sf_user_id' => $user_id ];
		if ( !$team_id ) {
			$where['sf_sport_id'] = $sport_id;
			$where['sf_team_id'] = 0;
		} else {
			$where['sf_team_id'] = $team_id;
		}

		$dbr = wfGetDB( DB_REPLICA );

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

	static function removeFavorite( $user_id, $sport_id, $team_id ) {
		if ( !$team_id ) {
			$where_sql = " sf_sport_id = {$sport_id} AND sf_team_id = 0 ";
		} else {
			$where_sql = " sf_team_id = {$team_id} ";
		}

		$dbr = wfGetDB( DB_MASTER );

		// Get the order of team being deleted;
		$sql = "SELECT sf_order FROM {$dbr->tableName( 'sport_favorite' )} WHERE sf_user_id={$user_id} AND {$where_sql}";
		$res = $dbr->query( $sql, __METHOD__ );
		$row = $dbr->fetchObject( $res );
		$order = $row->sf_order;

		// Update orders for those less than one being deleted
		$res = $dbr->update(
			'sport_favorite',
			[ 'sf_order = sf_order - 1' ],
			[ 'sf_user_id' => $user_id, "sf_order > {$order}" ],
			__METHOD__
		);

		// Finally we can remove the fav
		$sql = "DELETE FROM sport_favorite WHERE sf_user_id={$user_id} AND {$where_sql}";
		$res = $dbr->query( $sql, __METHOD__ );
	}

	static function dateDiff( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;

		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif['w'] = intval( $totalDays / 7 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

		return $dif;
	}

	static function getTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = '';
		if ( $time[$timeabrv] > 0 ) {
			$timeStr = wfMessage( "sportsteams-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if ( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	static function getTimeAgo( $time ) {
		$timeArray = self::dateDiff( time(), $time );
		$timeStr = '';
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