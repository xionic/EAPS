CREATE TABLE tClient (
	client_id INTEGER PRIMARY KEY AUTOINCREMENT,
	client_key CHAR(64) NOT NULL,
	client_name CHAR(64) NOT NULL,
	
	UNIQUE(client_key),
	UNIQUE(client_name)
);

CREATE TABLE tTag (
	tag_id INTEGER PRIMARY KEY AUTOINCREMENT,
	tag_name VARCHAR(32) NOT NULL,
	client_id INTEGER NOT NULL,
	
	UNIQUE(tag_name, client_id)
	FOREIGN KEY (`client_id`) REFERENCES `tClient` (`client_id`)
);

CREATE TABLE tKey (
	key_id INTEGER PRIMARY KEY AUTOINCREMENT,
	key_name VARCHAR(64) NOT NULL,
	tag_id INTEGER NOT NULL,
	newest_value_id INTEGER NOT NULL,
	
	UNIQUE(key_name, tag_id)
	FOREIGN KEY (`tag_id`) REFERENCES `tTag` (`tag_id`)
);

CREATE TABLE tValue (
	value_id INTEGER PRIMARY KEY AUTOINCREMENT,
	key_id INTEGER,
	value_data VARCHAR(1024) NOT NULL,
	created INTEGER NOT NULL,
	
	
	FOREIGN KEY (`key_id`) REFERENCES `tKey` (`key_id`)
);

