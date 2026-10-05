<?php
require __DIR__ . '/../src/bootstrap.php';
$a = $_GET['a'] ?? '';
$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

try {
    switch ($a) {
    case 'search':
        $t = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($t) < 2) json_out([]);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $t) . '%';
        $out = [];
        foreach (rows('select slug,name_en,name_kh from provinces where name_en ilike ? or name_kh ilike ? order by sort_order limit 3', [$like, $like]) as $r)
            $out[] = ['type' => 'province', 'slug' => $r['slug'], 'label' => pick($r), 'sub' => 'Province'];
        foreach (rows('select p.id,p.name_en,p.name_kh,v.name_en sub from places p join provinces v on v.id=p.province_id where p.is_active=1 and (p.name_en ilike ? or p.name_kh ilike ?) order by p.is_featured desc limit 6', [$like, $like]) as $r)
            $out[] = ['type' => 'place', 'id' => (int)$r['id'], 'label' => pick($r), 'sub' => $r['sub']];
        json_out($out);

    case 'notes':
        $uid = $_SESSION['uid'] ?? 0;
        json_out(rows('select id,type,title_en,title_kh,body_en,body_kh,link from notifications where user_id is null or user_id=? order by id desc limit 15', [$uid]));

    case 'settings':
        json_out(array_column(rows("select key,value from app_settings where key in ('police_call','fire_call','police_telegram','fire_telegram','contact_telegram','contact_email')"), 'value', 'key'));

    case 'place':
        $p = row('select p.*, c.name_en cat_en, c.name_kh cat_kh, c.color cat_color, v.name_en prov_en, v.name_kh prov_kh, v.slug prov_slug
                  from places p left join categories c on c.id=p.category_id join provinces v on v.id=p.province_id where p.id=? and p.is_active=1', [(int)($_GET['id'] ?? 0)]);
        if (!$p) json_out(['error' => 'not found'], 404);
        $p['views'] = bump_view('place', (int)$p['id']);
        $hcols = "id,name,type,rating,address,price_range,image_url,images,contact_number,telegram_link,facebook_url, coalesce((select views from item_views v where v.item_type='hotel' and v.item_id=hotels.id),0) as views";
        if ($p['latitude'] !== null && $p['longitude'] !== null) {   // nearest hotels first
            $hotels = rows("select $hcols, round(cast(6371 * acos(least(1, greatest(-1, cos(radians(cast(? as float8))) * cos(radians(latitude)) * cos(radians(longitude) - radians(cast(? as float8))) + sin(radians(cast(? as float8))) * sin(radians(latitude))))) as numeric), 1) as distance_km
                           from hotels where province_id=? and is_active=1 order by distance_km nulls last, sort_order, id limit 12", [$p['latitude'], $p['longitude'], $p['latitude'], $p['province_id']]);
        } else {
            $hotels = rows("select $hcols from hotels where province_id=? and is_active=1 order by sort_order,id limit 12", [$p['province_id']]);
        }
        $reviews = rows('select r.rating,r.comment,r.created_at,u.name from reviews r join users u on u.id=r.user_id where r.place_id=? order by r.id desc limit 5', [$p['id']]);
        $rate = row('select round(avg(rating),1) avg, count(*) n from reviews where place_id=?', [$p['id']]);
        $food = rows('select id,restaurant_name_en,restaurant_name_kh,cuisine_type,opening_hours,price_range,image_url,images,contact_number,telegram_link,facebook_url, coalesce((select views from item_views v where v.item_type=\'restaurant\' and v.item_id=place_restaurants.id),0) as views from place_restaurants
                      where province_id=? order by coalesce(lower(place_name)=lower(?),false) desc, sort_order,id limit 12', [$p['province_id'], $p['name_en']]);
        $bm = !empty($_SESSION['uid']) ? (bool)val('select 1 from bookmarks where user_id=? and place_id=?', [$_SESSION['uid'], $p['id']]) : false;
        json_out(['place' => $p, 'hotels' => $hotels, 'food' => $food, 'bookmarked' => $bm, 'reviews' => $reviews, 'rating' => $rate]);

    case 'province':
        $pv = row('select * from provinces where slug=?', [$_GET['slug'] ?? '']);
        if (!$pv) json_out(['error' => 'not found'], 404);
        $id = $pv['id'];
        json_out([
            'province' => $pv,
            'places' => rows('select p.id,p.name_en,p.name_kh,p.image_url,p.images,c.color cat_color,c.name_en cat_en,c.name_kh cat_kh, coalesce((select views from item_views v where v.item_type=\'place\' and v.item_id=p.id),0) as views from places p left join categories c on c.id=p.category_id where p.province_id=? and p.is_active=1 order by p.sort_order,p.id', [$id]),
            'hotels' => rows('select id,name,type,rating,address,price_range,image_url,images,contact_number,telegram_link,facebook_url, coalesce((select views from item_views v where v.item_type=\'hotel\' and v.item_id=hotels.id),0) as views from hotels where province_id=? and is_active=1 order by sort_order,id', [$id]),
            'food'   => rows('select id,restaurant_name_en,restaurant_name_kh,cuisine_type,opening_hours,price_range,image_url,images,contact_number,telegram_link,facebook_url, coalesce((select views from item_views v where v.item_type=\'restaurant\' and v.item_id=place_restaurants.id),0) as views from place_restaurants where province_id=? order by sort_order,id', [$id]),
            'weather'=> rows('select month_num,month_name,season,avg_temp_c from weather_months where province_id=? order by month_num', [$id]),
            'shops'  => rows('select id,name_en,name_kh,shop_type,description_en,description_kh,merchandise_en,merchandise_kh,address_en,address_kh,opening_hours,price_level,image_url,images,phone,website_url from province_shops where province_id=? and is_active=1 order by sort_order,id', [$id]),
        ]);

    case 'view':
    case 'event':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
        check_csrf();
        if ($a === 'view') json_out(['views' => bump_view((string)($body['type'] ?? ''), (int)($body['id'] ?? 0))]);
        if (in_array($body['name'] ?? '', ['login_prompt_shown', 'login_prompt_login', 'login_prompt_signup'], true) && !is_bot()) stat_bump($body['name']);
        json_out(['ok' => true]);

    case 'provinces_list':
        json_out(rows('select slug,name_en,name_kh from provinces order by name_en'));

    case 'menu':
        json_out(rows('select id,name_en,name_kh,category,price_usd,image_url,description_en from restaurant_menu_items where restaurant_id=? and is_active=1 order by sort_order,name_en', [(int)($_GET['restaurant_id'] ?? 0)]));

    case 'bookmark':
    case 'review':
    case 'trip_add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
        check_csrf();
        $uid = $_SESSION['uid'] ?? 0;
        if (!$uid) json_out(['error' => 'login'], 401);
        $pid = (int)($body['place_id'] ?? 0);
        if ($a === 'bookmark') {
            if (val('select 1 from bookmarks where user_id=? and place_id=?', [$uid, $pid])) { q('delete from bookmarks where user_id=? and place_id=?', [$uid, $pid]); json_out(['bookmarked' => false]); }
            q('insert into bookmarks (user_id,place_id) values (?,?)', [$uid, $pid]); json_out(['bookmarked' => true]);
        }
        if ($a === 'trip_add') {      // add a place to the user's latest trip (creates "My trip" the first time)
            $tid = val('select id from trip_plans where user_id=? order by id desc limit 1', [$uid]) ?: val("insert into trip_plans (user_id,title) values (?,'My trip') returning id", [$uid]);
            if (!val('select 1 from trip_places where trip_id=? and place_id=?', [$tid, $pid])) q('insert into trip_places (trip_id,place_id,day_number) values (?,?,1)', [$tid, $pid]);
            json_out(['ok' => true]);
        }
        $rate = max(1, min(5, (int)($body['rating'] ?? 5)));
        q('insert into reviews (user_id,place_id,rating,comment) values (?,?,?,?)', [$uid, $pid, $rate, mb_substr((string)($body['comment'] ?? ''), 0, 1000)]);
        json_out(['ok' => true]);

    default:
        json_out(['error' => 'unknown action'], 400);
    }
} catch (Throwable $ex) {
    error_log($ex->getMessage());
    json_out(['error' => 'server error'], 500);
}
