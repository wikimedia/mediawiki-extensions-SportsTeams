<?php
/**
 * SportsTeams API module
 *
 * @file
 * @ingroup API
 * @date 11 July 2013
 * @see http://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiSportsTeams extends ApiBase {

	/**
	 * The original incarnation of this code was an AJAX function called
	 * wfGetSportsTeams in SportsTeams_AjaxFunctions.php.
	 * That function was referenced by ../LoginReg/SpecialUserRegister.php and
	 * SpecialUpdateProfile_Sports.php.
	 * Said function was originally located in UserProfile/UserProfile_AjaxFunctions.php
	 */
	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		wfSuppressWarnings();
		$sportId = $params['sportId'];
		wfRestoreWarnings();

		// You only had one job...
		if ( !$sportId || $sportId === null || !is_numeric( $sportId ) ) {
			$this->dieUsageMsg( 'missingparam' );
		}

		$dbr = $this->getDB();

		$res = $dbr->select(
			'sport_team',
			array( 'team_id', 'team_name' ),
			array( 'team_sport_id' => intval( $sportId ) ),
			__METHOD__,
			array( 'ORDER BY' => 'team_name' )
		);

		$x = 0;
		$output = array();
		$output['options'] = array();

		foreach ( $res as $row ) {
			/*
			if ( $x != 0 ) {
				$out .= ',';
			}
			*/

			$output['options'][] = array(
				'id' => $row->team_id,
				'name' => $row->team_name
			);
			$x++;
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'result' => $output )
		);

		return true;
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 * @return String: human-readable module description
	 */
	public function getDescription() {
		return 'API for fetching the teams for a given sport from the database';
	}

	/**
	 * @return Array
	 */
	public function getAllowedParams() {
		return array(
			'sportId' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			)
		);
	}

	/**
	 * Describe the parameter
	 *
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'sportId' => 'Unique identifier (number) of the sport in question'
		) );
	}

	/**
	 * Get Examples
	 *
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
			'api.php?action=sportsteams&sportId=3' => 'Get the names and teams under sport #3'
		);
	}

	public function getExamplesMessages() {
		return array(
			'action=sportsteams&sportId=3' => 'apihelp-sportsteams-example-1'
		);
	}
}