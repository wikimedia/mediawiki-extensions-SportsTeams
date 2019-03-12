<?php
/**
 * SportsTeams API module
 *
 * @file
 * @ingroup API
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
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

		Wikimedia\suppressWarnings();
		$sportId = $params['sportId'];
		Wikimedia\restoreWarnings();

		// You only had one job...
		if ( !$sportId || $sportId === null || !is_numeric( $sportId ) ) {
			$this->dieWithError( [ 'apierror-missingparam', 'sportId' ], 'missingparam' );
		}

		$dbr = $this->getDB();

		$res = $dbr->select(
			'sport_team',
			[ 'team_id', 'team_name' ],
			[ 'team_sport_id' => intval( $sportId ) ],
			__METHOD__,
			[ 'ORDER BY' => 'team_name' ]
		);

		$x = 0;
		$output = [];
		$output['options'] = [];

		foreach ( $res as $row ) {
			/*
			if ( $x != 0 ) {
				$out .= ',';
			}
			*/

			$output['options'][] = [
				'id' => $row->team_id,
				'name' => $row->team_name
			];
			$x++;
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $output ]
		);

		return true;
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 * @return string Human-readable module description
	 */
	public function getDescription() {
		return 'API for fetching the teams for a given sport from the database';
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'sportId' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * Describe the parameter
	 *
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), [
			'sportId' => 'Unique identifier (number) of the sport in question'
		] );
	}

	/**
	 * Get examples
	 *
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return [
			'api.php?action=sportsteams&sportId=3' => 'Get the names and teams under sport #3'
		];
	}

	public function getExamplesMessages() {
		return [
			'action=sportsteams&sportId=3' => 'apihelp-sportsteams-example-1'
		];
	}
}