<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$doc = $id ? get_doctor($id, false) : null;
if ($id && !$doc) { flash('error', 'Doctor not found.'); redirect('doctors.php'); }

$errors = [];
$valid_time = fn($t) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $data = [
            'name'           => trim((string)($_POST['name'] ?? '')),
            'specialty_id'   => (int)($_POST['specialty_id'] ?? 0) ?: null,
            'qualifications' => trim((string)($_POST['qualifications'] ?? '')),
            'bio'            => trim((string)($_POST['bio'] ?? '')),
            'fee'            => (float)($_POST['fee'] ?? 0),
            'active'         => empty($_POST['active']) ? 0 : 1,
        ];
        if ($data['name'] === '') $errors[] = 'Enter the doctor\'s name.';
        if ($data['fee'] < 0) $errors[] = 'Fee can\'t be negative.';

        $photo = $doc['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $f = $_FILES['photo'];
            $info = $f['error'] === UPLOAD_ERR_OK ? @getimagesize($f['tmp_name']) : false;
            $ext = $info ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
            if (!$ext) $errors[] = 'Photo must be a JPG, PNG or WebP image.';
            elseif ($f['size'] > 3 * 1024 * 1024) $errors[] = 'Photo must be smaller than 3 MB.';
            else {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                $newName = bin2hex(random_bytes(10)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $newName)) {
                    if ($photo) @unlink(UPLOAD_DIR . '/' . $photo);
                    $photo = $newName;
                } else $errors[] = 'Couldn\'t save the photo. Check that the uploads folder is writable.';
            }
        }
        if (!empty($_POST['remove_photo']) && $photo) { @unlink(UPLOAD_DIR . '/' . $photo); $photo = null; }

        if (!$errors) {
            if ($doc) {
                $pdo->prepare('UPDATE doctors SET name=?, specialty_id=?, qualifications=?, bio=?, fee=?, active=?, photo=? WHERE id=?')
                    ->execute([$data['name'], $data['specialty_id'], $data['qualifications'], $data['bio'], $data['fee'], $data['active'], $photo, $id]);
                flash('success', 'Doctor details saved.');
            } else {
                $pdo->prepare('INSERT INTO doctors (name, specialty_id, qualifications, bio, fee, active, photo, created_at) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$data['name'], $data['specialty_id'], $data['qualifications'], $data['bio'], $data['fee'], $data['active'], $photo, date('Y-m-d H:i:s')]);
                $id = (int)$pdo->lastInsertId();
                flash('success', 'Doctor added. Now add their weekly sessions below so patients can book.');
            }
            redirect('doctor_edit.php?id=' . $id);
        }
        $doc = array_merge($doc ?? [], $data, ['photo' => $photo]);
    }

    if ($doc && $action === 'add_session') {
        $days = array_map('intval', (array)($_POST['weekdays'] ?? []));
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        $max = (int)($_POST['max_patients'] ?? 0);
        $mins = (int)($_POST['minutes_per_patient'] ?? 0);
        $room = trim((string)($_POST['room'] ?? ''));
        if (!$days) $errors[] = 'Tick at least one day.';
        if (!$valid_time($start) || !$valid_time($end) || $end <= $start) $errors[] = 'End time must be after start time.';
        if ($max < 1 || $max > 500) $errors[] = 'Maximum patients must be between 1 and 500.';
        if ($mins < 1 || $mins > 120) $errors[] = 'Minutes per patient must be between 1 and 120.';
        if (!$errors) {
            $st = $pdo->prepare('INSERT INTO schedules (doctor_id, weekday, start_time, end_time, max_patients, minutes_per_patient, room, active) VALUES (?,?,?,?,?,?,?,1)');
            foreach ($days as $w) if ($w >= 0 && $w <= 6) $st->execute([$id, $w, $start, $end, $max, $mins, $room ?: null]);
            flash('success', count($days) === 1 ? 'Session added.' : count($days) . ' sessions added.');
            redirect('doctor_edit.php?id=' . $id . '#sessions');
        }
    }

    if ($doc && $action === 'update_session') {
        $sid = (int)$_POST['schedule_id'];
        $start = (string)($_POST['start_time'] ?? '');
        $end = (string)($_POST['end_time'] ?? '');
        if (!$valid_time($start) || !$valid_time($end) || $end <= $start) {
            flash('error', 'End time must be after start time.');
        } else {
            $pdo->prepare('UPDATE schedules SET start_time=?, end_time=?, max_patients=?, minutes_per_patient=?, room=?, active=? WHERE id=? AND doctor_id=?')
                ->execute([$start, $end, max(1, (int)$_POST['max_patients']), max(1, (int)$_POST['minutes_per_patient']), trim((string)$_POST['room']) ?: null, empty($_POST['active']) ? 0 : 1, $sid, $id]);
            flash('success', 'Session updated.');
        }
        redirect('doctor_edit.php?id=' . $id . '#sessions');
    }

    if ($doc && $action === 'delete_session') {
        $sid = (int)$_POST['schedule_id'];
        $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE schedule_id = ? AND appt_date >= ? AND status <> 'cancelled'");
        $st->execute([$sid, date('Y-m-d')]);
        $upcoming = (int)$st->fetchColumn();
        $st = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE schedule_id = ?');
        $st->execute([$sid]);
        if ($upcoming > 0) {
            flash('error', "This session has $upcoming upcoming bookings. Switch it off instead, and contact those patients.");
        } elseif ((int)$st->fetchColumn() > 0) {
            $pdo->prepare('UPDATE schedules SET active = 0 WHERE id = ? AND doctor_id = ?')->execute([$sid, $id]);
            flash('success', 'Session switched off (kept for past appointment records).');
        } else {
            $pdo->prepare('DELETE FROM schedules WHERE id = ? AND doctor_id = ?')->execute([$sid, $id]);
            flash('success', 'Session deleted.');
        }
        redirect('doctor_edit.php?id=' . $id . '#sessions');
    }
}

$specs = specialties();
$schedules = $doc && !empty($doc['id']) ? doctor_schedules((int)$doc['id'], false) : [];
admin_header($doc ? 'Edit ' . $doc['name'] : 'Add a doctor', 'doctors');
?>
<p><a href="doctors.php">All doctors</a></p>
<?php foreach ($errors as $err): ?><div class="alert alert--error"><?= e($err) ?></div><?php endforeach; ?>

<section class="panel">
  <h2>Profile</h2>
  <form method="post" enctype="multipart/form-data" class="grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <label class="span-2">Name, as patients should see it <input name="name" value="<?= e($doc['name'] ?? '') ?>" required placeholder="Dr. Nimal Perera"></label>
    <label>Specialty
      <select name="specialty_id">
        <option value="">None</option>
        <?php foreach ($specs as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)($doc['specialty_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Doctor's fee (Rs.) <input type="number" name="fee" min="0" step="50" value="<?= e($doc['fee'] ?? '0') ?>"></label>
    <label class="span-2">Qualifications <input name="qualifications" value="<?= e($doc['qualifications'] ?? '') ?>" placeholder="MBBS (Colombo), MD"></label>
    <label class="span-4">About the doctor <textarea name="bio" rows="3"><?= e($doc['bio'] ?? '') ?></textarea></label>
    <div class="span-2 photo-field">
      <?php if (!empty($doc['photo'])): ?><?= doctor_avatar($doc) ?><?php endif; ?>
      <label>Photo (JPG, PNG or WebP, up to 3 MB) <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
      <?php if (!empty($doc['photo'])): ?><label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove photo</label><?php endif; ?>
    </div>
    <label class="check span-2"><input type="checkbox" name="active" value="1"<?= ($doc['active'] ?? 1) ? ' checked' : '' ?>> Show on website and take bookings</label>
    <div class="span-4"><button class="btn btn--red" type="submit"><?= $id ? 'Save changes' : 'Add doctor' ?></button></div>
  </form>
</section>

<?php if ($id): ?>
<section class="panel" id="sessions">
  <h2>Weekly sessions</h2>
  <p class="muted">Each session repeats every week. Patients get numbers in order; the estimated time is the start time plus minutes per patient.</p>
  <?php if ($schedules): ?>
  <div class="table-scroll">
  <table class="table table--form">
    <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Max patients</th><th>Min / patient</th><th>Room</th><th>On</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($schedules as $s): $fid = 'sf' . (int)$s['id']; ?>
      <tr class="<?= $s['active'] ? '' : 'is-muted' ?>">
        <td><strong><?= e(WEEKDAYS[(int)$s['weekday']]) ?></strong></td>
        <td><input form="<?= $fid ?>" type="time" name="start_time" value="<?= e($s['start_time']) ?>" required aria-label="Start time"></td>
        <td><input form="<?= $fid ?>" type="time" name="end_time" value="<?= e($s['end_time']) ?>" required aria-label="End time"></td>
        <td><input form="<?= $fid ?>" type="number" name="max_patients" min="1" max="500" value="<?= (int)$s['max_patients'] ?>" aria-label="Max patients"></td>
        <td><input form="<?= $fid ?>" type="number" name="minutes_per_patient" min="1" max="120" value="<?= (int)$s['minutes_per_patient'] ?>" aria-label="Minutes per patient"></td>
        <td><input form="<?= $fid ?>" name="room" value="<?= e($s['room']) ?>" aria-label="Room"></td>
        <td><input form="<?= $fid ?>" type="checkbox" name="active" value="1"<?= $s['active'] ? ' checked' : '' ?> aria-label="Session on"></td>
        <td class="nowrap">
          <form id="<?= $fid ?>" method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="update_session">
            <button class="btn btn--navy btn--small" type="submit">Save</button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('Remove this session?')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete_session">
            <button class="btn btn--danger btn--small" type="submit">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <div class="alert alert--info">No sessions yet. Patients can't book this doctor until you add at least one.</div>
  <?php endif; ?>

  <h3>Add a session</h3>
  <form method="post" class="grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="add_session">
    <fieldset class="span-4 days">
      <legend>Days (tick all that apply)</legend>
      <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): ?>
        <label class="day"><input type="checkbox" name="weekdays[]" value="<?= $w ?>"> <?= e(WEEKDAYS[$w]) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <label>Start time <input type="time" name="start_time" value="17:00" required></label>
    <label>End time <input type="time" name="end_time" value="20:00" required></label>
    <label>Max patients <input type="number" name="max_patients" value="20" min="1" max="500" required></label>
    <label>Minutes per patient <input type="number" name="minutes_per_patient" value="10" min="1" max="120" required></label>
    <label class="span-2">Room (optional) <input name="room" placeholder="Room 3, ground floor"></label>
    <div class="span-4"><button class="btn btn--red" type="submit">Add session</button></div>
  </form>
</section>
<?php endif; ?>
<?php admin_footer(); ?>
