# Discover Cambodia — plain PHP + Postgres (final package)

PHP 8.1+ (extension `pdo_pgsql`), vanilla JavaScript, no framework, no build step. Same design as the old site, same SQL (now Postgres).

## Run it
1. Database (Supabase or any Postgres): you already ran `001_travel_kh.sql`. Now run, in order:
   ```
   psql "$DATABASE_URL" -f database/002_php_features.sql
   psql "$DATABASE_URL" -f database/003_bookings.sql
   psql "$DATABASE_URL" -f database/004_partners.sql
   psql "$DATABASE_URL" -f database/005_final.sql
   psql "$DATABASE_URL" -f database/006_rewards.sql
   psql "$DATABASE_URL" -f database/007_live.sql
   ```
2. `cp .env.example .env` and fill in `DB_HOST`, `DB_PASS`, … (Supabase: Project Settings → Database). On a live site set `FORCE_HTTPS=1`.
3. **Copy your old `images/` and `uploads/` folders into `public/`** (~100 MB, not included).
4. Make `public/uploads/` and `storage/` writable by the web server.
5. `php -S localhost:8000 -t public` → http://localhost:8000  (XAMPP: put the folder in `htdocs`, enable `extension=pdo_pgsql`, and use `/travel-kh-php/public/`).
6. Check `/health.php` returns `{"ok":true}`.
7. Edit **Admin → Site settings**: `payment_info` (your bank/ABA details), `deposit_pct`, Police/Fire phone or Telegram, contact links.

## Website
- Sticky top nav on every screen size (hamburger on phone/tablet, no bottom nav), search, notification bell, EN/ខ្មែរ, light/dark.
- Home: hero + stats, sponsor slideshow, interactive map (25 hotspots), province cards, Top Places, All Places (4/3/2 per row + Show more), festivals (click → linked place), services, donate, footer.
- Place panel: slideshow, map/bookmark/trip/review, info, best time / getting there / things to do & avoid, reviews + rating, nearest hotels (with km), restaurants (with **menu**), Booking buttons.
- Province panel: places, weather, hotels, food, **shops**.
- Chat helper (no AI): plan a trip, find hotels/restaurants, explore, best time to visit, about, contact + Police/Fire buttons.
- Accounts: sign up / login (rate-limited), profile (photo, language, loyalty level, applications, trips, bookings, bookmarks).
- **Trip planner**: provinces, days (1–14), budget, transport (walk/tuk-tuk/car), pace, family/solo, interests → day-by-day plan ordered by distance; save to profile; add single places to a trip.
- **Bookings**: hotels & restaurants (loyalty discount, upfront deposit: restaurant $5, hotel 20%), guides, transport (50% deposit by default) — all with receipt upload at `/pay.php`.
- **Applications**: become a guide / transport partner (`/apply.php`), documents stored privately.
- **Donate** (`/donate.php`).

## Admin: `/admin`
Places, Provinces, Categories, Hotels, Restaurants, Restaurant menu, Province shops, Festivals, Sponsors, Top Places, Notifications, Guide/Transport applications, Hotel & restaurant bookings, Guides, Transport partners, Guide/Transport bookings, Donations, Users, Loyalty levels, Site settings. Image upload in every form that has an image field.
- New festival → everyone gets a notification. Application approved/rejected → that user is notified; approved applicants appear on the public Guides/Transport pages automatically.
- Booking status / payment confirmed → user is notified. **Completed** bookings add to the user's total spent → loyalty levels 1–5 (1%–5% off hotels & restaurants).
- Admin password: old plain-text passwords are upgraded to a hash on first login. Login is rate-limited.
- To manage another table, add one entry to `src/admin_config.php`.

## Structure
```
public/       web root: pages, api.php, admin/, assets/
src/          bootstrap (env, DB, helpers, security), i18n, view, upload, admin config   (outside web root)
storage/      private uploads (ID cards, receipts)  — never public
database/     001 your SQL, 002–005 new tables + triggers
```

## Left out on purpose / not included
- The old **reward-points** system (points, redeem) — replaced by the 5 loyalty discount levels.
- Online card payments: payment is bank transfer + receipt, confirmed by an admin.
- Opening-hours validation on restaurant bookings (the venue confirms the time).

## Deploy on Railway
1. Put the project in a (private) GitHub repo. Copy your old `images/` into `public/images/` first (and old `uploads/` into `public/uploads/`) so they are deployed too.
2. Railway → New Project → Deploy from GitHub repo (it uses the `Dockerfile`).
3. Variables: `DB_HOST`, `DB_PORT=5432`, `DB_NAME=postgres`, `DB_USER`, `DB_PASS`, `DB_SSLMODE=require`, `FORCE_HTTPS=1`, `TRUST_PROXY=1` (use the Supabase **pooler** details).
4. Add a **Volume** to the service with mount path `/data` (keeps uploads, ID cards and receipts between deploys).
5. Settings → Networking → Generate Domain, then open `/health.php`.
