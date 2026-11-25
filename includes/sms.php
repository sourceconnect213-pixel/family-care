<?php
/**
 * SMS notifications through a Sri Lankan SMS gateway.
 * Configure in Admin → Hospital settings → SMS messages.
 *
 * Messages go out from the hospital's registered sender ID (its official name/number),
 * never from a doctor's personal phone:
 *   - the patient gets a confirmation (and a notice if the booking is cancelled)
 *   - the hospital's official number gets a "Dr X has been channelled" alert
 */

const SMS_PROVIDERS = [
    'off'       => 'Off (don\'t send SMS)',
    'notify_lk' => 'Notify.lk',
    'text_lk'   => 'Text.lk',
];

/** 07XXXXXXXX → 947XXXXXXXX (format the gateways expect) */
function sms_number(string $phone): ?string
{
    $d = normalize_phone($phone);
    return preg_match('/^0\d{9}$/', $d) ? '94' . substr($d, 1) : null;
}

/**
 * Send one SMS and record it in sms_log.
 * @return array ['ok' => bool, 'response' => string]
 */
function send_sms(string $to, string $message, string $kind = 'other', ?int $appointmentId = null): array
{
    $provider = setting('sms_provider', 'off');
    $number = sms_number($to);
    if ($provider === 'off') return ['ok' => false, 'response' => 'SMS is switched off'];
    if (!$number) {
        sms_log($appointmentId, $to, $kind, $message, 'failed', 'Invalid phone number');
        return ['ok' => false, 'response' => 'Invalid phone number'];
    }

    $userId = setting('sms_user_id');
    $apiKey = setting('sms_api_key');
    $sender = setting('sms_sender_id');

    if ($provider === 'notify_lk') {
        // https://developer.notify.lk/api-endpoints  (GET/POST /api/v1/send)
        $res = http_request('POST', 'https://app.notify.lk/api/v1/send', [], http_build_query([
            'user_id' => $userId, 'api_key' => $apiKey, 'sender_id' => $sender, 'to' => $number, 'message' => $message,
        ]), 'application/x-www-form-urlencoded');
        $json = json_decode($res['body'] ?? '', true);
        $ok = $res['code'] === 200 && ($json['status'] ?? '') === 'success';
    } elseif ($provider === 'text_lk') {
        // https://text.lk/docs/send-sms/  (POST /api/v3/sms/send, Bearer token)
        $res = http_request('POST', 'https://app.text.lk/api/v3/sms/send', ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
            json_encode(['recipient' => $number, 'sender_id' => $sender, 'type' => 'plain', 'message' => $message]), 'application/json');
        $json = json_decode($res['body'] ?? '', true);
        $ok = $res['code'] >= 200 && $res['code'] < 300 && ($json['status'] ?? '') === 'success';
    } else {
        return ['ok' => false, 'response' => 'Unknown SMS provider'];
    }

    $response = mb_substr(($res['error'] ?: '') . ' ' . ($res['body'] ?? ''), 0, 500);
    sms_log($appointmentId, $number, $kind, $message, $ok ? 'sent' : 'failed', trim($response));
    return ['ok' => $ok, 'response' => trim($response)];
}

function http_request(string $method, string $url, array $headers, string $body, string $contentType): array
{
    // For testing only: send every request to a local mock gateway
    if ($mock = getenv('FC_SMS_MOCK_URL')) $url = $mock . '?to=' . rawurlencode(parse_url($url, PHP_URL_HOST));
    if (!function_exists('curl_init')) return ['code' => 0, 'body' => '', 'error' => 'PHP curl extension is not installed'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: ' . $contentType], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $out = curl_exec($ch);
    $res = ['code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => $out === false ? '' : (string)$out, 'error' => curl_error($ch)];
    curl_close($ch);
    return $res;
}

function sms_log(?int $appointmentId, string $to, string $kind, string $message, string $status, string $response): void
{
    db()->prepare('INSERT INTO sms_log (appointment_id, recipient, kind, message, status, response, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$appointmentId, $to, $kind, $message, $status, $response, date('Y-m-d H:i:s')]);
}

/** Short hospital name for SMS, e.g. "Family Care Hospital Kelaniya" */
function sms_signature(): string
{
    return setting('hospital_name') . ' ' . setting('hospital_town');
}

function notify_booking(array $a): void
{
    if (setting('sms_provider', 'off') === 'off') return;
    $est = fmt_time(estimated_time(['start_time' => $a['start_time'], 'minutes_per_patient' => $a['minutes_per_patient']], (int)$a['number']));
    $date = fmt_date($a['appt_date'], 'D j M');

    if (setting('sms_to_patient') === '1') {
        send_sms($a['phone'],
            "Appointment confirmed. {$a['doctor_name']}, $date. Your No. {$a['number']}, est. time $est. Ref {$a['ref']}. Please come 15 min early. - " . sms_signature(),
            'patient_booked', (int)$a['id']);
    }
    $official = setting('official_number');
    if (setting('sms_to_official') === '1' && $official !== '') {
        send_sms($official,
            "New channelling: {$a['doctor_name']} - $date " . fmt_time($a['start_time']) . " session. No. {$a['number']}, {$a['patient_name']} ({$a['phone']}). Ref {$a['ref']}",
            'official_booked', (int)$a['id']);
    }
}

function notify_cancellation(array $a, string $by): void
{
    if (setting('sms_provider', 'off') === 'off') return;
    $date = fmt_date($a['appt_date'], 'D j M');
    if (setting('sms_to_patient') === '1') {
        send_sms($a['phone'],
            "Your appointment Ref {$a['ref']} with {$a['doctor_name']} on $date (No. {$a['number']}) has been cancelled. Call " . setting('phone') . " for help. - " . sms_signature(),
            'patient_cancelled', (int)$a['id']);
    }
    $official = setting('official_number');
    if (setting('sms_to_official') === '1' && $official !== '') {
        send_sms($official,
            "Cancelled by $by: {$a['doctor_name']} - $date, No. {$a['number']}, {$a['patient_name']} ({$a['phone']}). Ref {$a['ref']}",
            'official_cancelled', (int)$a['id']);
    }
}
