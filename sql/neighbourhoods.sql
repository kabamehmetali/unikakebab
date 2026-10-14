-- =====================================================================
--  Unika Kebab — neighbourhood landing pages
--
--  One row per local landing page (/kebab-in-king-west-toronto and so on).
--  Rendered by neighbourhood.php, registered into seo_pages() so the
--  sitemap and the meta helpers pick them up automatically, and edited
--  from Admin -> Neighbourhoods.
--
--  is_active is the staging switch. A row with is_active = 0 is a hard
--  404, is absent from sitemap.xml, and is not linked from anywhere —
--  which is how the pages are released a batch at a time rather than all
--  at once.
--
--  Column layout follows menu_categories / order_links: int(11) id,
--  utf8mb4_general_ci, tinyint flags, sort_order, and BOTH timestamps so
--  <lastmod> in the sitemap is honest.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `neighbourhoods` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `slug`          varchar(140) NOT NULL,
  `name`          varchar(120) NOT NULL,

  -- release control
  `batch`         tinyint(4)   NOT NULL DEFAULT 1,
  `sort_order`    int(11)      NOT NULL DEFAULT 0,
  `is_active`     tinyint(1)   NOT NULL DEFAULT 0,

  -- search
  `page_title`    varchar(180) DEFAULT NULL,
  `meta_desc`     varchar(300) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `og_image`      varchar(255) DEFAULT NULL,
  `og_image_alt`  varchar(255) DEFAULT NULL,

  -- hero
  `hero_eyebrow`  varchar(120) DEFAULT NULL,
  `hero_title`    varchar(180) DEFAULT NULL,
  `hero_text`     varchar(400) DEFAULT NULL,
  `hero_image`    varchar(255) DEFAULT NULL,
  `hero_image_alt` varchar(255) DEFAULT NULL,

  -- body: opening prose
  `intro_title`   varchar(180) DEFAULT NULL,
  `intro_text`    text         DEFAULT NULL,

  -- body: getting here
  `travel_title`  varchar(180) DEFAULT NULL,
  `travel_text`   text         DEFAULT NULL,

  -- body: four verifiable logistics facts, rendered as info-cards
  `fact1_icon`    varchar(60)  DEFAULT NULL,
  `fact1_label`   varchar(60)  DEFAULT NULL,
  `fact1_text`    varchar(255) DEFAULT NULL,
  `fact2_icon`    varchar(60)  DEFAULT NULL,
  `fact2_label`   varchar(60)  DEFAULT NULL,
  `fact2_text`    varchar(255) DEFAULT NULL,
  `fact3_icon`    varchar(60)  DEFAULT NULL,
  `fact3_label`   varchar(60)  DEFAULT NULL,
  `fact3_text`    varchar(255) DEFAULT NULL,
  `fact4_icon`    varchar(60)  DEFAULT NULL,
  `fact4_label`   varchar(60)  DEFAULT NULL,
  `fact4_text`    varchar(255) DEFAULT NULL,

  -- body: what this neighbourhood orders (dish names match menu_items.name)
  `picks_title`   varchar(180) DEFAULT NULL,
  `picks_text`    varchar(400) DEFAULT NULL,
  `pick1_dish`    varchar(150) DEFAULT NULL,
  `pick1_note`    varchar(255) DEFAULT NULL,
  `pick2_dish`    varchar(150) DEFAULT NULL,
  `pick2_note`    varchar(255) DEFAULT NULL,
  `pick3_dish`    varchar(150) DEFAULT NULL,
  `pick3_note`    varchar(255) DEFAULT NULL,

  -- body: questions someone in this neighbourhood actually asks
  `faq1_q`        varchar(255) DEFAULT NULL,
  `faq1_a`        text         DEFAULT NULL,
  `faq2_q`        varchar(255) DEFAULT NULL,
  `faq2_a`        text         DEFAULT NULL,
  `faq3_q`        varchar(255) DEFAULT NULL,
  `faq3_a`        text         DEFAULT NULL,

  -- body: closing
  `closing_title` varchar(180) DEFAULT NULL,
  `closing_text`  varchar(500) DEFAULT NULL,

  -- internal linking: comma-separated slugs of 2-3 true geographic neighbours
  `neighbours`    varchar(500) DEFAULT NULL,

  `created_at`    timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_nb_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
