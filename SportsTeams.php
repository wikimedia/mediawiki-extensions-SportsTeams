<?php
/**
 * SportsTeams extension -- provides networking functionality
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author Ashish Datta <ashish@setfive.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:SportsTeams Documentation
 */
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'SportsTeams' );
	$wgMessagesDirs['SportsTeams'] =  __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for SportsTeams extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the SportsTeams extension requires MediaWiki 1.25+' );
}