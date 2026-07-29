-- =====================================================================
--  Unika Kebab — SEO upgrade
--
--  Adds editable alt text to every image the admin can upload, and a full
--  set of per-page meta tags. Safe to run more than once.
--
--    mysql -u root unikakebab_db < sql/seo-upgrade.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Alt text columns
--
-- MySQL has no portable "ADD COLUMN IF NOT EXISTS", so each one is guarded
-- by an information_schema lookup that builds either the ALTER or a no-op.
-- Done with plain prepared statements rather than a stored procedure, which
-- keeps this runnable on XAMPP installs whose mysql.proc is out of date.
-- ---------------------------------------------------------------------
SET @ddl = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'menu_items' AND COLUMN_NAME = 'image_alt'),
  'DO 0',
  'ALTER TABLE menu_items ADD COLUMN image_alt VARCHAR(255) NULL AFTER image'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'menu_categories' AND COLUMN_NAME = 'image_alt'),
  'DO 0',
  'ALTER TABLE menu_categories ADD COLUMN image_alt VARCHAR(255) NULL AFTER image'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store' AND COLUMN_NAME = 'photo_alt'),
  'DO 0',
  'ALTER TABLE store ADD COLUMN photo_alt VARCHAR(255) NULL AFTER photo'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. Settings
--
-- INSERT IGNORE keeps values an admin has already customised: re-running
-- this file tops up missing keys instead of resetting the whole set.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES

-- --- global SEO -------------------------------------------------------
('seo_author',           'Mehmet Ali KABA'),
('seo_publisher',        'Unika Kebab'),
('seo_locale',           'en_CA'),
('seo_title_suffix',     'Unika Kebab Charcoal Grill'),
('seo_default_robots',   'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'),
('seo_twitter_site',     ''),
('seo_twitter_creator',  ''),
('seo_fb_app_id',        ''),
('seo_google_verification', ''),
('seo_bing_verification',   ''),
('seo_pinterest_verification', ''),
('seo_price_range',      '$$'),
('seo_sitemap_enabled',  '1'),

-- --- alt text for the images an admin uploads -------------------------
('logo_alt',                  'Unika Kebab Charcoal Grill logo'),
('footer_logo_alt',           'Unika Kebab Charcoal Grill logo'),
('og_image_alt',              'Charcoal-grilled kebab skewers at Unika Kebab'),
('hero_image_alt',            'Kebab skewers grilling over glowing charcoal at Unika Kebab'),
('home_about_image_alt',      'Chef hand-shaping kofte in the Unika Kebab kitchen'),
('menu_item_placeholder_alt', 'Unika Kebab menu item photo coming soon'),

-- --- home page --------------------------------------------------------
('meta_home_og_title',   ''),
('meta_home_og_desc',    ''),
('meta_home_og_image',   ''),
('meta_home_canonical',  ''),
('meta_home_robots',     ''),

-- --- menu page --------------------------------------------------------
('meta_menu_og_title',   ''),
('meta_menu_og_desc',    ''),
('meta_menu_og_image',   ''),
('meta_menu_canonical',  ''),
('meta_menu_robots',     ''),

-- --- contact page -----------------------------------------------------
('meta_contact_og_title',  ''),
('meta_contact_og_desc',   ''),
('meta_contact_og_image',  ''),
('meta_contact_canonical', ''),
('meta_contact_robots',    ''),

-- --- locations page ---------------------------------------------------
('meta_locations_og_title',  ''),
('meta_locations_og_desc',   ''),
('meta_locations_og_image',  ''),
('meta_locations_canonical', ''),
('meta_locations_robots',    ''),

-- --- order page -------------------------------------------------------
('meta_order_og_title',   ''),
('meta_order_og_desc',    ''),
('meta_order_og_image',   ''),
('meta_order_canonical',  ''),
('meta_order_robots',     ''),

-- --- newsletter pages (kept out of the index by default) --------------
('meta_subscribe_title',    'Newsletter | Unika Kebab Charcoal Grill'),
('meta_subscribe_desc',     'Confirm your subscription to the Unika Kebab list — specials, new skewers and seasonal plates, once a month.'),
('meta_subscribe_keywords', ''),
('meta_subscribe_og_title', ''),
('meta_subscribe_og_desc',  ''),
('meta_subscribe_og_image', ''),
('meta_subscribe_canonical',''),
('meta_subscribe_robots',   'noindex, follow'),

('meta_unsubscribe_title',    'Unsubscribe | Unika Kebab Charcoal Grill'),
('meta_unsubscribe_desc',     'Manage your Unika Kebab email preferences or unsubscribe from the newsletter.'),
('meta_unsubscribe_keywords', ''),
('meta_unsubscribe_og_title', ''),
('meta_unsubscribe_og_desc',  ''),
('meta_unsubscribe_og_image', ''),
('meta_unsubscribe_canonical',''),
('meta_unsubscribe_robots',   'noindex, nofollow');

-- ---------------------------------------------------------------------
-- 3. Seed alt text for menu items that already have a photo
-- ---------------------------------------------------------------------
UPDATE menu_items mi
  JOIN menu_categories mc ON mc.id = mi.category_id
   SET mi.image_alt = CONCAT(mi.name, ' — ', mc.name, ' at Unika Kebab Charcoal Grill')
 WHERE mi.image IS NOT NULL AND mi.image <> ''
   AND (mi.image_alt IS NULL OR mi.image_alt = '');

UPDATE menu_categories
   SET image_alt = CONCAT(name, ' menu category at Unika Kebab Charcoal Grill')
 WHERE image IS NOT NULL AND image <> ''
   AND (image_alt IS NULL OR image_alt = '');

UPDATE store
   SET photo_alt = CONCAT(name, ' storefront on ', COALESCE(address_line1, ''), ', ', COALESCE(city, ''))
 WHERE photo IS NOT NULL AND photo <> ''
   AND (photo_alt IS NULL OR photo_alt = '');
