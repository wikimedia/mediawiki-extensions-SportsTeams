DROP SEQUENCE IF EXISTS sport_sport_id_seq CASCADE;
CREATE SEQUENCE sport_sport_id_seq;

CREATE TABLE /*_*/sport (
	sport_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('sport_sport_id_seq'),
	sport_name TEXT NOT NULL default '',
	sport_order TEXT NOT NULL default ''
);

ALTER SEQUENCE sport_sport_id_seq OWNED BY sport.sport_id;

CREATE TABLE /*_*/sport_favorite (
	sf_id INTEGER NOT NULL default 0,
	sf_sport_id INTEGER NOT NULL default 0,
	sf_team_id INTEGER NOT NULL default 0,
	sf_actor INTEGER NOT NULL default 0,
	sf_order INTEGER NOT NULL default 0,
	sf_date TIMESTAMPTZ default NULL
);

DROP SEQUENCE IF EXISTS sport_team_team_id_seq CASCADE;
CREATE SEQUENCE sport_team_team_id_seq;

CREATE TABLE /*_*/sport_team (
	team_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('sport_team_team_id_seq'),
	team_name TEXT NOT NULL default '',
	team_sport_id INTEGER NOT NULL default 0
);

ALTER SEQUENCE sport_team_team_id_seq OWNED BY sport_team.team_id;