<?php
/** Every section of the dashboard. To manage a new table, add one entry here. */
function admin_resources(): array {
    static $out;
    if ($out) return $out;
    $T = fn($k, $l, $list = false) => ['key' => $k, 'label' => $l, 'list' => $list];
    $A = fn($k, $l) => ['key' => $k, 'label' => $l, 'type' => 'textarea'];
    $N = fn($k, $l, $list = false) => ['key' => $k, 'label' => $l, 'type' => 'number', 'list' => $list];
    $F = fn($k, $l, $def = 1) => ['key' => $k, 'label' => $l, 'type' => 'flag', 'def' => $def];
    $S = fn($k, $l, $o, $list = false) => ['key' => $k, 'label' => $l, 'type' => 'select', 'options' => $o, 'list' => $list];
    $R = fn($k, $l, $tb, $col, $list = true) => ['key' => $k, 'label' => $l, 'type' => 'select', 'ref' => [$tb, $col], 'list' => $list];
    $IMGS = fn($k = 'images', $label = 'Pictures (Img 1 = main picture)', $sync = 'image_url', $single = false) => ['key' => $k, 'label' => $label, 'type' => 'images', 'sync' => $sync, 'single' => $single];
    $PROV = $R('province_id', 'Province', 'provinces', 'name_en');
    $D = fn($k, $l) => ['key' => $k, 'label' => $l, 'type' => 'date'];

    $out = [
      'places' => ['title' => 'Places', 'group' => 'Content', 'table' => 'places', 'fields' => [
        $PROV, $R('category_id', 'Category', 'categories', 'name_en'), $T('name_en', 'Name (EN)', true), $T('name_kh', 'Name (KH)', true),
        $A('description_en', 'Description (EN)'), $A('description_kh', 'Description (KH)'), $A('highlights_en', 'Highlights (one per line)'), $A('highlights_kh', 'Highlights (KH)'), $A('things_to_do_en', 'Things to do (one per line)'), $A('things_avoid_en', 'Things to avoid (one per line)'), $T('best_time_en', 'Best time (EN)'), $T('best_time_kh', 'Best time (KH)'), $A('getting_there_en', 'Getting there'), $T('main_product_en', 'Main product (EN)'), $T('main_product_kh', 'Main product (KH)'), $A('services_en', 'Services'), $T('icon', 'Icon'), $S('zone', 'Zone (trip planner)', ['city', 'near', 'mid', 'far']), $F('kids_friendly', 'Kids friendly', 0), $F('picnic_friendly', 'Picnic friendly', 0), $F('on_site_food', 'Food on site', 0),
        $IMGS(), $N('latitude', 'Latitude'), $N('longitude', 'Longitude'), $T('opening_hours', 'Opening hours'),
        $T('entry_fee', 'Entry fee (text)'), $N('entry_fee_usd', 'Entry fee USD'), $N('estimated_food_usd', 'Food estimate USD'), $N('avg_visit_hrs', 'Visit hours'),
        $T('duration', 'Time needed'), $S('difficulty', 'Difficulty', ['Easy', 'Moderate', 'Challenging']), $T('planner_tags', 'Planner tags'),
        $F('is_featured', 'Featured', 0), $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'provinces' => ['title' => 'Provinces', 'group' => 'Content', 'table' => 'provinces', 'fields' => [
        $T('slug', 'Slug', true), $T('name_en', 'Name (EN)', true), $T('name_kh', 'Name (KH)', true), $T('region', 'Region', true),
        $A('description_en', 'Description (EN)'), $A('description_kh', 'Description (KH)'), $T('emoji', 'Emoji'), $T('cover_color', 'Cover color (#hex)'),
        $IMGS('cover_image', 'Cover pictures (Img 1 = main picture)', null), $N('latitude', 'Latitude'), $N('longitude', 'Longitude'), $F('is_featured', 'Featured', 0), $N('sort_order', 'Sort order')]],
      'categories' => ['title' => 'Categories', 'group' => 'Content', 'table' => 'categories', 'fields' => [
        $T('slug', 'Slug', true), $T('name_en', 'Name (EN)', true), $T('name_kh', 'Name (KH)', true), $T('icon', 'Icon'), $T('color', 'Color (#hex)', true), $N('sort_order', 'Sort order')]],
      'hotels' => ['title' => 'Hotels', 'group' => 'Content', 'table' => 'hotels', 'fields' => [
        $PROV, $T('name', 'Name', true), $S('type', 'Type', ['Hotel', 'Guesthouse', 'Hostel', 'Resort', 'Homestay'], true), $T('price_range', 'Price range', true), $N('rating', 'Rating'),
        $T('address', 'Address'), $T('amenities', 'Amenities'), $IMGS(), $N('latitude', 'Latitude'), $N('longitude', 'Longitude'),
        $T('contact_number', 'Phone'), $T('telegram_link', 'Telegram link'), $T('facebook_url', 'Facebook URL'), $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'restaurants' => ['title' => 'Restaurants', 'group' => 'Content', 'table' => 'place_restaurants', 'fields' => [
        $PROV, $T('province_name', 'Province name (text)'), $T('place_name', 'Near place'), $T('restaurant_name_en', 'Name (EN)', true), $T('restaurant_name_kh', 'Name (KH)'),
        $T('cuisine_type', 'Cuisine', true), $A('description_en', 'Description'), $IMGS(), $T('opening_hours', 'Opening hours'), $T('price_range', 'Price range'),
        $T('contact_number', 'Phone'), $T('telegram_link', 'Telegram link'), $T('facebook_url', 'Facebook URL'), $F('is_province_wide', 'Province-wide', 0), $N('sort_order', 'Sort order')]],
      'festivals' => ['title' => 'Festivals', 'group' => 'Content', 'table' => 'festivals', 'fields' => [
        $T('name_en', 'Name (EN)', true), $T('name_kh', 'Name (KH)', true), $T('icon', 'Icon'), $T('when_en', 'When (e.g. April 13–16)', true), $T('duration_en', 'Duration'),
        $T('vibe_en', 'Vibe'), $A('description_en', 'Description (EN)'), $A('description_kh', 'Description (KH)'), $T('accent_color', 'Accent color'), $R('place_id', 'Linked place (opens on click)', 'places', 'name_en', false),
        $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'sponsors' => ['title' => 'Sponsors', 'group' => 'Promotion', 'table' => 'sponsors', 'fields' => [
        $T('title', 'Title', true), $IMGS('image_urls', 'Banner pictures (Img 1 shows first)', null), $T('link_url', 'Link URL', true), $D('starts_on', 'Starts on'),
        $D('ends_on', 'Ends on'), $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'top-places' => ['title' => 'Top Places', 'group' => 'Promotion', 'table' => 'top_places', 'pk' => 'place_id', 'order' => 'sort_order', 'fields' => [
        ['createOnly' => true] + $R('place_id', 'Place', 'places', 'name_en'), $N('sort_order', 'Sort order', true)]],
      'notifications' => ['title' => 'Notifications', 'group' => 'Promotion', 'table' => 'notifications', 'desc' => true, 'fields' => [
        $S('type', 'Type', ['info', 'festival', 'access'], true), $T('title_en', 'Title (EN)', true), $T('title_kh', 'Title (KH)'), $A('body_en', 'Body (EN)'), $A('body_kh', 'Body (KH)'),
        $T('link', 'Link (e.g. #festivals)'), $R('user_id', 'Only for user (empty = everyone)', 'users', 'name', false)]],
      'guides' => ['title' => 'Guide applications', 'group' => 'People', 'table' => 'guide_applications', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('name', 'Name', true), $T('phone', 'Phone', true), $T('email', 'Email'), $T('telegram', 'Telegram'), $T('id_card_no', 'ID card no.'), $T('home_address', 'Address'), $T('provinces', 'Provinces'), $T('languages', 'Languages'), $N('daily_rate_usd', 'Daily rate USD'), $T('id_card_photo', 'ID card file'), $T('profile_photo', 'Profile photo'), $T('warranty_letter', 'Warranty letter'), $T('guarantor_name', 'Guarantor'), $T('guarantor_phone', 'Guarantor phone'), $A('admin_notes', 'Admin notes (sent to the user)'),
        $S('status', 'Status', ['pending', 'approved', 'rejected'], true)]],
      'transport' => ['title' => 'Transport applications', 'group' => 'People', 'table' => 'transport_applications', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('name', 'Name', true), $T('phone', 'Phone', true), $T('email', 'Email'), $T('id_card_no', 'ID card no.'), $T('home_address', 'Address'), $T('vehicle_type', 'Vehicle'), $T('vehicle_model', 'Model'), $T('license_plate', 'Plate'), $T('base_province', 'Base province'), $N('price_per_day', 'Price per day'), $T('id_card_photo', 'ID card file'), $T('vehicle_photo', 'Vehicle photo'), $T('warranty_letter', 'Warranty letter'), $T('guarantor_name', 'Guarantor'), $T('guarantor_phone', 'Guarantor phone'), $A('admin_notes', 'Admin notes (sent to the user)'),
        $S('status', 'Status', ['pending', 'approved', 'rejected'], true)]],
      'reservations' => ['title' => 'Hotel & restaurant bookings', 'group' => 'People', 'table' => 'hotel_restaurant_reservations', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('item_type', 'Type', true), $T('item_name', 'Item', true), $T('tourist_name', 'Guest', true), $T('phone', 'Phone'), $T('reserve_date', 'Date', true), $T('check_out_date', 'Check-out'),
        $N('guests', 'Guests'), $N('estimated_price_usd', 'Price after discount USD', true), $N('payment_amount_usd', 'Upfront deposit USD', true), $T('payment_proof', 'Receipt file'), $T('payment_reference', 'Bank ref'), $T('booking_details', 'Details'),
        $S('status', 'Status (completed = counts toward loyalty)', ['pending', 'confirmed', 'completed', 'cancelled'], true),
        $S('payment_status', 'Payment', ['not_due', 'unpaid', 'pending_review', 'paid', 'expired']), $T('room_number', 'Room no.'), $A('notes', 'Guest notes')]],
      'guide-list' => ['title' => 'Guides', 'group' => 'Partners', 'table' => 'guides', 'fields' => [
        $T('name', 'Name', true), $T('phone', 'Phone', true), $T('email', 'Email'), $T('telegram', 'Telegram'), $T('languages', 'Languages', true), $T('provinces', 'Provinces', true),
        $N('daily_rate_usd', 'Daily rate USD', true), $N('years_exp', 'Years of experience'), $T('profile_photo', 'Profile photo (path)'), $A('bio', 'Bio'),
        $S('status', 'Status', ['pending', 'approved', 'rejected', 'suspended'], true), $F('is_active', 'Active (shown on site)'), $N('sort_order', 'Sort order')]],
      'transport-list' => ['title' => 'Transport partners', 'group' => 'Partners', 'table' => 'transport_partners', 'fields' => [
        $T('name', 'Owner', true), $T('phone', 'Phone', true), $S('vehicle_type', 'Vehicle type', ['taxi', 'tuk_tuk', 'van', 'bus', 'other'], true), $T('vehicle_model', 'Model', true),
        $N('vehicle_capacity', 'Seats'), $T('license_plate', 'Plate'), $T('base_province', 'Base province', true), $N('price_per_day', 'Price per day USD', true), $T('vehicle_photo', 'Vehicle photo (path)'),
        $S('status', 'Status', ['pending', 'approved', 'rejected', 'suspended'], true), $F('is_active', 'Active (shown on site)'), $N('sort_order', 'Sort order')]],
      'guide-bookings' => ['title' => 'Guide bookings', 'group' => 'Partners', 'table' => 'guide_bookings', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('reference', 'Reference', true), $R('guide_id', 'Guide', 'guides', 'name'), $T('tourist_name', 'Tourist', true), $T('phone', 'Phone'), $T('trip_start', 'Start', true), $T('trip_end', 'End'),
        $N('num_people', 'People'), $N('total_usd', 'Total USD', true), $N('deposit_usd', 'Deposit USD'), $T('payment_proof', 'Receipt file'), $T('payment_reference', 'Bank ref'),
        $S('payment_status', 'Payment', ['pending', 'pending_review', 'deposit_paid', 'fully_paid', 'refunded', 'expired'], true),
        $S('booking_status', 'Booking (completed = counts toward loyalty)', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true), $A('admin_notes', 'Admin notes (sent to the user)'), $A('trip_plan', 'Trip plan')]],
      'transport-bookings' => ['title' => 'Transport bookings', 'group' => 'Partners', 'table' => 'transport_bookings', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('reference', 'Reference', true), $R('partner_id', 'Partner', 'transport_partners', 'name'), $T('tourist_name', 'Tourist', true), $T('phone', 'Phone'), $T('pickup_location', 'Pickup'),
        $T('dropoff_location', 'Drop-off'), $T('trip_date', 'Date', true), $T('trip_time', 'Time'), $N('num_passengers', 'Passengers'), $N('total_usd', 'Total USD', true), $N('deposit_usd', 'Deposit USD'),
        $T('payment_proof', 'Receipt file'), $T('payment_reference', 'Bank ref'), $S('payment_status', 'Payment', ['pending', 'pending_review', 'deposit_paid', 'fully_paid', 'refunded', 'expired'], true),
        $S('booking_status', 'Booking (completed = counts toward loyalty)', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true), $A('admin_notes', 'Admin notes (sent to the user)'), $A('notes', 'Tourist notes')]],
      'donations' => ['title' => 'Donations', 'group' => 'People', 'table' => 'donations', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('donor_name', 'Donor', true), $N('amount_usd', 'Amount USD', true), $A('message', 'Message'), $S('status', 'Status (completed = received)', ['pending', 'completed'], true)]],
      'menu-items' => ['title' => 'Restaurant menu', 'group' => 'Content', 'table' => 'restaurant_menu_items', 'fields' => [
        $R('restaurant_id', 'Restaurant', 'place_restaurants', 'restaurant_name_en'), $T('name_en', 'Dish (EN)', true), $T('name_kh', 'Dish (KH)', true), $T('category', 'Category', true),
        $N('price_usd', 'Price USD', true), $IMGS('image_url', 'Picture', null, true), $A('description_en', 'Description'), $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'shops' => ['title' => 'Province shops', 'group' => 'Content', 'table' => 'province_shops', 'fields' => [
        $PROV, $T('slug', 'Slug', true), $T('name_en', 'Name (EN)', true), $T('name_kh', 'Name (KH)', true), $T('shop_type', 'Type', true), $A('description_en', 'Description (EN)'), $A('description_kh', 'Description (KH)'),
        $T('merchandise_en', 'Products (EN)'), $T('merchandise_kh', 'Products (KH)'), $T('address_en', 'Address (EN)'), $T('address_kh', 'Address (KH)'), $T('opening_hours', 'Opening hours'),
        $T('price_level', 'Price level'), $IMGS(), $T('phone', 'Phone'), $T('website_url', 'Website'), $N('latitude', 'Latitude'), $N('longitude', 'Longitude'),
        $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'rewards' => ['title' => 'Prizes (coins)', 'group' => 'Settings', 'table' => 'point_rewards', 'fields' => [
        $T('name_en', 'Prize (EN)', true), $T('name_kh', 'Prize (KH)', true), $T('description_en', 'Description'), $N('cost_points', 'Cost in coins', true), $F('is_active', 'Active'), $N('sort_order', 'Sort order')]],
      'redemptions' => ['title' => 'Prize requests', 'group' => 'People', 'table' => 'point_redemptions', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('reward_name', 'Prize', true), $R('user_id', 'User', 'users', 'name'), $N('cost_points', 'Coins spent', true),
        $S('status', 'Status (cancelled = coins refunded)', ['pending', 'approved', 'completed', 'cancelled'], true), $A('admin_notes', 'Admin notes (sent to the user)')]],
      'messages' => ['title' => 'Messages', 'group' => 'People', 'table' => 'contact_messages', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('name', 'Name', true), $T('contact', 'Email / phone', true), ['list' => true] + $A('message', 'Message'), ['list' => true] + $F('is_read', 'Read', 0), ['createOnly' => true] + $T('created_at', 'Received', true)]],
      'views' => ['title' => 'Most viewed', 'group' => 'Promotion', 'table' => 'item_views', 'pk' => 'item_id', 'readonly' => true,
        'select' => "select v.item_type, v.item_id, v.views, coalesce(p.name_en, h.name, r.restaurant_name_en) as name from item_views v left join places p on v.item_type='place' and p.id=v.item_id left join hotels h on v.item_type='hotel' and h.id=v.item_id left join place_restaurants r on v.item_type='restaurant' and r.id=v.item_id order by v.views desc limit 200",
        'fields' => [$T('name', 'Name', true), $T('item_type', 'Type', true), $N('views', 'Views', true)]],
      'stats' => ['title' => 'Visitor stats', 'group' => 'Promotion', 'table' => 'site_stats', 'pk' => 'name', 'order' => 'name', 'readonly' => true, 'fields' => [$T('name', 'Event', true), $N('count', 'Count', true)]],
      'qr' => ['title' => 'Bank QR codes', 'group' => 'Settings', 'table' => 'app_settings', 'pk' => 'key', 'where' => "key like 'qr\\_%'", 'keyPrefix' => 'qr_', 'fields' => [
        ['createOnly' => true, 'req' => true] + $T('key', 'Name (must start with qr_, e.g. qr_aba)', true), ['list' => true] + $IMGS('value', 'QR picture', null, true)]],
      'room-prices' => ['title' => 'Room prices', 'group' => 'Settings', 'table' => 'room_prices', 'order' => 'sort_order', 'fields' => [
        $N('bed_count', 'Beds (1-4)', true), $S('class', 'Class', ['Fan', 'Air-con', 'Deluxe', 'Premium', 'VIP Suite', 'Family Room'], true), $N('price_usd', 'Price USD / night', true),
        $F('is_active', 'Active'), $N('sort_order', 'Sort order', true)]],
      'users' => ['title' => 'Users', 'group' => 'People', 'table' => 'users', 'noCreate' => true, 'desc' => true, 'fields' => [
        $T('name', 'Name', true), $T('email', 'Email', true), $T('phone', 'Phone'), $N('total_spent', 'Total spent (USD) → sets loyalty level', true), $N('points_balance', 'Coins (points)', true)]],
      'loyalty' => ['title' => 'Loyalty levels', 'group' => 'Settings', 'table' => 'loyalty_levels', 'pk' => 'level', 'fields' => [
        ['createOnly' => true] + $N('level', 'Level', true), $N('min_spent', 'Min spent (USD)', true), $N('discount_pct', 'Discount %', true)]],
      'settings' => ['title' => 'Site settings', 'group' => 'Settings', 'table' => 'app_settings', 'pk' => 'key', 'where' => "key not like 'qr\\_%'", 'fields' => [
        ['createOnly' => true] + $T('key', 'Key', true), $T('value', 'Value', true)]],
    ];
    $req = ['places' => ['province_id', 'name_en'], 'provinces' => ['slug', 'name_en'], 'categories' => ['slug', 'name_en'], 'hotels' => ['province_id', 'name'],
        'restaurants' => ['province_id', 'province_name', 'restaurant_name_en'], 'festivals' => ['name_en'], 'sponsors' => ['title'], 'top-places' => ['place_id'],
        'notifications' => ['title_en'], 'menu-items' => ['restaurant_id', 'name_en'], 'shops' => ['province_id', 'slug', 'name_en'], 'rewards' => ['name_en', 'cost_points'],
        'loyalty' => ['level', 'min_spent', 'discount_pct'], 'settings' => ['key'], 'guide-list' => ['name', 'phone'], 'transport-list' => ['name', 'phone', 'vehicle_type'],
        'room-prices' => ['bed_count', 'class', 'price_usd']];
    foreach ($req as $slug => $keys) foreach ($out[$slug]['fields'] as &$f) if (in_array($f['key'], $keys, true)) $f['req'] = true;
    unset($f);
    $badge = ['guides' => "status = 'pending'", 'transport' => "status = 'pending'", 'reservations' => "status = 'pending' or payment_status = 'pending_review'",
        'guide-bookings' => "booking_status = 'pending' or payment_status = 'pending_review'", 'transport-bookings' => "booking_status = 'pending' or payment_status = 'pending_review'",
        'redemptions' => "status = 'pending'", 'donations' => "status = 'pending'", 'messages' => 'is_read = 0'];
    foreach ($badge as $slug => $where) if (isset($out[$slug])) $out[$slug]['badge'] = $where;
    return $out;
}