-- ----------------------------------------------------------------------
-- Phoca SEO manual installation                                       --
-- ----------------------------------------------------------------------
-- See documentation on https://www.phoca.cz/                          --
--                                                                     --
-- Change all prefixes #__ to prefix which is set in your Joomla! site --
-- (e.g. from #__phocaseo to jos_phocaseo)                             --
-- Run this SQL queries in your database tool, e.g. in phpMyAdmin      --
-- If you have questions, just ask in Phoca Forum                      --
-- https://www.phoca.cz/forum/                                         --
-- ----------------------------------------------------------------------
-- @package    Phoca.SEO
-- @subpackage com_phocaseo
-- @copyright  Copyright (C) Jan Pavelka www.phoca.cz
-- @license    GNU/GPL
--

CREATE TABLE IF NOT EXISTS `#__phocaseo_metadata` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `context` varchar(100) NOT NULL DEFAULT '' COMMENT 'e.g. com_content.article, com_menus.item',
  `item_id` int unsigned NOT NULL DEFAULT 0 COMMENT 'Referenced item ID',
  `focus_keyword` varchar(255) NOT NULL DEFAULT '' COMMENT 'Primary SEO keyword',
  `seo_score` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'SEO score 0-100',
  `cornerstone_content` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Cornerstone content flag',
  `canonical_url` varchar(2048) NOT NULL DEFAULT '' COMMENT 'Canonical URL override',
  `sitemap_exclude` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Exclude from sitemap',
  `meta_title` varchar(255) NOT NULL DEFAULT '' COMMENT 'Fallback for extensions without native',
  `meta_description` text COMMENT 'Fallback for extensions without native',
  `ai_data` json DEFAULT NULL COMMENT 'AI-generated analysis data',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_context_item` (`context`, `item_id`),
  KEY `idx_focus_keyword` (`focus_keyword`(191)),
  KEY `idx_seo_score` (`seo_score`),
  KEY `idx_sitemap_exclude` (`sitemap_exclude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Links table for Link Manager/Scanner
--
CREATE TABLE IF NOT EXISTS `#__phocaseo_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source_context` varchar(100) NOT NULL DEFAULT '' COMMENT 'Context of the source item',
  `source_id` int unsigned NOT NULL DEFAULT 0 COMMENT 'ID of the source item',
  `source_url` varchar(2048) NOT NULL DEFAULT '' COMMENT 'Computed SEF URL of source',
  `target_url` varchar(2048) NOT NULL COMMENT 'The linked URL',
  `target_url_hash` char(32) NOT NULL COMMENT 'MD5 hash for indexing',
  `target_option` text,
  `target_view` varchar(2048) NOT NULL,
  `target_id` int(11) NOT NULL DEFAULT '0',
  `link_text` varchar(500) NOT NULL DEFAULT '' COMMENT 'Anchor text',
  `link_type` enum('internal','external') NOT NULL DEFAULT 'internal',
  `is_nofollow` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_sponsored` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_ugc` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `status_code` smallint unsigned DEFAULT NULL COMMENT 'HTTP status code',
  `status_checked` datetime DEFAULT NULL COMMENT 'Last status check time',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source_context`, `source_id`),
  KEY `idx_target_hash` (`target_url_hash`),
  KEY `idx_link_type` (`link_type`),
  KEY `idx_status_code` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Link analysis aggregation table
--
-- CREATE TABLE IF NOT EXISTS `#__phocaseo_link_analysis` (
--  `id` int unsigned NOT NULL AUTO_INCREMENT,
--  `url_hash` char(32) NOT NULL COMMENT 'MD5 hash of the URL',
--  `url` varchar(2048) NOT NULL COMMENT 'The URL',
--  `link_type` enum('internal','external') NOT NULL DEFAULT 'internal',
--  `inbound_count` int unsigned NOT NULL DEFAULT 0 COMMENT 'Number of inbound links',
--  `status_code` smallint unsigned DEFAULT NULL,
--  `status_message` varchar(255) DEFAULT NULL,
--  `status_checked` datetime DEFAULT NULL,
--  `is_orphan` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'No inbound links flag',
--  `created` datetime NOT NULL,
--  `modified` datetime NOT NULL,
--  PRIMARY KEY (`id`),
--  UNIQUE KEY `idx_url_hash` (`url_hash`),
--  KEY `idx_status_code` (`status_code`),
--  KEY `idx_is_orphan` (`is_orphan`),
--  KEY `idx_link_type` (`link_type`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

--
-- Keywords tracking table
--
CREATE TABLE IF NOT EXISTS `#__phocaseo_keywords` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL COMMENT 'The keyword/phrase',
  `keyword_hash` char(32) NOT NULL COMMENT 'MD5 hash for indexing',
  `context` varchar(100) NOT NULL DEFAULT '' COMMENT 'Context where keyword appears',
  `item_id` int unsigned NOT NULL DEFAULT 0 COMMENT 'Item ID where keyword appears',
  `count` int unsigned NOT NULL DEFAULT 1 COMMENT 'Number of occurrences',
  `is_focus` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT 'Is this the focus keyword',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_keyword_context_item` (`keyword_hash`, `context`, `item_id`),
  KEY `idx_keyword` (`keyword`(191)),
  KEY `idx_is_focus` (`is_focus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
