DROP TABLE IF EXISTS `lms_cat`;
CREATE TABLE `lms_cat` (
  `catid` integer NOT NULL auto_increment,
  `parentid` integer NOT NULL DEFAULT '0',
  `name` varchar(255) collate utf8_unicode_ci NOT NULL DEFAULT '',
  `link` boolean NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`catid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE UNIQUE INDEX `idx_cname` ON `lms_cat` (`parentid`,`name`);

DROP TABLE IF EXISTS `lms_link`;
CREATE TABLE `lms_link` (
  `linkid` integer NOT NULL auto_increment,
  `catid` integer NOT NULL DEFAULT '0',
-- Note: 10921 is the maximum number of unicode chars that fits into the max. 65536 byte 
--       varchar field of MySQL in the worst case (6 byte per char)
  `url` varchar(10921) collate utf8_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(255) collate utf8_unicode_ci NOT NULL DEFAULT '',
  `adddate` timestamp NOT NULL,
  PRIMARY KEY (`linkid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
-- For MyISAM tables, the maximum index prefix length is 1000 byte (= at least 166 utf8 chars)
CREATE UNIQUE INDEX `idx_link` ON `lms_link` (catid, url(166));

DROP TABLE IF EXISTS `lms_user`;
CREATE TABLE `lms_user` (
  `userid` integer NOT NULL auto_increment,
  `name` varchar(255) collate utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`userid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE UNIQUE INDEX `idx_uname` ON `lms_user` (`name`);

DROP TABLE IF EXISTS `lms_stat_link`;
CREATE TABLE `lms_stat_link` (
  `linkid` integer NOT NULL,
  `userid` integer NOT NULL,
  `private` boolean NOT NULL DEFAULT FALSE,
  `linkcount` integer NOT NULL DEFAULT '0',
  PRIMARY KEY (`linkid`, `userid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `lms_stat_cat`;
CREATE TABLE `lms_stat_cat` (
  `catid` integer NOT NULL,
  `userid` integer NOT NULL,
  `catcount` integer NOT NULL DEFAULT '0',
  `catnext` integer NOT NULL DEFAULT '0',
  `catprev` integer NOT NULL DEFAULT '0',
  PRIMARY KEY (`catid`, `userid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

