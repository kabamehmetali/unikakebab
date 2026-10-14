-- =====================================================================
--  Unika Kebab — NAP, entity and share-image fixes
--
--  Safe to run more than once, and safe to run against production: every
--  statement is either an exact-value replacement or an upsert.
--
--  Run with:
--    mysql -u root unikakebab_db < sql/seo-nap-fixes.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. The fake phone number
--
-- order_links row "Call for pickup" points at +1 416-555-0184. 555-01xx is
-- the reserved fictional range — the link is on every page of the site, in
-- the Order dropdown, and it dials nowhere. It also contradicts the real
-- number in the same page's JSON-LD, and two phone numbers for one business
-- is exactly the inconsistency that stops a Maps listing resolving.
-- ---------------------------------------------------------------------
UPDATE `order_links`
   SET `url` = 'tel:+14165162087'
 WHERE `url` LIKE 'tel:%555%';

-- ---------------------------------------------------------------------
-- 2. The store's own number, in a form that displays like a phone number
--
-- phone_href() now normalises to E.164 itself, so the stored value can be
-- readable without breaking the tel: link or the schema.org telephone.
-- ---------------------------------------------------------------------
UPDATE `store`
   SET `phone`    = '(416) 516-2087',
       `whatsapp` = CASE WHEN `whatsapp` IS NULL OR `whatsapp` = '' OR `whatsapp` LIKE '%000-0000%'
                         THEN NULL ELSE `whatsapp` END
 WHERE `id` = 1;

-- ---------------------------------------------------------------------
-- 3. The dead email domain
--
-- unikakebab.ca is not merely missing DNS — it is unregistered, so mail to
-- info@unikakebab.ca is silently lost and the address is free for anyone
-- else to take. unikakebab.com already has MX at mail.unikakebab.com.
-- ---------------------------------------------------------------------
UPDATE `store`
   SET `email` = 'info@unikakebab.com'
 WHERE `id` = 1 AND `email` LIKE '%unikakebab.ca';

UPDATE `settings`
   SET `setting_value` = REPLACE(`setting_value`, '@unikakebab.ca', '@unikakebab.com')
 WHERE `setting_value` LIKE '%@unikakebab.ca';

-- ---------------------------------------------------------------------
-- 4. Bind the website to the Google Business Profile
--
-- The listing's CID. Published as hasMap and in sameAs so Google is told
-- explicitly that this site and that pin are one business — the link that
-- is currently missing in both directions.
--   maps.app.goo.gl/VSmRZV8CSL1z6AVN7
--     -> .../place/Unika+kebab/...!1s0x882b35058bdca601:0x1f5db3693df3fb0b
--        0x1f5db3693df3fb0b == 2260159852602194699
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('store_map_cid', '2260159852602194699')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ---------------------------------------------------------------------
-- 5. Share images
--
-- og:image was the logo on all six pages: a 900x832 near-square graphic in
-- a slot every platform crops to roughly 1.91:1. The hero photographs are
-- 1800x960 and are actual food.
--
-- NOTE: hero-home.jpg and hero-menu.jpg are currently byte-identical, so
-- this buys three distinct images, not six. Real per-page photography is
-- on the owner checklist.
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('og_image',                  'assets/img/hero-home.jpg'),
  ('og_image_alt',              'Charcoal-grilled kebab skewers at Unika Kebab in Liberty Village, Toronto'),
  ('meta_home_og_image',        'assets/img/hero-home.jpg'),
  ('meta_menu_og_image',        'assets/img/hero-menu.jpg'),
  ('meta_contact_og_image',     'assets/img/hero-contact.jpg'),
  ('meta_locations_og_image',   'uploads/site/about_20260728_125447_4dd2b2.png'),
  ('meta_order_og_image',       'assets/img/hero-home.jpg'),
  ('meta_join_og_image',        'assets/img/hero-contact.jpg')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ---------------------------------------------------------------------
-- 6. The home page claims Liberty Village
--
-- The single most winnable query on the board, and the word appeared
-- nowhere on the site outside the postal address.
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('meta_home_title',    'Unika Kebab | Charcoal Grill in Liberty Village, Toronto'),
  ('meta_home_desc',     'Charcoal-grilled Adana, beef kofte, shish tavuk and shawarma on Jefferson Ave in Liberty Village, Toronto. Halal, hand-shaped daily, wrapped to order.'),
  ('meta_home_keywords', 'kebab liberty village, charcoal kebab toronto, halal kebab toronto, turkish restaurant liberty village, adana kebab, shawarma'),
  ('hero_eyebrow',       'Charcoal grill in Liberty Village'),
  ('hero_title',         'Skewers with a story'),
  ('hero_subtitle',      'Hand-shaped kofte, marinated shish tavuk and shawarma carved to order — pressed into warm pita straight off the coals, a minute from Lamport Stadium.'),
  ('meta_locations_title', 'Find Us in Liberty Village | Unika Kebab Charcoal Grill'),
  ('meta_locations_desc',  'Unika Kebab is at 133 Jefferson Ave in Liberty Village, Toronto — beside Lamport Stadium, off the 504 King stop. Hours, directions and parking.')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
