USE gringo_db;
ALTER TABLE clothes
 ADD COLUMN formality ENUM('casual','smart_casual','formal','black_tie') NULL AFTER style;
