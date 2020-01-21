-- Author: Jack Phoenix
-- Date: 3 August 2011
-- License: public domain to the extent that it is possible
CREATE TABLE /*_*/sport (
	-- Autoincrementing, unique ID for this sport
	sport_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	-- Name of the sport (i.e. "Cheerleading")
	sport_name varchar(255) NOT NULL default '',
	-- I have no idea what this should be...
	sport_order varchar(255) NOT NULL default ''
)/*$wgDBTableOptions*/;

CREATE TABLE /*_*/sport_favorite (
	-- TODO: should this or the sport ID be the primary key?
	sf_id int(11) NOT NULL default 0,
	-- Seems to correspond to sport.sport_id
	sf_sport_id int(11) NOT NULL default 0,
	-- Corresponds to sport_team.team_id, I believe
	sf_team_id int(11) NOT NULL default 0,
	-- User's actor ID
	sf_actor bigint unsigned NOT NULL,
	--
	sf_order int(11) NOT NULL default 0,
	-- Timestamp of the last update
	sf_date datetime default NULL
)/*$wgDBTableOptions*/;

CREATE TABLE /*_*/sport_team (
	-- Autoincrementing, unique ID for this team
	team_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	-- Name of the team (i.e. "New York Mets")
	team_name varchar(255) NOT NULL default '',
	-- ID of the sport (see sport.sport_id) that this team is associated with
	team_sport_id int(11) NOT NULL default 0
)/*$wgDBTableOptions*/;