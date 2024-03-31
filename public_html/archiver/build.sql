use archiver;
DROP TABLE IF EXISTS records;
CREATE TABLE records (
	recordID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	userID INT(11),
	iA VARCHAR(2) DEFAULT '7',
	iB VARCHAR(2) DEFAULT 'A',
	iC INT(3) DEFAULT 0,
	recordCondition VARCHAR(8),
	artist VARCHAR(255),
	title VARCHAR(255),
	format VARCHAR(16),
	country VARCHAR(32),
	recordYear INT(4),
	catNo VARCHAR(255),
	stereo INT(1),
	price INT(5),
	hasTimings INT(1) DEFAULT 0,
	hasStars INT(1) DEFAULT 0,
	hasInsert VARCHAR(255),
	hasNote VARCHAR(255),
	comment TEXT,
	dateCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

DROP TABLE IF EXISTS events;
CREATE TABLE events (
	eventID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	userID INT(11),
	recordID INT(11),
	event VARCHAR(16),
	eventDate DATETIME
);
DROP TABLE IF EXISTS pics;
CREATE TABLE pics (
	picID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	recordID INT(11),
	userID INT(11),
	filename VARCHAR(255),
	dateCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	INDEX i1(recordID)
);

ALTER TABLE records ADD INDEX i1(iA);
ALTER TABLE records ADD sleeveCondition VARCHAR(8) AFTER iC;
ALTER TABLE records ADD barcode VARCHAR(16) AFTER status;

ALTER TABLE records DROP status;
ALTER TABLE records DROP dateCreated;
ALTER TABLE records ADD photoTakenByUserID INT(11) DEFAULT 0;
ALTER TABLE records ADD photoTakenDate DATETIME;
ALTER TABLE records ADD infoEnteredByUserID INT(11) DEFAULT 0;
ALTER TABLE records ADD infoEnteredDate DATETIME;
ALTER TABLE records ADD attention VARCHAR(255);
ALTER TABLE records DROP userID;
ALTER TABLE records ADD numCopies INT(3) DEFAULT 1;
-- LIVE 9th April

ALTER TABLE records CHANGE hasStars annotations INT(1) DEFAULT 0;
ALTER TABLE records DROP hasTimings;
ALTER TABLE records ADD partOfPrevious INT(1) DEFAULT 0;
ALTER TABLE records ADD promo INT(1) DEFAULT 0;

DROP TABLE IF EXISTS users;
CREATE TABLE users (
	userID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(32),
	pwd VARCHAR(64)
);

-- Changes, Apr 2015
CREATE TABLE tmpRecordsMar15 AS SELECT * FROM records;
ALTER TABLE records ADD iD INT(2) DEFAULT 1 AFTER iC;

-- Merge to single condition
ALTER TABLE records ADD cond VARCHAR(8) AFTER barcode;
UPDATE records SET cond=recordCondition;
ALTER TABLE records DROP recordCondition;
ALTER TABLE records DROP sleeveCondition;

-- Convert condition NM to EX
UPDATE records SET cond='EX' WHERE cond='NM';

-- LIVE May 13th 2015 JL
ALTER TABLE records ADD special INT(1) DEFAULT 0;

DROP TABLE IF EXISTS chunks;
CREATE TABLE chunks (
	chunkID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	iA VARCHAR(2) DEFAULT '7',
	iB VARCHAR(2) DEFAULT 'A',
	chunkSize INT(4) DEFAULT 1000
);
UPDATE records SET iB='A' WHERE iA='7' AND iB='A0';
ALTER TABLE records CHANGE iC iC INT(4);
-- run index.php?action=createChunks
UPDATE chunks SET chunkSize=2000 WHERE iA='7';
-- LIVE 19th May 2015
DELETE FROM chunks WHERE iA='T';
CREATE TABLE totalHistory (
	totalHistoryID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	dateComputed DATETIME,
	statName VARCHAR(64),
	statValue VARCHAR(64)
);
-- LIVE 21st May 2015

-- 12" chunk sizes
UPDATE chunks SET chunkSize=910 WHERE iA=12 AND iB='A';
UPDATE chunks SET chunkSize=1540 WHERE iA=12 AND iB='B';
UPDATE chunks SET chunkSize=1295 WHERE iA=12 AND iB='C';
UPDATE chunks SET chunkSize=1145 WHERE iA=12 AND iB='D';
UPDATE chunks SET chunkSize=520 WHERE iA=12 AND iB='E';
UPDATE chunks SET chunkSize=890 WHERE iA=12 AND iB='F';
UPDATE chunks SET chunkSize=600 WHERE iA=12 AND iB='G';
UPDATE chunks SET chunkSize=785 WHERE iA=12 AND iB='H';
UPDATE chunks SET chunkSize=350 WHERE iA=12 AND iB='I';
UPDATE chunks SET chunkSize=350 WHERE iA=12 AND iB='J';
UPDATE chunks SET chunkSize=505 WHERE iA=12 AND iB='K';
UPDATE chunks SET chunkSize=750 WHERE iA=12 AND iB='L';
UPDATE chunks SET chunkSize=1420 WHERE iA=12 AND iB='M';
UPDATE chunks SET chunkSize=455 WHERE iA=12 AND iB='N';
UPDATE chunks SET chunkSize=130 WHERE iA=12 AND iB='O';
UPDATE chunks SET chunkSize=1200 WHERE iA=12 AND iB='P';
UPDATE chunks SET chunkSize=60 WHERE iA=12 AND iB='Q';
UPDATE chunks SET chunkSize=805 WHERE iA=12 AND iB='R';
UPDATE chunks SET chunkSize=2240 WHERE iA=12 AND iB='S';
UPDATE chunks SET chunkSize=1105 WHERE iA=12 AND iB='T';
UPDATE chunks SET chunkSize=275 WHERE iA=12 AND iB='U';
UPDATE chunks SET chunkSize=265 WHERE iA=12 AND iB='V';
UPDATE chunks SET chunkSize=510 WHERE iA=12 AND iB='W';
UPDATE chunks SET chunkSize=70 WHERE iA=12 AND iB='X';
UPDATE chunks SET chunkSize=75 WHERE iA=12 AND iB='Y';
UPDATE chunks SET chunkSize=95 WHERE iA=12 AND iB='Z';
UPDATE chunks SET chunkSize=215 WHERE iA=12 AND iB='0';
-- Additional 12" chunks
INSERT INTO chunks(iA,iB,chunkSize) VALUES (12,'Sp',99);
INSERT INTO chunks(iA,iB,chunkSize) VALUES (12,'Mi',380);
INSERT INTO chunks(iA,iB,chunkSize) VALUES (12,'Co',70);
INSERT INTO chunks(iA,iB,chunkSize) VALUES (12,'Un',55);
-- Specials chunk for 7"s
INSERT INTO chunks(iA,iB,chunkSize) VALUES (7,'Sp',999);

UPDATE chunks SET chunkSize=99 WHERE iA='M';
INSERT INTO chunks(iA,iB,chunkSize) VALUES ('M','Se',230);
-- LIVE 16th Jun 2015

ALTER TABLE records ADD label VARCHAR(64) AFTER format;
ALTER TABLE records ADD attentionGiven VARCHAR(255) AFTER attention;
ALTER TABLE records ADD pressRelease INT(1) DEFAULT 0;
-- LIVE 2nd Jul 2015

UPDATE chunks SET chunkSize=410 WHERE iA='7' AND iB='Sp';
-- LIVE 13th Jul 2015
INSERT INTO chunks(iA,iB,chunkSize) VALUES (7,'Re',400);
-- LIVE 24th Aug 2015
INSERT INTO chunks(iA,iB,chunkSize) VALUES (7,'FB',150);
INSERT INTO chunks(iA,iB,chunkSize) VALUES ('CD','Mi',100);
-- LIVE 23rd Oct 2015
