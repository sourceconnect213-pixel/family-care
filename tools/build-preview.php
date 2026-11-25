<?php
/**
 * Builds a read-only preview of the website (public pages, admin panel and doctor portal)
 * as plain HTML files, for GitHub Pages or any static host.
 *
 *   php tools/build-preview.php [output-dir]
 *
 * It runs the real PHP site on a temporary database filled with sample data, saves every
 * page, and rewrites links so they work without PHP. Forms don't save anything in the preview.
 */

$root = realpath(__DIR__ . '/..');
$out = rtrim($argv[1] ?? $root . '/_site', '/');
$tmp = sys_get_temp_dir() . '/fc-preview-' . getmypid();
@mkdir($tmp, 0777, true);
$dbPath = "$tmp/preview.sqlite";
putenv("FC_DB_PATH=$dbPath");
date_default_timezone_set('Asia/Colombo');

/* ---------- 1. sample data ---------- */
require $root . '/includes/functions.php';
$pdo = db();
$pdo->prepare('INSERT INTO admins (username, name, password_hash, created_at) VALUES (?,?,?,?)')
    ->execute(['admin', 'Front desk', password_hash('preview-only', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
$pdo->prepare("UPDATE doctors SET username = 'dr.b', login_enabled = 1, password_hash = ? WHERE id = 2")
    ->execute([password_hash('preview-only', PASSWORD_DEFAULT)]);
$pdo->exec("UPDATE settings SET svalue = 'FamilyCare' WHERE skey = 'sms_sender_id'");
$pdo->exec("UPDATE settings SET svalue = '0770000000' WHERE skey = 'official_number'");

$names = ['Nimali Perera', 'Kasun Fernando', 'Dilani Jayasinghe', 'Ruwan Silva', 'Ishara Bandara', 'Chamara Wijesinghe',
          'Sanduni Rathnayake', 'Tharindu Gunawardena', 'Malini de Alwis', 'Pradeep Herath', 'Ayesha Nazeer', 'Kavindu Senanayake'];
$notes = ['', '', 'Fever for 3 days', '', 'Follow-up visit, bringing reports', '', 'Child, 6 years', ''];
$i = 0;
foreach (doctors() as $doc) {
    foreach (array_slice(upcoming_sessions((int)$doc['id']), 0, 3, true) as $date => $sessions) {
        foreach ($sessions as $r) {
            if ($r['state'] !== 'open') continue;
            $n = 2 + ($i % 6);
            for ($k = 0; $k < $n; $k++, $i++) {
                book_appointment((int)$r['schedule']['id'], $date, [
                    'name' => $names[$i % count($names)], 'phone' => '07' . str_pad((string)(10000000 + $i * 7919 % 89999999), 8, '0'),
                    'nic' => '', 'age' => (string)(5 + $i * 7 % 70), 'gender' => ['female', 'male'][$i % 2], 'notes' => $notes[$i % count($notes)],
                ], $i % 3 ? 'online' : 'admin');
            }
        }
    }
}
// A few already seen / finished so the screens show different states
$pdo->exec("UPDATE appointments SET seen_by_doctor = 1 WHERE id % 3 = 0");
$pdo->exec("UPDATE appointments SET status = 'arrived' WHERE id % 7 = 0");
$pdo->exec("UPDATE appointments SET status = 'cancelled' WHERE id % 11 = 0");
// Sample SMS log (nothing is really sent)
foreach ($pdo->query('SELECT ref FROM appointments ORDER BY id DESC LIMIT 4')->fetchAll(PDO::FETCH_COLUMN) as $ref) {
    $a = get_appointment_by_ref($ref);
    sms_log((int)$a['id'], sms_number($a['phone']), 'patient_booked', "Appointment confirmed. {$a['doctor_name']}, No. {$a['number']}. Ref {$a['ref']}", 'sent', 'Preview sample');
    sms_log((int)$a['id'], '94770000000', 'official_booked', "New channelling: {$a['doctor_name']} - No. {$a['number']}, {$a['patient_name']}. Ref {$a['ref']}", 'sent', 'Preview sample');
}
// SMS stays switched off while building, so no real message can ever be sent

/* ---------- 2. run the real site ---------- */
$port = 18080 + getmypid() % 1000;
$server = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $root], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, getenv() + ['FC_DB_PATH' => $dbPath]);
usleep(800000);
$base = "http://127.0.0.1:$port/";

function fetch(string $url, ?string $jar = null, array $post = null): string
{
    global $base;
    $ch = curl_init($base . $url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
    if ($jar) curl_setopt_array($ch, [CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    $html = (string)curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) fwrite(STDERR, "Warning: $url returned " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n");
    curl_close($ch);
    return $html;
}
function csrf_from(string $html): string
{
    return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}

/* ---------- 3. URL mapping: PHP address → static file ---------- */
function map_url(string $path): ?string
{
    $q = [];
    [$file, $query] = array_pad(explode('?', $path, 2), 2, '');
    [$query, $hash] = array_pad(explode('#', $query, 2), 2, '');
    if (str_contains($file, '#')) [$file, $hash] = explode('#', $file, 2);
    parse_str(html_entity_decode($query), $q);
    $h = $hash !== '' ? "#$hash" : '';
    $spec = (int)($q['specialty'] ?? 0);
    $map = [
        ''                       => 'index.html',
        'index.php'              => 'index.html',
        'doctors.php'            => $spec ? "doctors-$spec.html" : 'doctors.html',
        'doctor.php'             => 'doctor-' . (int)($q['id'] ?? 0) . '.html',
        'timetable.php'          => $spec ? "timetable-$spec.html" : 'timetable.html',
        'book.php'               => 'book-preview.html',
        'booking.php'            => 'booking-preview.html',
        'check.php'              => 'check.html',
        'admin/'                 => 'admin/index.html',
        'admin/index.php'        => 'admin/index.html',
        'admin/login.php'        => 'admin/login.html',
        'admin/setup.php'        => 'admin/login.html',
        'admin/logout.php'       => 'admin/login.html',
        'admin/appointments.php' => 'admin/appointments.html',
        'admin/print.php'        => 'admin/print.html',
        'admin/doctors.php'      => 'admin/doctors.html',
        'admin/doctor_edit.php'  => isset($q['id']) ? 'admin/doctor-' . (int)$q['id'] . '.html' : 'admin/doctor-new.html',
        'admin/leaves.php'       => 'admin/leaves.html',
        'admin/specialties.php'  => 'admin/specialties.html',
        'admin/settings.php'     => 'admin/settings.html',
        'admin/sms.php'          => 'admin/sms.html',
        'admin/account.php'      => 'admin/account.html',
        'portal/'                => 'portal/index.html',
        'portal/index.php'       => 'portal/index.html',
        'portal/login.php'       => 'portal/login.html',
        'portal/logout.php'      => 'portal/login.html',
        'portal/timetable.php'   => 'portal/timetable.html',
        'portal/password.php'    => 'portal/password.html',
    ];
    return isset($map[$file]) ? $map[$file] . $h : null;
}

/** Turn a link found on page $pageDir ('' or 'admin' or 'portal') into a root path */
function resolve(string $href, string $pageDir): string
{
    $parts = $pageDir === '' ? [] : [$pageDir];
    if ($href === '' || $href[0] === '?') return implode('/', $parts) . '/' . ($pageDir === '' ? 'index.php' : 'index.php') . $href;
    foreach (explode('/', $href) as $seg) {
        if ($seg === '..') array_pop($parts);
        elseif ($seg !== '.') $parts[] = $seg;
    }
    return implode('/', $parts);
}

function relative(string $target, string $pageDir): string
{
    return ($pageDir === '' ? '' : '../') . $target;
}

$banner = <<<HTML
<div class="fc-preview" role="note">
  <span><strong>Preview</strong> with sample data. Nothing you type is saved. Booking, the admin panel and doctor logins work on the live site.</span>
  <span><a href="{ROOT}index.html">Website</a> <a href="{ROOT}admin/index.html">Admin panel</a> <a href="{ROOT}portal/index.html">Doctor portal</a></span>
</div>
<style>
.fc-preview{position:fixed;left:12px;right:12px;bottom:12px;z-index:9999;background:#06164A;color:#fff;font:15px/1.4 "Source Sans 3",system-ui,sans-serif;padding:10px 14px;border-radius:8px;border-left:5px solid #E30613;display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;justify-content:space-between;box-shadow:0 10px 30px rgba(0,0,0,.25)}
.fc-preview a{color:#fff;margin-left:12px} body{padding-bottom:84px} body.admin .side{padding-bottom:84px}
@media print{.fc-preview{display:none}}
</style>
<script>
document.addEventListener('submit', function (e) {
  var f = e.target, root = '{ROOT}';
  e.preventDefault();
  var spec = f.querySelector('select[name=specialty]');
  if (spec && f.method.toLowerCase() === 'get') {
    var page = /timetable/.test(location.pathname) ? 'timetable' : 'doctors';
    location.href = root + page + (spec.value ? '-' + spec.value : '') + '.html'; return;
  }
  if (f.querySelector('input[name=password]') && !f.querySelector('input[name=current]') && /\/admin\//.test(location.pathname)) { location.href = root + 'admin/index.html'; return; }
  if (f.querySelector('input[name=password]') && !f.querySelector('input[name=current]') && /\/portal\//.test(location.pathname)) { location.href = root + 'portal/index.html'; return; }
  if (f.querySelector('input[name=schedule]') && f.querySelector('input[name=patient_name]') && !/\/admin\//.test(location.pathname)) { location.href = root + 'booking-preview.html'; return; }
  alert('This is a preview, so nothing is saved. On the live site this button works.');
}, true);
window.fcPreviewChange = function (el) { var f = el.form; if (f.requestSubmit) f.requestSubmit(); else f.dispatchEvent(new Event('submit', {cancelable: true})); };
</script>
HTML;

function save_page(string $html, string $target): void
{
    global $out, $banner;
    $dir = dirname($target) === '.' ? '' : dirname($target);
    $html = preg_replace_callback('/\b(href|action)="([^"]*)"/', function ($m) use ($dir) {
        $url = html_entity_decode($m[2]);
        if (preg_match('#^(https?:|mailto:|tel:|\#|data:)#', $url) || str_contains($url, 'assets/') || str_contains($url, 'uploads/')) return $m[0];
        $mapped = map_url(resolve($url, $dir));
        if ($mapped === null) return $m[0];
        return $m[1] . '="' . htmlspecialchars(relative($mapped, $dir), ENT_QUOTES) . '"';
    }, $html);
    $html = str_replace('onchange="this.form.submit()"', 'onchange="fcPreviewChange(this)"', $html);
    $html = preg_replace('/<form(?![^>]*novalidate)/', '<form novalidate', $html); // let the preview handle every button
    $html = preg_replace('#<meta name="robots"[^>]*>#', '', $html);
    $html = str_replace('</head>', '<meta name="robots" content="noindex, nofollow">' . "\n</head>", $html);
    $html = preg_replace('#<body([^>]*)>#', '<body$1>' . str_replace('{ROOT}', $dir === '' ? '' : '../', $banner), $html, 1);
    @mkdir(dirname("$out/$target"), 0777, true);
    file_put_contents("$out/$target", $html);
}

/* ---------- 4. crawl ---------- */
@mkdir($out, 0777, true);
$docIds = array_map(fn($d) => (int)$d['id'], doctors());
$specIds = array_map(fn($s) => (int)$s['id'], specialties());

foreach (['index.php', 'doctors.php', 'timetable.php', 'check.php'] as $p) save_page(fetch($p), map_url($p));
foreach ($specIds as $s) {
    save_page(fetch("doctors.php?specialty=$s"), "doctors-$s.html");
    save_page(fetch("timetable.php?specialty=$s"), "timetable-$s.html");
}
foreach ($docIds as $id) save_page(fetch("doctor.php?id=$id"), "doctor-$id.html");

// Sample booking page + confirmation (doctor B)
$patientJar = "$tmp/patient.txt";
$doctorPage = fetch('doctor.php?id=2');
if (preg_match('/book\.php\?schedule=(\d+)&amp;date=([\d-]+)/', $doctorPage, $m)) {
    $bookHtml = fetch("book.php?schedule={$m[1]}&date={$m[2]}", $patientJar);
    save_page($bookHtml, 'book-preview.html');
    fetch('book.php', $patientJar, ['csrf' => csrf_from($bookHtml), 'schedule' => $m[1], 'date' => $m[2],
        'patient_name' => 'Nimali Perera', 'phone' => '0712345678', 'age' => '34', 'gender' => 'female', 'agree' => '1']);
    $ref = $pdo->query("SELECT ref FROM appointments ORDER BY id DESC LIMIT 1")->fetchColumn();
    save_page(fetch("booking.php?ref=$ref&new=1", $patientJar), 'booking-preview.html');
}

// Admin panel
$adminJar = "$tmp/admin.txt";
$login = fetch('admin/login.php', $adminJar);
save_page($login, 'admin/login.html');
fetch('admin/login.php', $adminJar, ['csrf' => csrf_from($login), 'username' => 'admin', 'password' => 'preview-only']);
foreach (['index', 'appointments', 'doctors', 'leaves', 'specialties', 'settings', 'account'] as $p) save_page(fetch("admin/$p.php", $adminJar), "admin/$p.html");
foreach ($docIds as $id) save_page(fetch("admin/doctor_edit.php?id=$id", $adminJar), "admin/doctor-$id.html");
save_page(fetch('admin/doctor_edit.php', $adminJar), 'admin/doctor-new.html');
save_page(fetch('admin/print.php?date=' . date('Y-m-d', strtotime('+1 day')), $adminJar), 'admin/print.html');

// Doctor portal (doctor B)
$docJar = "$tmp/doctor.txt";
$login = fetch('portal/login.php', $docJar);
save_page($login, 'portal/login.html');
fetch('portal/login.php', $docJar, ['csrf' => csrf_from($login), 'username' => 'dr.b', 'password' => 'preview-only']);
// signing in opens the patient list, which marks bookings as seen; reset some so "New" shows
$pdo->exec('UPDATE appointments SET seen_by_doctor = 0 WHERE doctor_id = 2 AND id % 3 <> 0');
foreach (['index', 'timetable', 'password'] as $p) save_page(fetch("portal/$p.php", $docJar), "portal/$p.html");

// Show the SMS page as if a provider were set up (no bookings happen after this point)
$pdo->exec("UPDATE settings SET svalue = 'notify_lk' WHERE skey = 'sms_provider'");
save_page(fetch('admin/sms.php', $adminJar), 'admin/sms.html');

/* ---------- 5. assets ---------- */
foreach (['assets/css', 'assets/js', 'assets/img'] as $d) {
    @mkdir("$out/$d", 0777, true);
    foreach (glob("$root/$d/*") as $f) copy($f, "$out/$d/" . basename($f));
}
file_put_contents("$out/.nojekyll", '');
file_put_contents("$out/robots.txt", "User-agent: *\nDisallow: /\n");

proc_terminate($server);
array_map('unlink', glob("$tmp/*"));
@rmdir($tmp);
echo 'Preview built in ' . $out . ' (' . (count(glob("$out/*.html")) + count(glob("$out/*/*.html"))) . " pages)\n";
