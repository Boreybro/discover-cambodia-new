<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https']);
session_start();

/* ---- env ---- */
foreach (@file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
    if ($l[0] === '#' || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\"'");
}
function env(string $k, string $d = ''): string { return (string)($_ENV[$k] ?? (getenv($k) ?: $d)); }

/* ---- database (PDO + Postgres) ---- */
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', env('DB_HOST', 'localhost'), env('DB_PORT', '5432'), env('DB_NAME', 'postgres'), env('DB_SSLMODE', 'prefer'));
        $pdo = new PDO($dsn, env('DB_USER', 'postgres'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true, // works with Supabase's pooler too
        ]);
        $pdo->exec("SET client_encoding = 'UTF8'");
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { return q($sql, $p)->fetch() ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

/* ---- helpers ---- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base(): string {
    static $b;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if (str_ends_with($b, '/admin')) $b = substr($b, 0, -6);
    }
    return $b;
}
function u(string $p = ''): string { return base() . '/' . ltrim($p, '/'); }
function img(?string $s): string { if (!$s) return ''; if (str_starts_with($s, 'private:')) return u('media.php?f=' . urlencode(substr($s, 8))); return (preg_match('~^(https?:)?//~', $s) || $s[0] === '/') ? $s : u($s); }
function images_of(array $r): array {
    $s = ($r['images'] ?? '') ?: ($r['image_url'] ?? '') ?: ($r['cover_image'] ?? '');
    return array_values(array_filter(array_map('trim', explode('|', (string)$s))));
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
function json_out($d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!hash_equals(csrf(), (string)$t)) { http_response_code(419); exit('Invalid or expired form. Go back and retry.'); }
}
function verify_pw(string $plain, string $hash): bool {
    return str_starts_with($hash, '$') ? password_verify($plain, $hash) : hash_equals($hash, $plain); // plain = legacy rows
}

/* ---- language ---- */
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'kh'], true)) {
    setcookie('lang', $_GET['lang'], time() + 31536000, '/');
    $_COOKIE['lang'] = $_GET['lang'];
}
function lang(): string { return ($_COOKIE['lang'] ?? 'en') === 'kh' ? 'kh' : 'en'; }
function i18n_all(): array {
    static $a;
    if (!$a) {
        $a = require ROOT . '/src/i18n.php'; $x = require ROOT . '/src/i18n_extra.php';
        $a['en'] += $x['en']; $a['kh'] = $x['kh'] + $a['kh'];      // extra Khmer wins over the old one
        $a['phrases'] = $x['phrases']; $a['rules'] = $x['rules'];
    }
    return $a;
}
function dict(): array { $a = i18n_all(); return lang() === 'kh' ? array_merge($a['en'], $a['kh']) : $a['en']; }
function t(string $k): string { return dict()[$k] ?? $k; }
function pick(array $r, string $f = 'name'): string {
    $kh = (string)($r[$f . '_kh'] ?? '');
    return (lang() === 'kh' && $kh !== '') ? $kh : (string)($r[$f . '_en'] ?? $r[$f] ?? '');
}

/* ---- users ---- */
function user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = empty($_SESSION['uid']) ? null
            : row('select id,name,email,phone,bio,avatar,preferred_lang,total_spent,points_balance from users where id=?', [$_SESSION['uid']]);
    }
    return $u;
}
function require_user(): array { return user() ?? redirect(u('login.php?next=' . urlencode($_SERVER['REQUEST_URI']))); }

/* ---- loyalty: spend -> discount level (table loyalty_levels) ---- */
function loyalty(float $spent): array {
    $cur = ['level' => 0, 'min_spent' => 0, 'discount_pct' => 0]; $next = null;
    foreach (rows('select level,min_spent,discount_pct from loyalty_levels order by level') as $l) {
        if ($spent >= (float)$l['min_spent']) $cur = $l; elseif (!$next) $next = $l;
    }
    return ['level' => (int)$cur['level'], 'pct' => (float)$cur['discount_pct'], 'min' => (float)$cur['min_spent'], 'next' => $next];
}
/** Use this in hotel/restaurant booking code: price after the user's level discount. */
function apply_discount(float $amount, float $spent): float { return round($amount * (1 - loyalty($spent)['pct'] / 100), 2); }

/* ---- security headers, optional HTTPS redirect (FORCE_HTTPS=1), login rate limit ---- */
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (env('FORCE_HTTPS') === '1' && empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
        redirect('https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'));
    }
}
function client_ip(): string {   // behind Railway's proxy set TRUST_PROXY=1 to use the real visitor IP
    if (env('TRUST_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $p = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])); return (string)end($p); }
    return $_SERVER['REMOTE_ADDR'] ?? '0';
}
function too_many_fails(string $scope, string $ident, int $limit = 5, int $mins = 15): bool {
    return (int)val("select count(*) from login_attempts where scope=? and ident=? and created_at > now() - (? || ' minutes')::interval", [$scope, $ident, $mins]) >= $limit;
}
function note_fail(string $scope, string $ident): void { q('insert into login_attempts (scope,ident) values (?,?)', [$scope, $ident]); }
function clear_fails(string $scope, string $ident): void { q('delete from login_attempts where scope=? and ident=?', [$scope, $ident]); }

/* ---- view counters + visitor stats ---- */
function fmt_views($n): string {
    $n = (int)$n;
    if ($n >= 1000000) return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
    if ($n >= 1000) return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    return (string)$n;
}
function is_bot(): bool { return (bool)preg_match('/bot|crawl|spider|slurp|preview|headless/i', $_SERVER['HTTP_USER_AGENT'] ?? ''); }
/** +1 view for a place / hotel / restaurant. One visitor counts once per item every 30 minutes. Returns the total. */
function bump_view(string $type, int $id): int {
    if (!in_array($type, ['place', 'hotel', 'restaurant'], true) || $id < 1) return 0;
    $key = "$type:$id"; $now = time();
    if (!is_bot() && ($_SESSION['seen'][$key] ?? 0) < $now - 1800) {
        q('insert into item_views (item_type,item_id,views) values (?,?,1) on conflict (item_type,item_id) do update set views = item_views.views + 1', [$type, $id]);
        $_SESSION['seen'][$key] = $now;
        if (count($_SESSION['seen']) > 300) $_SESSION['seen'] = array_slice($_SESSION['seen'], -150, null, true);
    }
    return (int)val('select views from item_views where item_type=? and item_id=?', [$type, $id]);
}
function stat_bump(string $name): void { q('insert into site_stats (name,count) values (?,1) on conflict (name) do update set count = site_stats.count + 1', [$name]); }
