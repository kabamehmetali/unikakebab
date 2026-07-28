# Unika Kebab — Charcoal Grill

A restaurant website with a public site and a password-protected admin panel.
Plain **PHP 8 + MySQL + Bootstrap 5 + vanilla JS**. No Composer, no build step, no frameworks.

---

## Getting started

1. Start **Apache** and **MySQL** in the XAMPP control panel.

2. Import the database (creates `unikakebab_db`, all tables and the seed content):

   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/unikakebab
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < sql/schema.sql
   ```

3. Open the site: <http://localhost/unikakebab/>
   Open the admin panel: <http://localhost/unikakebab/admin/>

   | | |
   |---|---|
   | Username | `admin` |
   | Password | `admin123` |

   **Change this immediately** in *Admin → Account*. The login screen stops showing the
   default credentials as soon as the password is changed.

Database credentials live in `includes/config.php` (`root`, no password). Set
`DEBUG_MODE` to `false` in that file before putting the site on a live server.

---

## What lives where

```
unikakebab/
├── index.php              Home
├── menu.php               Full menu, grouped by category
├── contact.php            Contact form → database (+ optional SMS)
├── locations.php          Address, hours, map, directions
├── order.php              Order Online landing page
├── subscribe.php          Newsletter sign-up handler
│
├── includes/
│   ├── app.php            Bootstrap — every page starts by requiring this
│   ├── config.php         DB credentials, paths, BASE_URL, session
│   ├── db.php             PDO connection + db_all/db_one/db_run
│   ├── functions.php      Every shared helper (settings, store, menu, CSRF, uploads, SMS)
│   ├── header.php         <head> + navbar
│   ├── hero.php           Standard inner-page hero
│   ├── footer.php         Footer (store details, hours, socials) + scripts
│   ├── flash.php          Renders queued flash messages
│   └── newsletter.php     Newsletter band partial
│
├── assets/
│   ├── css/style.css      The whole public design system
│   ├── js/main.js         Sticky nav, scroll reveal, menu nav, back-to-top
│   └── img/               Logo, hero backgrounds, ornament, placeholder
│
├── uploads/               Admin uploads (menu/, site/, store/) — PHP execution disabled
│
├── admin/
│   ├── login.php  logout.php  index.php (dashboard)
│   ├── home.php           Edit every part of the home page
│   ├── categories.php     Menu categories
│   ├── items.php          Menu items
│   ├── store.php          Store details, opening hours, socials
│   ├── messages.php       Contact form inbox
│   ├── subscribers.php    Newsletter list (+ CSV export)
│   ├── settings.php       Brand, per-page SEO meta, Google Maps, Twilio
│   ├── account.php        Your login details
│   ├── includes/          auth.php, header.php (sidebar), footer.php
│   └── assets/            admin.css, admin.js
│
├── sql/schema.sql         Schema + seed data
└── content/               The original source files you supplied (not served)
```

---

## Admin panel

| Screen | What you can change |
|---|---|
| **Dashboard** | Counts, what still needs configuring, latest messages and subscribers |
| **Home Page** | Every heading, paragraph, button and image on the home page, plus the "Why Unika" cards |
| **Categories** | Add / rename / reorder / hide menu categories |
| **Menu Items** | Add / edit / price / photo / hide items; mark them spicy, vegetarian or featured |
| **Store Info** | Address, phone, email, opening hours, social links, map position, directions and parking |
| **Messages** | Everything sent through the contact form, with SMS status and one-click reply |
| **Subscribers** | The newsletter list, with CSV export |
| **Settings** | Site name, logo, favicon, per-page SEO meta, Google Maps key, Twilio SMS |
| **Account** | Your name, username, email and password |

Anything marked **featured** on an item shows up on the home page. Items with an empty
price simply do not display a price — useful for the desserts and add-ons.

---

## Contact form → SMS

Messages are always saved to the `contact_messages` table. Forwarding them by text
message is optional:

1. *Admin → Settings → Twilio SMS*
2. Fill in **Account SID**, **Auth Token**, the Twilio **from** number (`+1…`) and the
   mobile number that should **receive** alerts.
3. Turn **Enable SMS forwarding** on and press **Send test message**.

Each message row records whether the SMS was `sent`, `failed` or `skipped`, along with
Twilio's response, so failures are easy to diagnose. A failed SMS never loses the message.

### API keys are masked

Stored keys are shown as `AC80••••••••••••f5e0` — first four and last four characters
only. Saving the form without touching a key leaves it untouched; **Show** reveals the
full value when you need it.

---

## Google Maps

Paste the key into *Admin → Settings → Google Maps*. The key needs the **Maps Embed API**
enabled. Without a key the Contact and Locations pages fall back to a button that opens
Google Maps in a new tab, so nothing breaks.

---

## Menu photos

No dish photos are set yet, so the site uses `assets/img/placeholder.svg`. You can upload a
custom fallback in *Admin → Menu Items → Menu item placeholder*, or add real ones through
*Admin → Menu Items → Edit → Image*.

Every upload is resized so its longest side is at most 1600px and re-encoded to keep the
file small. WebP is used when your PHP build supports it; otherwise transparent images are
saved as PNG and photos as JPEG. XAMPP's bundled PHP usually has no WebP support, which is
why uploads here land as `.jpg` / `.png` — that is expected, not a fault.

Items on the menu page render as a clean text row when they have no photo, so a half-finished
photo set never looks broken.

---

## Notes

- **SEO** — per-page title, description and keywords are editable in Settings, and each
  page already outputs Open Graph tags and Restaurant structured data.
- **Accessibility** — skip link, visible focus rings, real labels on every field, and all
  animation is disabled for visitors who prefer reduced motion.
- **Sub-folder safe** — `BASE_URL` is detected at runtime, so the site works at
  `/unikakebab/` locally and at a domain root once deployed.
- **Clean URLs** — pages are served without the `.php`: `/menu`, `/contact`, `/admin/items`,
  and the home page is simply `/`. Two pieces make that work together:
  `url()` in `includes/functions.php` strips the extension from every link the site prints,
  and the rewrite block in `.htaccess` maps the clean address back to the real file.
  Old `.php` addresses (and any trailing slash) 301-redirect to the clean form, so existing
  links and bookmarks keep working and search engines only index one address per page.
  Requires `mod_rewrite` and `AllowOverride All` — both are on in XAMPP by default.
  Always link with `url()` / `admin_url()`; never hard-code a path.
