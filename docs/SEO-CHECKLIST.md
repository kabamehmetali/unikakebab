# Unika Kebab — SEO action list

Written 20 August 2026. Everything here was checked against the live site, the
live DNS and the live Google Maps listing rather than assumed.

Items marked **[OWNER]** cannot be done in code. They are also, honestly, the
ones that matter most: the site's HTML was never the reason the business is
hard to find.

Items marked **[DONE]** are already in this repository and ship with the next
deploy.

---

## What is actually wrong

Two separate problems have been getting mixed together.

**1. "Google Maps doesn't show my page as Unika Kebab."**
Not a website problem. The Google Business Profile exists —
`https://maps.google.com/?cid=2260159852602194699` — and its name on Google is
literally **`Unika kebab`**, lowercase k. That exact string appears nowhere on
the website, so there was no matching name token tying the listing to the site.

Underneath that is the harder problem: **133 Jefferson Ave has a decade of
citations pointing at other restaurants.**

| Previous tenant | What still exists online |
|---|---|
| SKCOOKKS / SK COOKKS | 4.1★ across ~370 reviews; live entries on Yelp, DoorDash, SkipTheDishes, ByBlacks, Restaurantji |
| THE BODEGA BY SK | UberEats store titled "THE BODEGA BY SK (133 Jefferson Avenue)" |
| MAIZAL / El Maizal | Yelp (113 reviews, 142 photos), YellowPages |

Google's understanding of that address is anchored to them. A brand-new pin
with no reviews does not displace ~500 reviews of accumulated history on its
own — it has to be told, explicitly, that the others are gone.

**2. "Only about 4 links show for site:unikakebab.com."**
This is close to normal, and `site:` is not a coverage metric anyway.

- `unikakebab.com` was registered **2026-06-26 — 55 days ago**.
- It has **never been captured by the Wayback Machine**.
- There are **no inbound links or directory citations** anywhere.

Every technical check passes: all six URLs return 200, canonicals are
self-referential and correct, Googlebot receives byte-identical HTML, and
robots.txt and sitemap.xml are correct. There is no block to remove. The real
answer is that the site is eight weeks old and nothing links to it.

---

## Tier 0 — do this week

### 1. [OWNER] Claim and fix the Google Business Profile
The single highest-value thing on this list.

- Open `https://maps.google.com/?cid=2260159852602194699` and claim it.
- **Rename it to exactly `Unika Kebab`.** Match the website. Do not append
  "Charcoal Grill", "Best Kebab Toronto" or anything else — keyword-stuffed
  names get listings suspended, and the point of the change is that one string
  now matches everywhere.
- Primary category: **Turkish restaurant**. Secondary: Kebab shop, Halal
  restaurant.
- Attach the website `https://unikakebab.com`, the phone `+1 416-516-2087`,
  and the real opening hours.
- Upload **20+ real photos** — the food, the grill, the counter, the frontage.

### 2. [OWNER] Report the predecessor listings as closed
On Google Maps, for each of **SKCOOKKS**, **THE BODEGA BY SK**, and
**MAIZAL / El Maizal** at 133 Jefferson Ave: *Suggest an edit → Close or remove
→ Permanently closed*.

Until the address stops resolving to three defunct restaurants, the new listing
is competing with their history.

### 3. [OWNER] Verify Google Search Console
Nobody can currently see the real indexing data — Bing's verification tag is on
the site, Google's is not.

1. Add `unikakebab.com` at `search.google.com/search-console`.
2. Take the HTML-tag verification code.
3. Paste it into **Admin → Settings → SEO → Google verification**. The field is
   already wired into the page head; it just needs the value.
4. Submit `https://unikakebab.com/sitemap.xml`.
5. From then on read **Page Indexing**, not the `site:` operator.

### 4. [OWNER] Register unikakebab.ca
`unikakebab.ca` is **not registered** — confirmed against CIRA, which returns
"Not found". The site was publishing `info@unikakebab.ca`, so that mail was
being silently lost, and anyone could have registered the domain and started
receiving mail addressed to the restaurant.

The code now uses `info@unikakebab.com` (which has working MX at
`mail.unikakebab.com`). Registering the `.ca` anyway costs about $15/year and
closes the impersonation risk. Point it at the `.com` with a 301.

### 5. [OWNER] Restrict the Google Maps API key
The locations page now embeds a live route map, so the browser key
(`AIzaSy…ht7Q`) is visible in the page source. That is normal and unavoidable
for a browser key — what matters is that it is **restricted**, or anyone can
copy it and bill their own map loads to you.

In Google Cloud Console → Credentials → that key:
- **Application restrictions** → HTTP referrers → `https://unikakebab.com/*`
  and `https://www.unikakebab.com/*`.
- **API restrictions** → Maps Embed API only.

### 6. Deploy the code changes
```bash
mysql -u root unikakebab_db < sql/neighbourhoods.sql
mysql -u root unikakebab_db < sql/toronto-zones.sql
mysql -u root unikakebab_db < sql/seo-nap-fixes.sql
mysql -u root unikakebab_db < sql/neighbourhoods-seed.sql
mysql -u root unikakebab_db < sql/toronto-zones-seed.sql
```
All five files are safe to run more than once.

**Do not deploy by pulling alone.** `.htaccess` is not tracked in git, and it
carries the rewrite rule that makes the neighbourhood URLs resolve. Copy it
across explicitly or every one of those pages 404s.

---

## Tier 1 — weeks 1 to 4

### 7. [OWNER] Build the first citations
There are currently **zero**. Use exactly this NAP everywhere, character for
character — inconsistency here is what keeps a Maps listing weak:

```
Unika Kebab
133 Jefferson Ave, Toronto, ON M6K 3E4
+1 416-516-2087
https://unikakebab.com
```

Yelp · TripAdvisor · Zomato · Foursquare · Zabihah · thehalalfood.ca ·
YellowPages.ca · Apple Business Connect · Bing Places · Liberty Village BIA.

### 8. [OWNER] Reviews
Review count and velocity are what decide the local map pack — not page copy.
The listing currently has none, against ~370 for the restaurant that used to be
in the same room. A QR code at the counter pointing at the review form is the
cheapest fix available.

### 9. [OWNER] Photography
The site has **two distinct photographs**. `hero-home.jpg` and `hero-menu.jpg`
are byte-identical files, and 25 of 33 images on the menu page are the same
placeholder. Every neighbourhood page also wants its own images. This is the
biggest remaining content gap.

### 10. [OWNER] Local press
A directly comparable restaurant — **Mozy's Charcoal, 114 Atlantic Ave**, about
600 m away, also charcoal kebab — launched with coverage in Toronto Life,
blogTO, Daily Hive, Streets of Toronto and Sharp. Unika got none.

That gap, not the HTML, is the reason one is visible and the other is not.
Pitch blogTO, Narcity, Daily Hive and Streets of Toronto. The
new-opening window closes fast.

### 11. [OWNER] One brand name everywhere
There are currently five spellings in circulation: `Unika kebab` (Google),
`Unika Kebab | Charcoal Grill` (home page title), `Unika Kebab Charcoal Grill`
(other titles), `Unika Kebab` (structured data), `unikakebab` (TikTok).

Settle on **Unika Kebab** and change TikTok, Instagram and Facebook to match.

---

## Tier 2 — worth a decision, not urgent

### 12. The Square site is a second website
`unikakebab.square.site` is fully crawlable, has its own sitemap and about
20 product pages. It competes with `unikakebab.com` for brand searches and
splits the signals between two hosts. Either keep it purely as a checkout that
customers reach from the main site, or accept that it is a second storefront in
the index.

### 13. The Square menu is bigger than the website menu
Square lists Ayran, Hummus, Baba Gannouc, Falafel Salad and several specials
that `unikakebab.com/menu` does not. Adding them is free content on the page
that most deserves it.

---

## Image metadata

Seven files in `content/` carried **C2PA Content Credentials** — the embedded
provenance manifest — declaring
`digitalSourceType/trainedAlgorithmicMedia` with `gpt-image` as the software
agent:

```
content/bgstone.png        content/homeherobg.png     content/menubg.png
content/contact.png        content/menu ormenant.png  content/menuherobg.png
content/Logo/reallogo.png
```

All metadata has been stripped from every image in the project — 28 files
rewritten, and a byte-level scan of all 33 image files now comes back clean.

Two things worth knowing:

- **Nothing public was ever affected.** Everything the site actually serves —
  `assets/img/*` and `uploads/site/*` — was already clean, because the uploader
  re-encodes through GD and `assets/` was exported rather than copied. `content/`
  is print artwork, blocked by `.htaccess` (403) and disallowed in robots.txt,
  so those files were never reachable anyway.
- **The pixels are untouched.** Every one of the 29 raster files was decoded
  before and after and compared: identical pixel data, zero changes. No
  dimension changed and no colour shifted — none of the files carried a real
  ICC profile, only the plain sRGB flag, which is what a browser assumes for an
  untagged image regardless.

### One thing stripping cannot do

The manifests recorded `c2pa.watermarked.unbound` among their actions, which
means an **invisible watermark may have been applied to the pixels themselves**.
That is not metadata — it lives in the image data — so removing the manifest
does not remove it, and there is no reliable way to detect or strip it. If that
matters, the answer is different source images, not different metadata.

### Keeping it that way

Admin uploads normally go through GD, which discards metadata as a side effect
of re-encoding. There was a fallback path — used when GD is unavailable or
every encode attempt fails — that kept the uploaded file byte for byte,
metadata and all. `strip_image_metadata()` in `includes/functions.php` now
closes that: it removes EXIF, XMP, IPTC and C2PA from JPEG, PNG and WebP
without re-encoding, keeps ICC profiles so colours do not shift, and abandons
the whole operation if anything in the byte stream looks wrong rather than
risking a corrupted upload.

To re-check the project at any time:

```bash
exiftool -r -a -u -G1 assets uploads content | grep -iE 'c2pa|jumbf|gpt-image'
```

And to strip everything again after adding new artwork:

```bash
exiftool -all= -overwrite_original -r -ext png -ext jpg -ext webp assets uploads content
```

---

## The Toronto zone directory

The "Getting here" list has moved off the footer, where it was five links in a
narrow column, and onto `/locations`, where it has room to be useful.

It now covers **all 158 of the City of Toronto's official neighbourhoods**,
grouped by the six former municipalities — Old Toronto (50), North York (37),
Scarborough (30), Etobicoke (22), York (11), East York (8). Pick one and the
map draws the real driving route to the shop.

None of the numbers are typed in by hand:

| Field | Where it comes from |
|---|---|
| Names and boundaries | City of Toronto Open Data, *Neighbourhoods* (158, EPSG:4326) |
| District | Point-in-polygon of each centroid against *Former Municipality Boundaries*. All 158 matched outright |
| Latitude / longitude | Area-weighted centroid of each neighbourhood's largest polygon |
| Distance | Haversine from 133 Jefferson Ave |
| Driving route and time | Google, live, in the embedded map |

The distance in the list is a **straight line** and the page says so, because
the map beside it shows the real driving distance — which past the Don Valley
is close to double. Two unexplained numbers side by side would read as a bug.

**Why 158 rows of data and only five written pages.** A directory entry that
answers "how far is that from me?" is useful at any distance. A written page
about kebab in Rouge, 28 km away, would not be — it would be one of the 158
near-identical pages Google's spam policy exists to catch. The infrastructure
lets you promote any zone to a full page from Admin → Neighbourhoods later, if
one turns out to be worth writing.

To re-derive the whole thing after a City boundary revision, the two source
files are `sql/toronto-zones.sql` (schema) and `sql/toronto-zones-seed.sql`
(data, with its provenance recorded in the header).

---

## The neighbourhood pages

Five are built. **They are deliberately not all published.**

| Batch | Page | Publish when |
|---|---|---|
| 1 | King West | After the current Google spam update finishes rolling |
| 2 | Parkdale, Exhibition Place | 3–4 weeks after batch 1, if batch 1 held |
| 3 | Queen West, Fort York | 3–4 weeks after batch 2, if batches 1–2 are earning |

Publish from **Admin → Neighbourhoods** with the Publish button. An unpublished
page is a hard 404, is absent from the sitemap, and is linked from nowhere.

**Why staged.** Google began rolling out a spam update on **18 August 2026**
(confirmed on Google's own Search Status Dashboard) and its stated focus is
scaled content. A batch of geo pages landing mid-rollout is the worst possible
timing. More importantly, releasing one page first turns this from a guess into
a test with a stop button: if page one does not get indexed and hold, pages two
to five will not either, and the right response is to fix the approach rather
than publish more.

### ⚠ The King West page has a shelf life — read this before publishing it

Its hook is "one westbound 504 King, no transfer, off at King St W at Jefferson
Ave." That is true today. **It stops being true on 1 November 2026.**

Confirmed on ttc.ca and in press coverage: from Sunday 1 November 2026 the
504/304 King diverts via **Queen Street West between Spadina and Roncesvalles**
for watermain and track renewal running to **August 2027**. Jefferson Ave sits
inside that bypassed stretch. **504C replacement buses** will run on King
instead, so the journey still works — the vehicle changes from a streetcar to a
bus, and "no transfer" may not survive.

The page already says the diversion is coming and tells readers to check
ttc.ca, so it is honest. But plan for one of these:

- **Publish King West now and diarise a rewrite for late October.** It gets
  roughly six weeks of full accuracy, which is enough to learn whether the
  format indexes at all.
- **Or lead with Parkdale instead.** Its writer deliberately routed readers via
  the 501 Queen and 29 Dufferin *because* of this closure, so it does not go
  stale in November. It is also the strongest page of the five on the swap
  test.

The second option is the safer one if you are not confident of finding time to
rewrite in October.

### Two known content gaps

- **Images.** Each page carries a share image, but there are only three real
  photographs to go round five pages. Two pages reuse one. Reused imagery is
  itself a weak duplicate signal — real per-page photos are the fix.
- **Dish picks.** Chicken Shawarma and Baklava each appear on four of five
  pages. The *reasons* given are genuinely different every time (measured
  cross-page text overlap is 1.4%), but the cards look repetitive side by side.
  Worth varying as batches 2 and 3 come up.
- **Menu photos.** Every dish card on the King West page currently renders
  "No image uploaded yet", because the menu items have no photographs — the
  same reason 25 of 33 images on `/menu` are the placeholder. It is the weakest
  thing on an otherwise strong page. Photographing the six or seven dishes
  these pages actually name would fix `/menu` at the same time.

### The opening hours are written into the copy

Every neighbourhood page states the hours in prose — "open 12 to 9", "the grill
goes cold at nine", "we stop at 21:00" — because a page that tells you to catch
a streetcar has to tell you whether you will arrive before the shutter comes
down. **If the hours ever change, these sentences have to be edited by hand**
in Admin → Neighbourhoods. The hours block in the footer updates itself; this
prose does not.

The copy was written against the hours the live site publishes today —
**12:00 to 21:00, seven days**, confirmed from the structured data on
unikakebab.com. Confirm that is still right before publishing. (The local
development database holds older hours, 11:00–22:00; that is stale dev data,
not a discrepancy on the live site.)

**Why there is no Liberty Village page.** The restaurant *is* in Liberty
Village — 280 m from its centre. A `/kebab-in-liberty-village` page would put
the home page, `/locations` and that page in a three-way fight over one query.
The home page now claims that ground instead, which is the correct place for
it and the strongest URL on the domain.

**What keeps these from being doorway pages.** Google's spam policy names
"pages targeted at specific regions or cities that funnel users to one page"
explicitly, and the penalty is site-wide — thin pages would drag `/menu` and
`/order` down with them. Each page therefore has to carry real, checkable local
detail: route numbers, stop names, walking times, parking. The test to apply
before publishing any of them is simple:

> Swap the neighbourhood name for a different one. If the page is still
> factually true, it should not be published.

---

## What this can and cannot achieve

**"Best kebab in toronto" — top three organic is not realistic.** Page one for
that query is roughly 90% listicles and directories (blogTO, Yelp, TripAdvisor,
Wanderlog). Google has decided the query wants comparison content, and a single
restaurant is not comparison content. The restaurants that do break through
have thousands of reviews behind them.

The better goal is to be named *inside* those listicles. A mention in blogTO's
round-up is worth more real traffic than an eighth-place organic slot.

**What is genuinely winnable:**

| Target | Timeline | Confidence |
|---|---|---|
| The Maps listing resolving cleanly as "Unika Kebab" | 1–4 weeks after claiming it | High |
| "kebab liberty village", "charcoal kebab liberty village" | 4–10 weeks | High — no competitor site holds it |
| All six existing pages indexed | 2–6 weeks after Search Console | High |
| Local pack for "kebab near me" within ~2 km | 8–16 weeks | Medium-high — gated on reviews, not content |
| "kebab king west", "kebab parkdale" | 6–12 weeks after those pages ship | Medium |
| "turkish restaurant toronto" | 6–12 months | Medium — the least crowded city-wide term, and a better headline goal than "best kebab in toronto" |
