DROP TABLE IF EXISTS `servers`;
CREATE TABLE `servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `ip` text NOT NULL,
  `port` int(11) NOT NULL,
  `terrainname` text NOT NULL,
  `maxclients` int(11) NOT NULL,
  `lastheartbeat` int(11) NOT NULL,
  `currentusers` int(11) NOT NULL,
  `starttime` int(11) NOT NULL,
  `version` text NOT NULL,
  `state` int(11) NOT NULL,
  `challenge` text NOT NULL,
  `verified` int(11) NOT NULL DEFAULT '0',
  `userlist` text NOT NULL,
  `country` text NOT NULL,
  `users` text NOT NULL,
  `rconenabled` int(1) NOT NULL,
  `passwordprotected` int(1) NOT NULL,
  `country2` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=75425 DEFAULT CHARSET=utf8;
