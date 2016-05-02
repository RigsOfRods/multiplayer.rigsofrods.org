-- Adminer 4.2.4 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `servers`;
CREATE TABLE `servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `description` text,
  `ip` text NOT NULL,
  `port` int(11) NOT NULL,
  `terrain-name` text NOT NULL,
  `max-clients` int(11) NOT NULL,
  `last-heartbeat` int(11) NOT NULL,
  `current-users` int(11) NOT NULL DEFAULT '0',
  `start-time` int(11) NOT NULL,
  `version` text NOT NULL,
  `state` int(11) NOT NULL DEFAULT '0',
  `challenge` text NOT NULL,
  `verified` int(11) NOT NULL DEFAULT '0',
  `userlist` text NOT NULL,
  `country` text,
  `users` text NOT NULL,
  `has-rcon` tinyint(1) NOT NULL,
  `has-password` tinyint(1) NOT NULL,
  `is-official` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- 2016-05-02 22:02:04