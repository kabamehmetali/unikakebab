-- =====================================================================
--  Unika Kebab — Toronto zone directory
--
--  Every one of the City of Toronto's 158 official neighbourhoods, with
--  the district it belongs to and its distance from 133 Jefferson Ave.
--
--  This is reference data, not editorial content. It exists so the
--  locations page can answer "how far is it from where I am?" for
--  anywhere in the city, without needing a written page per area — the
--  written pages stay a small curated set in `neighbourhoods`.
--
--  page_slug is the join: a zone that has been written up points at its
--  row in `neighbourhoods`, and the directory links through to it. Most
--  zones have no page and simply carry a distance and a directions link.
--
--  Geometry, centroids and distances all come from the City of Toronto
--  open-data boundary files — see sql/toronto-zones-seed.sql for the
--  provenance note. Nothing here is estimated.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `toronto_zones` (
  `id`          int(11)       NOT NULL AUTO_INCREMENT,
  `name`        varchar(120)  NOT NULL,
  `slug`        varchar(140)  NOT NULL,
  `district`    varchar(60)   NOT NULL,
  `latitude`    decimal(10,7) DEFAULT NULL,
  `longitude`   decimal(10,7) DEFAULT NULL,
  `distance_km` decimal(6,2)  DEFAULT NULL,
  `page_slug`   varchar(140)  DEFAULT NULL,
  `sort_order`  int(11)       NOT NULL DEFAULT 0,
  `is_active`   tinyint(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_zone_district` (`district`, `distance_km`),
  KEY `idx_zone_distance` (`distance_km`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
