-- =====================================================================
--  UNIKA KEBAB — Email marketing add-on
--  Templates, campaigns, the send queue and the delivery log.
--
--  Run this once against an existing database:
--      mysql -u root unikakebab_db < sql/email-marketing.sql
--
--  A fresh install does not need it — sql/schema.sql already contains
--  everything below.
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
('app_secret',             ''),
('meta_unsubscribe_title', 'Unsubscribe | Unika Kebab Charcoal Grill'),
('meta_unsubscribe_desc',  'Leave the Unika Kebab mailing list. One click and your address is deleted for good.'),
('unsubscribe_hero_eyebrow', 'Mailing list'),
('unsubscribe_hero_title',   'Leaving the list'),
('unsubscribe_hero_text',    'No hard feelings — confirm below and your address is deleted from our records straight away.');

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
  <li><strong>Order ahead</strong> — choose delivery or pickup. <a href="{{order_url}}">Start an order</a>.</li>
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
