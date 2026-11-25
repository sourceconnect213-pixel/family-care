<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('fcsess');
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const STATUSES = ['booked' => 'Booked', 'arrived' => 'Arrived', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'];

/* ---------- output helpers ---------- */

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $r) $cache[$r['skey']] = $r['svalue'];
    }
    return $cache[$key] ?? $default;
}

function money($amount): string
{
    return 'Rs. ' . number_format((float)$amount, 2);
}

function fmt_time(string $hhmm): string
{
    return date('g.i a', strtotime($hhmm));
}

function fmt_date(string $ymd, string $format = 'l, j F Y'): string
{
    return date($format, strtotime($ymd));
}

function initials(string $name): string
{
    $name = preg_replace('/^(Dr\.?|Prof\.?|Mr\.?|Mrs\.?|Ms\.?)\s+/i', '', trim($name));
    $parts = preg_split('/[\s.]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($parts, -2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out ?: 'DR';
}

function doctor_avatar(array $doc, string $class = ''): string
{
    if (!empty($doc['photo']) && is_file(__DIR__ . '/../' . UPLOAD_URL . '/' . $doc['photo'])) {
        return '<span class="shield ' . e($class) . '"><img src="' . e(base_url(UPLOAD_URL . '/' . $doc['photo'])) . '" alt="" loading="lazy"></span>';
    }
    return '<span class="shield shield--initials ' . e($class) . '" aria-hidden="true">' . e(initials($doc['name'])) . '</span>';
}

/** URL relative to the site root, works when the site lives in a sub-folder */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $root = realpath(__DIR__ . '/..');
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
        $depth = 0;
        if ($script && str_starts_with($script, $root)) {
            $rel = trim(substr(dirname($script), strlen($root)), DIRECTORY_SEPARATOR);
            $depth = $rel === '' ? 0 : count(explode(DIRECTORY_SEPARATOR, $rel));
        }
        $base = str_repeat('../', $depth);
    }
    return $base . ltrim($path, '/');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $html .= '<div class="alert alert--' . e($type) . '" role="status">' . e($msg) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- security ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/* ---------- auth ---------- */

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;
    static $admin = false;
    if ($admin === false) {
        $st = db()->prepare('SELECT id, username, name FROM admins WHERE id = ?');
        $st->execute([$_SESSION['admin_id']]);
        $admin = $st->fetch() ?: null;
    }
    return $admin;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) redirect('login.php');
    // idle timeout: 2 hours
    if (time() - ($_SESSION['admin_seen'] ?? 0) > 7200) {
        unset($_SESSION['admin_id']);
        flash('info', 'You were signed out after 2 hours of inactivity.');
        redirect('login.php');
    }
    $_SESSION['admin_seen'] = time();
    return $a;
}

function admin_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

/* ---------- data ---------- */

function specialties(): array
{
    return db()->query('SELECT * FROM specialties ORDER BY sort_order, name')->fetchAll();
}

function get_doctor(int $id, bool $activeOnly = true): ?array
{
    $sql = 'SELECT d.*, s.name AS specialty FROM doctors d LEFT JOIN specialties s ON s.id = d.specialty_id WHERE d.id = ?';
    if ($activeOnly) $sql .= ' AND d.active = 1';
    $st = db()->prepare($sql);
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function doctors(?int $specialtyId = null, string $q = '', bool $activeOnly = true): array
{
    $sql = 'SELECT d.*, s.name AS specialty FROM doctors d LEFT JOIN specialties s ON s.id = d.specialty_id WHERE 1=1';
    $args = [];
    if ($activeOnly) $sql .= ' AND d.active = 1';
    if ($specialtyId) { $sql .= ' AND d.specialty_id = ?'; $args[] = $specialtyId; }
    if ($q !== '') { $sql .= ' AND (d.name LIKE ? OR s.name LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
    $sql .= ' ORDER BY s.sort_order, d.name';
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function doctor_schedules(int $doctorId, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM schedules WHERE doctor_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY weekday, start_time';
    $st = db()->prepare($sql);
    $st->execute([$doctorId]);
    return $st->fetchAll();
}

function get_schedule(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM schedules WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function weekday_of(string $ymd): int
{
    return (int)date('w', strtotime($ymd));
}

function is_on_leave(int $doctorId, int $scheduleId, string $date): ?array
{
    $st = db()->prepare('SELECT * FROM leaves WHERE doctor_id = ? AND leave_date = ? AND (schedule_id IS NULL OR schedule_id = ?) LIMIT 1');
    $st->execute([$doctorId, $date, $scheduleId]);
    return $st->fetch() ?: null;
}

function booked_count(int $scheduleId, string $date): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND appt_date = ? AND status <> 'cancelled'");
    $st->execute([$scheduleId, $date]);
    return (int)$st->fetchColumn();
}

/**
 * State of one session on one date.
 * Returns ['state' => open|full|closed|leave|invalid, 'booked' => n, 'next' => n|null, 'leave' => reason]
 */
function session_state(array $sch, string $date): array
{
    $out = ['state' => 'open', 'booked' => 0, 'next' => null, 'leave' => null];
    if (!$sch['active'] || weekday_of($date) !== (int)$sch['weekday']) return ['state' => 'invalid'] + $out;

    $leave = is_on_leave((int)$sch['doctor_id'], (int)$sch['id'], $date);
    if ($leave) return ['state' => 'leave', 'leave' => $leave['reason'] ?: 'Doctor not available'] + $out;

    $out['booked'] = booked_count((int)$sch['id'], $date);
    $cutoff = strtotime("$date {$sch['start_time']}") - 60 * (int)setting('close_before_min', '30');
    $lastDay = strtotime('today +' . (int)setting('booking_days_ahead', '30') . ' days');

    if (time() > $cutoff || strtotime($date) > $lastDay) {
        $out['state'] = 'closed';
    } elseif ($out['booked'] >= (int)$sch['max_patients']) {
        $out['state'] = 'full';
    } else {
        $out['next'] = next_number((int)$sch['id'], $date);
    }
    return $out;
}

function next_number(int $scheduleId, string $date): int
{
    $st = db()->prepare('SELECT COALESCE(MAX(number), 0) + 1 FROM appointments WHERE schedule_id = ? AND appt_date = ?');
    $st->execute([$scheduleId, $date]);
    return (int)$st->fetchColumn();
}

function estimated_time(array $sch, int $number): string
{
    $t = strtotime('2000-01-01 ' . $sch['start_time']) + ($number - 1) * 60 * (int)$sch['minutes_per_patient'];
    return date('H:i', $t);
}

/** Every bookable session for a doctor in the booking window, grouped by date */
function upcoming_sessions(int $doctorId): array
{
    $schedules = doctor_schedules($doctorId);
    $days = (int)setting('booking_days_ahead', '30');
    $out = [];
    for ($i = 0; $i <= $days; $i++) {
        $date = date('Y-m-d', strtotime("today +$i days"));
        $w = weekday_of($date);
        foreach ($schedules as $s) {
            if ((int)$s['weekday'] !== $w) continue;
            $state = session_state($s, $date);
            if ($state['state'] === 'closed' && $i === 0) continue; // already started today
            $out[$date][] = ['schedule' => $s, 'date' => $date] + $state;
        }
    }
    return $out;
}

/** All sessions running on a given date (for the "today" board and admin dashboard) */
function sessions_on(string $date): array
{
    $st = db()->prepare('SELECT sc.*, d.name AS doctor_name, d.photo, sp.name AS specialty
        FROM schedules sc JOIN doctors d ON d.id = sc.doctor_id LEFT JOIN specialties sp ON sp.id = d.specialty_id
        WHERE sc.active = 1 AND d.active = 1 AND sc.weekday = ? ORDER BY sc.start_time, d.name');
    $st->execute([weekday_of($date)]);
    $rows = [];
    foreach ($st->fetchAll() as $s) {
        $rows[] = ['schedule' => $s, 'date' => $date] + session_state($s, $date);
    }
    return $rows;
}

function generate_ref(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $ref = 'FC';
        for ($i = 0; $i < 6; $i++) $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $st = db()->prepare('SELECT 1 FROM appointments WHERE ref = ?');
        $st->execute([$ref]);
    } while ($st->fetchColumn());
    return $ref;
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '94') && strlen($digits) === 11) $digits = '0' . substr($digits, 2);
    return $digits;
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^0\d{9}$/', $phone);
}

function valid_nic(string $nic): bool
{
    return $nic === '' || (bool)preg_match('/^(\d{9}[VvXx]|\d{12})$/', $nic);
}

/**
 * Book an appointment. Locks the session so two people can never get the same number.
 * @return array ['ok' => bool, 'error' => string, 'appointment' => array]
 */
function book_appointment(int $scheduleId, string $date, array $patient, string $source = 'online'): array
{
    $pdo = db();
    $sch = get_schedule($scheduleId);
    if (!$sch || !get_doctor((int)$sch['doctor_id'])) return ['ok' => false, 'error' => 'That session no longer exists. Pick another session.'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return ['ok' => false, 'error' => 'Pick a valid date.'];

    try {
        if (db_is_sqlite()) $pdo->exec('BEGIN IMMEDIATE'); else $pdo->beginTransaction();
        if (!db_is_sqlite()) {
            // serialise bookings for this session
            $pdo->prepare('SELECT id FROM schedules WHERE id = ? FOR UPDATE')->execute([$scheduleId]);
        }

        $state = session_state($sch, $date);
        if ($source === 'admin' && $state['state'] === 'closed' && strtotime($date) >= strtotime('today')) {
            // front desk may add walk-ins on the day, even after online booking closes
            $state['state'] = $state['booked'] >= (int)$sch['max_patients'] ? 'full' : 'open';
        }
        if ($state['state'] !== 'open') {
            $pdo->exec(db_is_sqlite() ? 'ROLLBACK' : 'ROLLBACK');
            $msg = [
                'full'    => 'This session just filled up. Pick another session.',
                'closed'  => 'Booking for this session has closed. Pick another session.',
                'leave'   => 'The doctor is not available for this session. Pick another session.',
                'invalid' => 'The doctor does not hold this session on that date.',
            ][$state['state']] ?? 'This session can\'t be booked.';
            return ['ok' => false, 'error' => $msg];
        }

        if ($source === 'online') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND appt_date = ? AND phone = ? AND status <> 'cancelled'");
            $st->execute([$scheduleId, $date, $patient['phone']]);
            if ((int)$st->fetchColumn() >= 3) {
                $pdo->exec('ROLLBACK');
                return ['ok' => false, 'error' => 'This phone number already has 3 bookings for this session. Call the hospital to book more.'];
            }
            $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE ip = ? AND created_at > ?");
            $st->execute([client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
            if ((int)$st->fetchColumn() >= 10) {
                $pdo->exec('ROLLBACK');
                return ['ok' => false, 'error' => 'Too many bookings from this device in the last hour. Call the hospital to book.'];
            }
        }

        $number = next_number($scheduleId, $date);
        $ref = generate_ref();
        $st = $pdo->prepare('INSERT INTO appointments (ref, doctor_id, schedule_id, appt_date, number, patient_name, phone, nic, age, gender, notes, status, source, ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $ref, $sch['doctor_id'], $scheduleId, $date, $number,
            $patient['name'], $patient['phone'], $patient['nic'] ?: null,
            $patient['age'] !== '' ? (int)$patient['age'] : null, $patient['gender'] ?: null,
            $patient['notes'] ?: null, 'booked', $source, client_ip(), date('Y-m-d H:i:s'),
        ]);
        $pdo->exec('COMMIT');
        $appt = get_appointment_by_ref($ref);
        after_response(fn() => notify_booking($appt));
        return ['ok' => true, 'appointment' => $appt];
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored) {}
        error_log('Booking failed: ' . $ex->getMessage());
        return ['ok' => false, 'error' => 'We couldn\'t save the booking. Try again, or call the hospital.'];
    }
}

function get_appointment_by_ref(string $ref): ?array
{
    $st = db()->prepare('SELECT a.*, d.name AS doctor_name, d.fee, sp.name AS specialty, sc.start_time, sc.end_time, sc.minutes_per_patient, sc.room
        FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN schedules sc ON sc.id = a.schedule_id
        LEFT JOIN specialties sp ON sp.id = d.specialty_id WHERE a.ref = ?');
    $st->execute([strtoupper(trim($ref))]);
    return $st->fetch() ?: null;
}

function validate_patient(array $in): array
{
    $p = [
        'name'   => trim(mb_substr((string)($in['patient_name'] ?? ''), 0, 120)),
        'phone'  => normalize_phone((string)($in['phone'] ?? '')),
        'nic'    => strtoupper(trim((string)($in['nic'] ?? ''))),
        'age'    => trim((string)($in['age'] ?? '')),
        'gender' => in_array($in['gender'] ?? '', ['male', 'female', 'other'], true) ? $in['gender'] : '',
        'notes'  => trim(mb_substr((string)($in['notes'] ?? ''), 0, 500)),
    ];
    $errors = [];
    if (mb_strlen($p['name']) < 2) $errors['patient_name'] = 'Enter the patient\'s name.';
    if (!valid_phone($p['phone'])) $errors['phone'] = 'Enter a 10-digit mobile number, for example 0771234567.';
    if (!valid_nic($p['nic'])) $errors['nic'] = 'Enter an NIC like 199012345678 or 901234567V, or leave it empty.';
    if ($p['age'] !== '' && (!ctype_digit($p['age']) || (int)$p['age'] > 120)) $errors['age'] = 'Enter age in years (0–120).';
    return [$p, $errors];
}

/** Run work (like sending SMS) after the page has been sent to the browser */
function after_response(callable $fn): void
{
    register_shutdown_function(function () use ($fn) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try { $fn(); } catch (Throwable $e) { error_log('Background task failed: ' . $e->getMessage()); }
    });
}

/** Cancel an appointment and tell the patient and the hospital */
function cancel_appointment(array $a, string $by): void
{
    db()->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?")->execute([$a['id']]);
    after_response(fn() => notify_cancellation($a, $by));
}

require_once __DIR__ . '/sms.php';

/* ---------- doctor login ---------- */

function current_doctor(): ?array
{
    if (empty($_SESSION['doctor_id'])) return null;
    $doc = get_doctor((int)$_SESSION['doctor_id'], false);
    return $doc && $doc['login_enabled'] ? $doc : null;
}

function require_doctor(): array
{
    $d = current_doctor();
    if (!$d || time() - ($_SESSION['doctor_seen'] ?? 0) > 7200) {
        unset($_SESSION['doctor_id']);
        if ($d) flash('info', 'You were signed out after 2 hours of inactivity.');
        redirect('login.php');
    }
    $_SESSION['doctor_seen'] = time();
    return $d;
}

/** 5 failed sign-ins per IP per 15 minutes, shared by staff and doctor logins */
function login_blocked(): bool
{
    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - 900]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([client_ip()]);
    return (int)$st->fetchColumn() >= 5;
}

function login_failed(): void
{
    db()->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([client_ip(), time()]);
    usleep(400000);
}

function login_succeeded(): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
    session_regenerate_id(true);
}

/** Full address of the doctor portal, for telling doctors where to sign in */
function portal_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'your-website';
    $root = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php'))), '/');
    return ($https ? 'https://' : 'http://') . $host . $root . '/portal/';
}

/* ---------- website content ---------- */

const SITE_UPLOAD_DIR = __DIR__ . '/../uploads/site';
const SITE_UPLOAD_URL = 'uploads/site';

/**
 * Save an uploaded photo (JPG, PNG or WebP, up to 5 MB) under uploads/site.
 * @return array [filename|null, error|null]
 */
function save_site_image(array $f, ?string $replace = null): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [$replace, null];
    $info = $f['error'] === UPLOAD_ERR_OK ? @getimagesize($f['tmp_name']) : false;
    $ext = $info ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
    if (!$ext) return [$replace, 'Photo must be a JPG, PNG or WebP image.'];
    if ($f['size'] > 5 * 1024 * 1024) return [$replace, 'Photo must be smaller than 5 MB.'];
    if (!is_dir(SITE_UPLOAD_DIR)) mkdir(SITE_UPLOAD_DIR, 0775, true);
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], SITE_UPLOAD_DIR . '/' . $name)) return [$replace, 'Couldn\'t save the photo. Check that the uploads folder is writable.'];
    if ($replace) delete_site_image($replace);
    return [$name, null];
}

function delete_site_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{20}\.(jpg|png|webp)$/', $name)) @unlink(SITE_UPLOAD_DIR . '/' . $name);
}

function site_image_url(?string $name): ?string
{
    return $name && is_file(SITE_UPLOAD_DIR . '/' . $name) ? base_url(SITE_UPLOAD_URL . '/' . $name) : null;
}

function pages(bool $menuOnly = false): array
{
    return db()->query('SELECT * FROM pages WHERE published = 1' . ($menuOnly ? ' AND in_top_menu = 1' : '') . ' ORDER BY sort_order, title')->fetchAll();
}

function get_page(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM pages WHERE slug = ? AND published = 1');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function services(): array
{
    return db()->query('SELECT * FROM services ORDER BY sort_order, name')->fetchAll();
}

function posts(int $limit = 0): array
{
    $sql = "SELECT * FROM posts WHERE published = 1 AND published_on <= '" . date('Y-m-d') . "' ORDER BY published_on DESC, id DESC";
    if ($limit) $sql .= ' LIMIT ' . (int)$limit;
    return db()->query($sql)->fetchAll();
}

/** YouTube video id from a normal, short or embed link */
function youtube_id(?string $url): ?string
{
    return $url && preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
}

/** Text with blank-line paragraphs → safe HTML paragraphs */
function paragraphs(?string $text): string
{
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim((string)$text)) as $p) if ($p !== '') $out .= '<p>' . nl2br(e(trim($p))) . "</p>\n";
    return $out;
}

/** Group names A–Z: ['A' => [...], 'B' => [...]] */
function group_by_letter(array $items, string $key = 'name'): array
{
    $g = [];
    foreach ($items as $it) $g[mb_strtoupper(mb_substr($it[$key], 0, 1))][] = $it;
    ksort($g);
    return $g;
}

/** A home page number; {doctors} and {specialties} count themselves */
function stat_value(string $n): string
{
    return strtr(setting("stat{$n}_value"), [
        '{doctors}'     => (string)count(doctors()),
        '{specialties}' => (string)count(specialties()),
    ]);
}

function tel(string $phone): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}
