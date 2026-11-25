<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    if (DB_DRIVER === 'mysql') {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SET time_zone = '+05:30'");
    } else {
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 10000');
    }
    db_migrate($pdo);
    return $pdo;
}

function db_is_sqlite(): bool
{
    return DB_DRIVER !== 'mysql';
}

function db_migrate(PDO $pdo): void
{
    $auto = db_is_sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $text = db_is_sqlite() ? 'TEXT' : 'VARCHAR(255)';
    $long = 'TEXT';
    $engine = db_is_sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(64) PRIMARY KEY,
        svalue $long
    )$engine");

    $version = (int)$pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_version'")->fetchColumn();
    if ($version < 1) db_migrate_v1($pdo, $auto, $text, $long, $engine);
    if ($version < 2) db_migrate_v2($pdo, $auto, $text, $long, $engine);
    if ($version < 3) db_migrate_v3($pdo, $auto, $text, $long, $engine);
}

/** Version 1: core tables and sample data */
function db_migrate_v1(PDO $pdo, string $auto, string $text, string $long, string $engine): void
{

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id $auto,
        username $text NOT NULL UNIQUE,
        name $text NOT NULL,
        password_hash $text NOT NULL,
        created_at $text NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id $auto,
        ip VARCHAR(45) NOT NULL,
        attempted_at INT NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS specialties (
        id $auto,
        name $text NOT NULL,
        description $long,
        sort_order INT NOT NULL DEFAULT 0
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
        id $auto,
        name $text NOT NULL,
        specialty_id INT,
        qualifications $text,
        bio $long,
        photo $text,
        fee DECIMAL(10,2) NOT NULL DEFAULT 0,
        active INT NOT NULL DEFAULT 1,
        created_at $text NOT NULL,
        FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE SET NULL
    )$engine");

    // Weekly recurring sessions. weekday: 0 = Sunday … 6 = Saturday
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedules (
        id $auto,
        doctor_id INT NOT NULL,
        weekday INT NOT NULL,
        start_time VARCHAR(5) NOT NULL,
        end_time VARCHAR(5) NOT NULL,
        max_patients INT NOT NULL DEFAULT 20,
        minutes_per_patient INT NOT NULL DEFAULT 10,
        room $text,
        active INT NOT NULL DEFAULT 1,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )$engine");

    // Doctor not available on a date (whole day if schedule_id is NULL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
        id $auto,
        doctor_id INT NOT NULL,
        leave_date VARCHAR(10) NOT NULL,
        schedule_id INT,
        reason $text,
        created_at $text NOT NULL,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id $auto,
        ref VARCHAR(12) NOT NULL UNIQUE,
        doctor_id INT NOT NULL,
        schedule_id INT NOT NULL,
        appt_date VARCHAR(10) NOT NULL,
        number INT NOT NULL,
        patient_name $text NOT NULL,
        phone VARCHAR(20) NOT NULL,
        nic VARCHAR(20),
        age INT,
        gender VARCHAR(10),
        notes $long,
        status VARCHAR(12) NOT NULL DEFAULT 'booked',
        source VARCHAR(10) NOT NULL DEFAULT 'online',
        ip VARCHAR(45),
        created_at $text NOT NULL,
        UNIQUE (schedule_id, appt_date, number),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
    )$engine");

    foreach (['CREATE INDEX idx_appt_date ON appointments (appt_date)', 'CREATE INDEX idx_appt_phone ON appointments (phone)'] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* index already exists */ }
    }

    db_seed($pdo);

    $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES ('schema_version', '1')")->execute();
}

/** Version 2: doctor logins, SMS notifications */
function db_migrate_v2(PDO $pdo, string $auto, string $text, string $long, string $engine): void
{
    $pdo->exec('ALTER TABLE doctors ADD COLUMN username VARCHAR(64)');
    $pdo->exec("ALTER TABLE doctors ADD COLUMN password_hash $text");
    $pdo->exec('ALTER TABLE doctors ADD COLUMN login_enabled INT NOT NULL DEFAULT 0');
    $pdo->exec('CREATE UNIQUE INDEX idx_doctor_username ON doctors (username)');
    // lets the doctor's page highlight bookings they haven't seen yet
    $pdo->exec('ALTER TABLE appointments ADD COLUMN seen_by_doctor INT NOT NULL DEFAULT 0');

    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_log (
        id $auto,
        appointment_id INT,
        recipient VARCHAR(20) NOT NULL,
        kind VARCHAR(20) NOT NULL,
        message $long NOT NULL,
        status VARCHAR(12) NOT NULL,
        response $long,
        created_at $text NOT NULL
    )$engine");

    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ([
        'sms_provider'        => 'off',   // off | notify_lk | text_lk
        'sms_user_id'         => '',
        'sms_api_key'         => '',
        'sms_sender_id'       => '',
        'official_number'     => '',
        'sms_to_patient'      => '1',
        'sms_to_official'     => '1',
    ] as $k => $v) $st->execute([$k, $v]);

    $pdo->exec("UPDATE settings SET svalue = '2' WHERE skey = 'schema_version'");
}

function db_seed(PDO $pdo): void
{
    $settings = [
        'hospital_name'      => 'Family Care Hospital',
        'hospital_town'      => 'Kelaniya',
        'tagline'            => 'Channel your doctor without the queue.',
        'phone'              => '+94 11 000 0000',
        'emergency_phone'    => '+94 11 000 0000',
        'email'              => 'info@familycare.lk',
        'address'            => 'Kandy Road, Kelaniya, Sri Lanka',
        'opening_hours'      => "OPD: open daily, 7.00 am – 10.00 pm\nPharmacy & laboratory: 24 hours",
        'hospital_fee'       => '600',
        'booking_days_ahead' => '30',
        'close_before_min'   => '30',
        'map_url'            => 'https://maps.google.com/?q=Kelaniya',
    ];
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    // Sample data so the site is not empty on first launch.
    // Replace with the hospital's real doctors from Admin → Doctors.
    $specs = [
        ['General Physician', 'Fevers, infections, long-term conditions and check-ups.'],
        ['Paediatrician', 'Care for babies, children and teenagers.'],
        ['Obstetrician & Gynaecologist', 'Pregnancy care and women\'s health.'],
        ['Cardiologist', 'Heart and blood pressure.'],
        ['ENT Surgeon', 'Ear, nose and throat.'],
        ['Dermatologist', 'Skin, hair and nails.'],
        ['Eye Surgeon', 'Vision checks, cataract and eye conditions.'],
        ['Dental Surgeon', 'Check-ups, fillings, extractions and cleaning.'],
    ];
    $st = $pdo->prepare('INSERT INTO specialties (name, description, sort_order) VALUES (?, ?, ?)');
    foreach ($specs as $i => $s) $st->execute([$s[0], $s[1], $i]);

    $now = date('Y-m-d H:i:s');
    $docs = [
        // name, specialty, qualifications, fee, sessions [weekday, start, end, max]
        ['Dr. Sample Doctor A', 1, 'MBBS (Colombo)', 1500, [[1, '17:00', '20:00', 25], [3, '17:00', '20:00', 25], [5, '17:00', '20:00', 25]]],
        ['Dr. Sample Doctor B', 2, 'MBBS, MD (Paediatrics)', 2500, [[2, '16:00', '19:00', 20], [6, '09:00', '12:00', 20]]],
        ['Dr. Sample Doctor C', 3, 'MBBS, MS (Obs & Gyn)', 3000, [[1, '09:00', '12:00', 15], [4, '16:30', '19:30', 15]]],
        ['Dr. Sample Doctor D', 4, 'MBBS, MD, MRCP (UK)', 3500, [[3, '18:00', '21:00', 15], [0, '09:00', '12:00', 15]]],
        ['Dr. Sample Doctor E', 5, 'MBBS, MS (ENT)', 3000, [[2, '09:00', '11:30', 15], [5, '09:00', '11:30', 15]]],
        ['Dr. Sample Doctor F', 8, 'BDS (Peradeniya)', 1500, [[0, '08:00', '13:00', 20], [1, '08:00', '13:00', 20], [2, '08:00', '13:00', 20], [3, '08:00', '13:00', 20], [4, '08:00', '13:00', 20], [5, '08:00', '13:00', 20], [6, '08:00', '13:00', 20]]],
    ];
    $dst = $pdo->prepare('INSERT INTO doctors (name, specialty_id, qualifications, bio, fee, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
    $sst = $pdo->prepare('INSERT INTO schedules (doctor_id, weekday, start_time, end_time, max_patients, minutes_per_patient, room, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
    foreach ($docs as $i => $d) {
        $dst->execute([$d[0], $d[1], $d[2], 'Sample profile. Edit this doctor in the admin panel.', $d[3], $now]);
        $id = (int)$pdo->lastInsertId();
        foreach ($d[4] as $s) {
            $mins = (int)round(((strtotime($s[2]) - strtotime($s[1])) / 60) / $s[3]);
            $sst->execute([$id, $s[0], $s[1], $s[2], $s[3], max(5, $mins), 'Room ' . ($i + 1)]);
        }
    }
}

/** Version 3: website content (pages, news & health tips, general services, home page) */
function db_migrate_v3(PDO $pdo, string $auto, string $text, string $long, string $engine): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
        id $auto,
        slug VARCHAR(60) NOT NULL UNIQUE,
        title $text NOT NULL,
        summary $long,
        body $long,
        image $text,
        in_top_menu INT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        published INT NOT NULL DEFAULT 1,
        updated_at $text NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id $auto,
        title $text NOT NULL,
        kind VARCHAR(10) NOT NULL DEFAULT 'tip',
        summary $long,
        body $long,
        image $text,
        youtube_url $text,
        published_on VARCHAR(10) NOT NULL,
        published INT NOT NULL DEFAULT 1
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id $auto,
        name $text NOT NULL,
        description $long,
        sort_order INT NOT NULL DEFAULT 0
    )$engine");

    $now = date('Y-m-d H:i:s');
    $note = "\n\n(Sample text. Edit this page in Admin → Website pages.)";
    $pages = [
        ['about', 'About us', 'A family hospital in Kelaniya, with specialist channelling, a 24-hour pharmacy and laboratory under one roof.',
            "Family Care Hospital looks after families in Kelaniya and the surrounding area. Specialist doctors channel here every day, and our nurses, pharmacists and laboratory staff work together so you can be seen, tested and treated in one visit.\n\nWe keep our waiting times short with numbered appointments you can book online, and we keep our charges clear: you see the doctor's fee and the hospital charge before you book.$note"],
        ['laboratory', 'Laboratory', 'Blood tests, urine tests and more, with reports the same day for most tests.',
            "Our laboratory runs routine and special tests, including full blood count, blood sugar, lipid profile, liver and kidney function, thyroid tests and urine analysis.\n\nMost reports are ready the same day. Ask at the counter about fasting before your test.$note"],
        ['pharmacy', 'Pharmacy', 'A 24-hour pharmacy for prescriptions from our doctors and others.',
            "Our pharmacy is open 24 hours. Bring your prescription from any doctor. Our pharmacists can explain how and when to take your medicines.$note"],
        ['facilities', 'Facilities', 'Consultation rooms, emergency care, scanning, X-ray and parking.',
            "Air-conditioned consultation rooms, an emergency treatment unit, X-ray and ultrasound scanning, ECG, a wound dressing room, a pharmacy and a laboratory, with parking for patients.$note"],
        ['careers', 'Careers', 'Join our team of doctors, nurses and staff.',
            "We are always glad to hear from caring doctors, nurses, pharmacists and support staff. Send your CV to the email address on our contact page.$note"],
    ];
    $st = $pdo->prepare('INSERT INTO pages (slug, title, summary, body, in_top_menu, sort_order, published, updated_at) VALUES (?,?,?,?,1,?,1,?)');
    foreach ($pages as $i => $pg) $st->execute([$pg[0], $pg[1], $pg[2], $pg[3], $i, $now]);

    $services = [
        ['24-hour pharmacy', 'Prescriptions filled day and night.'],
        ['Laboratory', 'Blood and urine tests, most reports the same day.'],
        ['Emergency treatment unit', 'Open 24 hours for accidents and urgent illness.'],
        ['X-ray', 'Digital X-ray with quick reports.'],
        ['Ultrasound scanning', 'Abdominal, pregnancy and other scans.'],
        ['ECG', 'Heart tracing, done on the spot.'],
        ['Ambulance service', 'Call the emergency number for an ambulance.'],
        ['Home sample collection', 'We collect blood samples at your home.'],
        ['Vaccination', 'Child and adult vaccines.'],
        ['Wound dressing', 'Dressings and minor procedures.'],
        ['Health check packages', 'Full body, diabetes and heart checks.'],
    ];
    $st = $pdo->prepare('INSERT INTO services (name, description, sort_order) VALUES (?,?,?)');
    foreach ($services as $i => $sv) $st->execute([$sv[0], $sv[1], $i]);

    $posts = [
        ['Dengue: when to see a doctor', 'Fever for two days or more during the rainy season? Get a blood test.', "If you have a fever for two days or more, with body aches, headache or vomiting, see a doctor and get a full blood count. Drink plenty of fluids, take only paracetamol for fever, and come back at once if you have bleeding, severe stomach pain or feel very weak.\n\n(Sample article. Edit or delete it in Admin → News & health tips.)", -3],
        ['Know your numbers: blood sugar and blood pressure', 'A simple check once a year can catch problems early.', "Adults over 35 should check blood sugar and blood pressure at least once a year, and more often if diabetes or high blood pressure runs in the family. Both tests take a few minutes at our laboratory.\n\n(Sample article. Edit or delete it in Admin → News & health tips.)", -10],
        ['Bringing your child for vaccines', 'What to bring and what to expect.', "Bring your child's health development record (CHDR) book. Mild fever after a vaccine is common; give paracetamol in the right dose and plenty of fluids.\n\n(Sample article. Edit or delete it in Admin → News & health tips.)", -20],
    ];
    $st = $pdo->prepare("INSERT INTO posts (title, kind, summary, body, published_on, published) VALUES (?, 'tip', ?, ?, ?, 1)");
    foreach ($posts as $po) $st->execute([$po[0], $po[1], $po[2], date('Y-m-d', strtotime("$po[3] days"))]);

    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ([
        'hero_title'   => 'Care for the whole family, close to home',
        'hero_text'    => 'Channel specialist doctors in Kelaniya. Book your number online and come in at your time.',
        'hero_image'   => '',
        'about_image'  => '',
        'lab_phone'    => '',
        'whatsapp'     => '',
        'stat1_value'  => '15+',  'stat1_label' => 'Years caring for Kelaniya',
        'stat2_value'  => '{doctors}', 'stat2_label' => 'Specialist doctors',
        'stat3_value'  => '24/7', 'stat3_label' => 'Emergency care and pharmacy',
        'stat4_value'  => '{specialties}', 'stat4_label' => 'Specialties',
    ] as $k => $v) $st->execute([$k, $v]);

    $pdo->exec("UPDATE settings SET svalue = '3' WHERE skey = 'schema_version'");
}
