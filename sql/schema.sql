-- =====================================================================
--  UNIKA KEBAB — Charcoal Grill
--  Database schema + seed data
--  Import:  mysql -u root < sql/schema.sql
-- =====================================================================

DROP DATABASE IF EXISTS unikakebab_db;
CREATE DATABASE unikakebab_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unikakebab_db;

-- ---------------------------------------------------------------------
-- Admin users
-- ---------------------------------------------------------------------
CREATE TABLE admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(120) NOT NULL DEFAULT 'Administrator',
  email         VARCHAR(150) NULL,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password = admin123  (change it in Admin > Settings > Account)
INSERT INTO admins (username, password_hash, full_name, email) VALUES
('admin', '$2y$12$GObKt3zydJ76MQgcR3yRVO6QiXLm7jF3THTOz8xKdCXp4drBIttsC', 'Unika Kebab Admin', 'info@unikakebab.ca');

-- ---------------------------------------------------------------------
-- Key / value settings — powers site config, SEO meta, API keys, home page
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  setting_key   VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
-- --- brand / identity -------------------------------------------------
('site_name',            'Unika Kebab'),
('site_tagline',         'Charcoal Grill'),
('logo',                 'assets/img/logo.png'),
('footer_logo',          ''),
('menu_item_placeholder','assets/img/placeholder.svg'),
('favicon',              'assets/img/favicon.png'),
('og_image',             'assets/img/logo.png'),
('store_map_share_url',  ''),

-- --- SEO meta, per page ----------------------------------------------
('meta_home_title',      'Unika Kebab | Charcoal Grill — Authentic Turkish Kebab'),
('meta_home_desc',       'Skewers grilled over real charcoal. Adana, beef kofte, shish tavuk and shawarma wrapped fresh daily at Unika Kebab.'),
('meta_home_keywords',   'kebab, charcoal grill, adana, shawarma, turkish food, halal'),
('meta_menu_title',      'Menu | Unika Kebab Charcoal Grill'),
('meta_menu_desc',       'Sandwiches, platters, sides and desserts — see the full Unika Kebab charcoal grill menu and prices.'),
('meta_menu_keywords',   'kebab menu, adana kebab, shawarma, falafel, baklava'),
('meta_contact_title',   'Contact Us | Unika Kebab Charcoal Grill'),
('meta_contact_desc',    'Questions, catering or large orders? Send the Unika Kebab team a message and we will get right back to you.'),
('meta_contact_keywords','contact unika kebab, catering, phone number'),
('meta_locations_title', 'Locations | Unika Kebab Charcoal Grill'),
('meta_locations_desc',  'Find Unika Kebab — address, opening hours, parking and directions to our charcoal grill.'),
('meta_locations_keywords','unika kebab location, directions, opening hours'),
('meta_order_title',     'Order Online | Unika Kebab Charcoal Grill'),
('meta_order_desc',      'Order Unika Kebab for pickup or delivery through your favourite app, or call the grill directly.'),
('meta_order_keywords',  'order kebab online, delivery, pickup'),

-- --- home: hero -------------------------------------------------------
('hero_image',           'assets/img/hero-home.jpg'),
('hero_eyebrow',         'Grilled over real charcoal'),
('hero_title',           'Skewers with a story'),
('hero_title_accent',    'story'),
('hero_subtitle',        'Hand-shaped kofte, marinated shish tavuk and shawarma carved to order — pressed into warm pita straight off the grill.'),
('hero_btn1_text',       'View the menu'),
('hero_btn1_link',       'menu.php'),
('hero_btn2_text',       'Order online'),
('hero_btn2_link',       'order.php'),
('hero_show_logo',       '1'),

-- --- home: intro / story ---------------------------------------------
('home_about_eyebrow',   'Our kitchen'),
('home_about_title',     'Fire, smoke and a little patience'),
('home_about_text',      'Everything at Unika starts on the charcoal. We shape the kofte by hand each morning, marinate the chicken overnight and grill each skewer to order over glowing coals — never a flat-top, never a microwave. The bread is warmed on the same grill so it picks up the smoke, then filled with crisp pickles, parsley, tomato and our house taratur.'),
('home_about_image',     'assets/img/bg-parchment.jpg'),
('home_about_btn_text',  'See what we grill'),
('home_about_btn_link',  'menu.php'),

-- --- home: menu preview ----------------------------------------------
('home_menu_eyebrow',    'From the grill'),
('home_menu_title',      'Favourites off the coals'),
('home_menu_text',       'A short look at what regulars order most. The full list — sandwiches, platters, sides and sweets — lives on the menu page.'),
('home_menu_btn_text',   'Full menu'),
('home_menu_btn_link',   'menu.php'),

-- --- home: why us -----------------------------------------------------
('home_features_eyebrow','Why Unika'),
('home_features_title',  'Small kitchen, serious standards'),

-- --- home: call to action band ---------------------------------------
('home_cta_eyebrow',     'Hungry now?'),
('home_cta_title',       'Your kebab is twelve minutes away'),
('home_cta_text',        'Order ahead for pickup or have it delivered — the grill fires as soon as your order lands.'),
('home_cta_btn_text',    'Start an order'),
('home_cta_btn_link',    'order.php'),

-- --- home: newsletter -------------------------------------------------
('newsletter_eyebrow',   'The Unika list'),
('newsletter_title',     'Specials before anyone else'),
('newsletter_text',      'One short email a month: new skewers, seasonal plates and the occasional free baklava. No spam, unsubscribe anytime.'),
('newsletter_btn_text',  'Join the list'),

-- --- page intros ------------------------------------------------------
('menu_hero_eyebrow',    'Charcoal grill'),
('menu_hero_title',      'The menu'),
('menu_hero_text',       'Sandwiches wrapped in warm pita, loaded platters, and the sides that make them.'),
('contact_hero_eyebrow', 'Say hello'),
('contact_hero_title',   'Contact us'),
('contact_hero_text',    'Catering, large orders or just a question about the grill — we answer fast.'),
('contact_intro_title',  'We read every message'),
('contact_intro_text',   'Fill in the form and it lands with the kitchen straight away. For anything urgent, calling is quickest.'),
('locations_hero_eyebrow','Find us'),
('locations_hero_title', 'Our location'),
('locations_hero_text',  'One kitchen, one grill, one address. Come say hi.'),
('order_hero_eyebrow',   'Pickup & delivery'),
('order_hero_title',     'Order online'),
('order_hero_text',      'Pick your platform below, or call the shop and we will have it ready.'),

-- --- footer -----------------------------------------------------------
('footer_about',         'Charcoal-grilled kebab, shawarma and mezze made fresh every day. Family run, generously portioned, always halal.'),
('footer_copyright',     'Unika Kebab Charcoal Grill. All rights reserved.'),

-- --- integrations -----------------------------------------------------
('google_maps_api_key',  'AIzaSyC6mA-6aqUkGDL1gQSLw7dtvDEYxq3ht7Q'),
('google_recaptcha_site_key',   ''),
('google_recaptcha_secret_key', ''),
('twilio_account_sid',   'AC8015b070fe0d5e1b9e2c5d97c630f5e0'),
('twilio_auth_token',    '668036b2825141a50a1eb7a64806e4a2'),
('twilio_from_number',   ''),
('twilio_to_number',     ''),
('sms_enabled',          '0');

-- ---------------------------------------------------------------------
-- Store (single row, id = 1)
-- ---------------------------------------------------------------------
CREATE TABLE store (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL DEFAULT 'Unika Kebab',
  tagline       VARCHAR(200) NULL,
  address_line1 VARCHAR(200) NULL,
  address_line2 VARCHAR(200) NULL,
  city          VARCHAR(100) NULL,
  province      VARCHAR(100) NULL,
  postal_code   VARCHAR(20)  NULL,
  country       VARCHAR(100) NULL DEFAULT 'Canada',
  phone         VARCHAR(40)  NULL,
  whatsapp      VARCHAR(40)  NULL,
  email         VARCHAR(150) NULL,
  latitude      DECIMAL(10,7) NULL,
  longitude     DECIMAL(10,7) NULL,
  map_zoom      TINYINT NOT NULL DEFAULT 16,
  directions_note TEXT NULL,
  parking_note  TEXT NULL,
  photo         VARCHAR(255) NULL,
  facebook      VARCHAR(255) NULL,
  instagram     VARCHAR(255) NULL,
  twitter       VARCHAR(255) NULL,
  tiktok        VARCHAR(255) NULL,
  youtube       VARCHAR(255) NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO store (id, name, tagline, address_line1, city, province, postal_code, country,
                   phone, whatsapp, email, latitude, longitude, directions_note, parking_note,
                   facebook, instagram, tiktok) VALUES
(1, 'Unika Kebab', 'Charcoal Grill',
 '133 Jefferson Ave', 'Toronto', 'ON', 'M6K 3E4', 'Canada',
 '(416) 555-0184', '(416) 555-0184', 'info@unikakebab.ca',
 NULL, NULL,
 NULL, NULL,
 'https://facebook.com/unikakebab', 'https://instagram.com/unikakebab', 'https://tiktok.com/@unikakebab');

-- ---------------------------------------------------------------------
-- Opening hours (0 = Monday … 6 = Sunday)
-- ---------------------------------------------------------------------
CREATE TABLE store_hours (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  store_id   INT NOT NULL DEFAULT 1,
  day_index  TINYINT NOT NULL,
  is_closed  TINYINT(1) NOT NULL DEFAULT 0,
  open_time  TIME NULL,
  close_time TIME NULL,
  note       VARCHAR(120) NULL,
  UNIQUE KEY uq_store_day (store_id, day_index),
  CONSTRAINT fk_hours_store FOREIGN KEY (store_id) REFERENCES store(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO store_hours (store_id, day_index, is_closed, open_time, close_time) VALUES
(1, 0, 0, '11:00:00', '22:00:00'),
(1, 1, 0, '11:00:00', '22:00:00'),
(1, 2, 0, '11:00:00', '22:00:00'),
(1, 3, 0, '11:00:00', '23:00:00'),
(1, 4, 0, '11:00:00', '23:59:00'),
(1, 5, 0, '11:00:00', '23:59:00'),
(1, 6, 0, '12:00:00', '21:00:00');

-- ---------------------------------------------------------------------
-- Order Online links (navbar dropdown + order page)
-- ---------------------------------------------------------------------
CREATE TABLE order_links (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  label      VARCHAR(80)  NOT NULL,
  url        VARCHAR(255) NOT NULL,
  icon       VARCHAR(60)  NOT NULL DEFAULT 'fa-solid fa-utensils',
  note       VARCHAR(160) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO order_links (label, url, icon, note, sort_order, is_active) VALUES
('Uber Eats',       'https://www.ubereats.com/',      'fa-solid fa-car-side',   'Delivery in 25–40 min',     1, 1),
('DoorDash',        'https://www.doordash.com/',      'fa-solid fa-box-open',   'Delivery & pickup',         2, 1),
('SkipTheDishes',   'https://www.skipthedishes.com/', 'fa-solid fa-bicycle',    'Local delivery',            3, 1),
('Call for pickup', 'tel:+14165550184',               'fa-solid fa-phone-flip', 'Ready in about 12 minutes', 4, 1);

-- ---------------------------------------------------------------------
-- Home page "why us" cards
-- ---------------------------------------------------------------------
CREATE TABLE home_features (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  icon       VARCHAR(60)  NOT NULL DEFAULT 'fa-solid fa-fire',
  title      VARCHAR(120) NOT NULL,
  text       TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO home_features (icon, title, text, sort_order) VALUES
('fa-solid fa-fire-flame-curved', 'Real charcoal, no shortcuts', 'Every skewer is grilled to order over glowing coals — that char is the whole point.', 1),
('fa-solid fa-wheat-awn',         'Made fresh each morning',     'Kofte shaped by hand, chicken marinated overnight, salads chopped the same day.', 2),
('fa-solid fa-certificate',       'Halal and generous',          'Fully halal meat and portions built for an appetite, not for a photograph.', 3),
('fa-solid fa-stopwatch',         'Twelve minutes to pickup',    'Order ahead and your bag is on the counter, still warm, when you walk in.', 4);

-- ---------------------------------------------------------------------
-- Menu
-- ---------------------------------------------------------------------
CREATE TABLE menu_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(140) NOT NULL UNIQUE,
  description TEXT NULL,
  image       VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO menu_categories (id, name, slug, description, sort_order, is_active) VALUES
(1, 'Sandwiches',      'sandwiches', 'Wrapped in pita warmed on the grill, with pickles, parsley and house sauce.', 1, 1),
(2, 'Plates (Platter)','plates',     'A full plate — skewers, rice or fries, grilled vegetables and tabule salad.',  2, 1),
(3, 'Sides',           'sides',      'The supporting cast. Good on their own, better with a skewer.',               3, 1),
(4, 'Add-Ons',         'add-ons',    'Extra toppings and sauces available.',                                        4, 1),
(5, 'Dessert',         'dessert',    'Sweet, syrupy and traditional — the proper way to finish.',                   5, 1),
(6, 'Drinks',          'drinks',     'Cold drinks to cut through the smoke.',                                       6, 1);

CREATE TABLE menu_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name        VARCHAR(150) NOT NULL,
  description TEXT NULL,
  price       DECIMAL(8,2) NULL,
  price_note  VARCHAR(40) NULL,
  image       VARCHAR(255) NULL,
  badge       VARCHAR(40) NULL,
  is_vegetarian TINYINT(1) NOT NULL DEFAULT 0,
  is_spicy      TINYINT(1) NOT NULL DEFAULT 0,
  is_featured   TINYINT(1) NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_items_cat (category_id),
  CONSTRAINT fk_items_cat FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO menu_items (category_id, name, description, price, price_note, badge, is_vegetarian, is_spicy, is_featured, sort_order) VALUES
-- Sandwiches
(1, 'Beef Kofte Kebap',      'Beef kofta with parsley, onion, tomato, pickles, and taratur sauce.',              12.99, NULL, 'Best seller', 0, 0, 1, 1),
(1, 'Adana Kebab',           'Seasoned beef with parsley, onion, tomato, pickles, and taratur sauce.',           12.99, NULL, NULL,          0, 1, 1, 2),
(1, 'Chicken Shawarma',      'Chicken, pickles, fries, and homemade garlic sauce wrapped in grilled pita.',      11.99, NULL, NULL,          0, 0, 1, 3),
(1, 'Shish Tavuk Shawarma',  'Grilled shish tavuk, pickles, fries, and homemade garlic sauce in grilled pita.',  11.99, NULL, NULL,          0, 0, 0, 4),
(1, 'Falafel',               'Four falafel balls with onion, parsley, mint, tomato, pickles, and taratur sauce.',10.00, NULL, NULL,          1, 0, 0, 5),
-- Plates
(2, 'Beef Kofte Kebap',      'Two beef kofta skewers with rice or fries, grilled vegetables, taratur sauce, and tabule salad.', 24.99, NULL, NULL, 0, 0, 1, 1),
(2, 'Adana Kebab',           'Two Adana skewers with rice or fries, grilled vegetables, taratur sauce, and tabule salad.',      24.99, NULL, NULL, 0, 1, 0, 2),
(2, 'Chicken Shawarma',      'Chicken shawarma with rice or fries, garlic sauce, and tabule salad.',                            19.99, NULL, NULL, 0, 0, 0, 3),
(2, 'Falafel Plate',         'Four falafel balls with rice or fries, taratur sauce, and tabule salad.',                         17.99, NULL, NULL, 1, 0, 0, 4),
-- Sides
(3, 'Add Popcorn Chicken',   'Crispy popcorn chicken added to any plate or sandwich.',  6.00, '+', NULL, 0, 0, 0, 1),
(3, 'Fries',                 'Crispy golden fries.',                                    6.00, NULL, NULL, 1, 0, 0, 2),
(3, 'Rice',                  'Seasoned rice.',                                          6.00, NULL, NULL, 1, 0, 0, 3),
(3, 'Tabuli Bowl',           'Fresh tabule salad with parsley, tomato, and herbs.',      NULL, NULL, NULL, 1, 0, 0, 4),
-- Add-Ons
(4, 'Extra Taratur Sauce',   'A side of our house taratur.',                            NULL, NULL, NULL, 1, 0, 0, 1),
(4, 'Extra Garlic Sauce',    'Homemade garlic sauce, on the side.',                     NULL, NULL, NULL, 1, 0, 0, 2),
(4, 'Extra Pickles',         'House pickled vegetables.',                               NULL, NULL, NULL, 1, 0, 0, 3),
(4, 'Extra Meat',            'An additional portion of the meat of your choice.',        NULL, NULL, NULL, 0, 0, 0, 4),
-- Dessert
(5, 'Chopped Mouze',         'Sweet traditional mouze dessert.',                        NULL, NULL, NULL, 1, 0, 0, 1),
(5, 'Baklava',               'Traditional flaky pastry with nuts and sweet syrup.',     NULL, NULL, NULL, 1, 0, 1, 2),
-- Drinks
(6, 'Pop',                   'Chilled cans — Coke, Diet Coke, Sprite, Fanta.',           2.00, NULL, NULL, 1, 0, 0, 1),
(6, 'Juice',                 'Bottled juice — orange, apple or mango.',                  4.00, NULL, NULL, 1, 0, 0, 2);

-- ---------------------------------------------------------------------
-- Contact messages (also forwarded by SMS when Twilio is enabled)
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  phone      VARCHAR(40)  NULL,
  subject    VARCHAR(180) NULL,
  message    TEXT NOT NULL,
  sms_status VARCHAR(20)  NOT NULL DEFAULT 'skipped',
  sms_detail VARCHAR(255) NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Newsletter subscribers
-- ---------------------------------------------------------------------
CREATE TABLE subscribers (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  EMAIL MARKETING
--  Kept byte-for-byte in step with sql/email-marketing.sql, which is the
--  same block again as a stand-alone migration for databases that were
--  created before this feature existed.
-- =====================================================================
-- ---------------------------------------------------------------------
--  Reusable email templates
--  kind = 'welcome' marks the one that goes out automatically when
--  somebody joins the list. Exactly one template holds that flag.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_templates (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120)  NOT NULL,
  subject     VARCHAR(200)  NOT NULL,
  preheader   VARCHAR(200)  NULL,
  body_html   MEDIUMTEXT    NOT NULL,
  kind        VARCHAR(20)   NOT NULL DEFAULT 'campaign',
  is_locked   TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_templates_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Campaigns — one row per newsletter blast
--  The subject and body are copied out of the template when the campaign
--  is created, so editing a template later never rewrites history.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_campaigns (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  template_id      INT           NULL,
  name             VARCHAR(150)  NOT NULL,
  subject          VARCHAR(200)  NOT NULL,
  preheader        VARCHAR(200)  NULL,
  body_html        MEDIUMTEXT    NOT NULL,
  status           VARCHAR(12)   NOT NULL DEFAULT 'draft',
  total_recipients INT           NOT NULL DEFAULT 0,
  sent_count       INT           NOT NULL DEFAULT 0,
  failed_count     INT           NOT NULL DEFAULT 0,
  started_at       DATETIME      NULL,
  finished_at      DATETIME      NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_campaign_status (status),
  KEY idx_campaign_template (template_id),
  CONSTRAINT fk_campaign_template FOREIGN KEY (template_id)
    REFERENCES email_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Send queue — the recipient list is frozen the moment a send starts,
--  then worked through in small batches so no single request times out.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_queue (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT          NOT NULL,
  email       VARCHAR(150) NOT NULL,
  name        VARCHAR(120) NOT NULL DEFAULT '',
  status      VARCHAR(10)  NOT NULL DEFAULT 'pending',
  error       VARCHAR(255) NULL,
  sent_at     DATETIME     NULL,
  KEY idx_queue_work (campaign_id, status),
  CONSTRAINT fk_queue_campaign FOREIGN KEY (campaign_id)
    REFERENCES email_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Delivery log — every welcome, test and campaign email that left the
--  building. Deliberately has no foreign key: the log outlives whatever
--  campaign produced it.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  kind        VARCHAR(20)  NOT NULL DEFAULT 'campaign',
  campaign_id INT          NULL,
  email       VARCHAR(150) NOT NULL,
  subject     VARCHAR(200) NOT NULL,
  status      VARCHAR(10)  NOT NULL DEFAULT 'sent',
  detail      VARCHAR(255) NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_created (created_at),
  KEY idx_log_kind (kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Settings
--  INSERT IGNORE so re-running this file never clobbers a live mailbox
--  password that has already been changed in the admin panel.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('smtp_host',              'mail.unikakebab.com'),
('smtp_port',              '465'),
('smtp_secure',            'ssl'),
('smtp_user',              'noreply@unikakebab.com'),
('smtp_pass',              'Toronto1234!'),
('smtp_verify_cert',       '1'),
('mail_from_email',        'noreply@unikakebab.com'),
('mail_from_name',         'Unika Kebab'),
('mail_reply_to',          'info@unikakebab.ca'),
('email_enabled',          '1'),
('welcome_email_enabled',  '1'),
('email_batch_size',       '20'),
('email_footer_note',      'You are receiving this because you joined the Unika Kebab list.'),
('site_url',               ''),
('app_secret',             '');

-- ---------------------------------------------------------------------
--  Starter templates
--  The body is content only — the branded header, the store details and
--  the unsubscribe footer are added by the layout at send time, so an
--  email can never go out without a way off the list.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO email_templates (id, name, subject, preheader, kind, is_locked, body_html) VALUES
(
  1,
  'Welcome to the list',
  'Welcome to the Unika Kebab list, {{first_name}}',
  'A little thank you for joining — plus what lands in your inbox next.',
  'welcome',
  1,
  '<h2>Welcome, {{first_name}}.</h2>
<p>Thanks for joining the Unika list. You are now first in line for new skewers, seasonal plates and the occasional free baklava.</p>
<p>Here is what to expect: one short email a month. No noise, no daily nagging — just the good stuff coming off the charcoal.</p>
<p style="text-align:center"><a class="button" href="{{menu_url}}">See what we grill</a></p>
<hr>
<h3>While you are here</h3>
<ul>
  <li><strong>Order ahead</strong> — pickup is about twelve minutes. <a href="{{order_url}}">Start an order</a>.</li>
  <li><strong>Come say hi</strong> — {{address}}.</li>
  <li><strong>Big order or catering?</strong> Call {{phone}} and we will sort it out.</li>
</ul>
<p>See you by the grill.<br><strong>The {{site_name}} kitchen</strong></p>'
),
(
  2,
  'Monthly specials',
  'This month off the coals at {{site_name}}',
  'New skewers, a seasonal plate and something sweet.',
  'campaign',
  0,
  '<h2>Hi {{first_name}}, here is what is new.</h2>
<p>The grill has been busy. A few things worth making room for this month:</p>
<h3>Off the charcoal</h3>
<ul>
  <li><strong>Adana Kebab</strong> — hand-minced, properly spiced, still our loudest seller.</li>
  <li><strong>Beef Kofte Platter</strong> — two skewers, rice or fries, grilled vegetables and tabule.</li>
  <li><strong>Baklava</strong> — made the traditional way, syrup and all.</li>
</ul>
<p style="text-align:center"><a class="button" href="{{order_url}}">Order online</a></p>
<hr>
<p>Prefer to eat in? We are at {{address}} — the coals are lit from open to close.</p>
<p><strong>The {{site_name}} kitchen</strong></p>'
),
(
  3,
  'New item on the menu',
  'Something new just hit the grill',
  'One new skewer, straight off the charcoal.',
  'campaign',
  0,
  '<h2>New on the menu</h2>
<p>{{first_name}}, we added something and you are hearing about it first.</p>
<h3>Name of the new dish</h3>
<p>Two or three lines about what it is, how it is cooked and what comes with it. Keep it short — the picture in their head does the rest.</p>
<p style="text-align:center"><a class="button" href="{{menu_url}}">See it on the menu</a></p>
<blockquote>Swap this text for a line from a regular who tried it early. Nothing sells a skewer like somebody else raving about it.</blockquote>
<p>Hungry now? <a href="{{order_url}}">Order for pickup</a> or call {{phone}}.</p>
<p><strong>The {{site_name}} kitchen</strong></p>'
),
(
  4,
  'Holiday hours',
  '{{site_name}} — our hours over the holidays',
  'When the grill is on and when it is resting.',
  'campaign',
  0,
  '<h2>Our holiday hours</h2>
<p>Hi {{first_name}} — a quick note so nobody turns up to a closed door.</p>
<table>
  <tr><th>Day</th><th>Hours</th></tr>
  <tr><td>Friday</td><td>11:00 am – midnight</td></tr>
  <tr><td>Saturday</td><td>11:00 am – midnight</td></tr>
  <tr><td>Sunday</td><td>Closed</td></tr>
</table>
<p>Ordering ahead is the safe move on busy nights — the kitchen fires as soon as your order lands.</p>
<p style="text-align:center"><a class="button" href="{{order_url}}">Order ahead</a></p>
<p>Thanks for a great year.<br><strong>The {{site_name}} kitchen</strong></p>'
);
