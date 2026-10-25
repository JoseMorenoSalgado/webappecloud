<?php
declare(strict_types=1);

/** Validated DNS operations for the domain attached to one WHMCS service. */
function ecloudhosting_dns_name(string $input, string $zone): string
{
    $input = strtolower(rtrim(trim($input), '.'));
    $zone = strtolower(rtrim($zone, '.'));
    if ($input === '@' || $input === '') $input = $zone;
    elseif ($input !== $zone && !str_ends_with($input, '.' . $zone) && !str_contains($input, '.')) {
        $input .= '.' . $zone;
    }
    if (strlen($input) > 253 || !($input === $zone || str_ends_with($input, '.' . $zone))) {
        throw new InvalidArgumentException('El registro no pertenece al dominio contratado.');
    }
    foreach (explode('.', $input) as $label) {
        if (!preg_match('/^[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?$/D', $label)) {
            throw new InvalidArgumentException('Nombre DNS no válido.');
        }
    }
    return $input . '.';
}

function ecloudhosting_dns_protected(string $name, string $zone, string $type, string $value = ''): bool
{
    $name = strtolower(rtrim($name, '.'));
    $zone = strtolower(rtrim($zone, '.'));
    $prefix = $name === $zone ? '' : substr($name, 0, -strlen('.' . $zone));
    $first = explode('.', $prefix)[0];
    if (!in_array($type, ['A', 'CNAME', 'TXT'], true)) return true;
    if (in_array($first, ['mail', 'smtp', 'imap', 'pop', 'pop3', 'webmail', 'cpanel',
        'autoconfig', 'autodiscover', 'ftp', '_dmarc'], true)) return true;
    if (str_contains($prefix, '._domainkey') || preg_match('/^v=(spf1|dkim1|dmarc1)/i', trim($value))) return true;
    return false;
}

function ecloudhosting_dns_validate(string $zone, string $name, string $type, string $value, int $ttl): array
{
    $name = ecloudhosting_dns_name($name, $zone);
    $type = strtoupper(trim($type));
    $value = trim($value);
    if ($ttl < 300 || $ttl > 86400 || ecloudhosting_dns_protected($name, $zone, $type, $value)) {
        throw new InvalidArgumentException('Registro reservado o TTL no permitido.');
    }
    if ($type === 'A') {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException('IP IPv4 no válida.');
        }
    } elseif ($type === 'CNAME') {
        if ($name === strtolower(rtrim($zone, '.')) . '.') {
            throw new InvalidArgumentException('El dominio raíz no admite CNAME.');
        }
        $value = strtolower(rtrim($value, '.'));
        if (strlen($value) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $value)) {
            throw new InvalidArgumentException('Destino CNAME no válido.');
        }
        $value .= '.';
    } elseif ($type === 'TXT') {
        if ($value === '' || strlen($value) > 255 || preg_match('/[\r\n\x00-\x1f]/', $value)) {
            throw new InvalidArgumentException('TXT no válido.');
        }
    } else {
        throw new InvalidArgumentException('Tipo de registro no permitido.');
    }
    return ['dname' => $name, 'ttl' => $ttl, 'record_type' => $type, 'data' => [$value]];
}

function ecloudhosting_dns_zone(ECloudCpanelApi $api, string $domain): array
{
    $data = $api->call('DNS', 'parse_zone', ['zone' => $domain]);
    if (!is_array($data)) throw new RuntimeException('Respuesta DNS inesperada.');
    // cPanel UAPI parse_zone returns an array of records, controls and comments.
    $serial = null;
    $records = [];
    foreach ($data as $row) {
        if (!is_array($row) || ($row['type'] ?? null) !== 'record') continue;
        $name = base64_decode((string) ($row['dname_b64'] ?? ''), true);
        if (!is_string($name)) continue;
        $values = [];
        foreach (($row['data_b64'] ?? []) as $base64) {
            $v = is_string($base64) ? base64_decode($base64, true) : false;
            if (!is_string($v)) continue;
            $values[] = $v;
        }
        if (($row['record_type'] ?? null) === 'SOA') {
            foreach ($values as $v) {
                if (preg_match('/^(?:[0-9]{8,14})$/D', trim($v))) {
                    $serial = trim($v);
                    break;
                }
            }
        }
        if (!isset($row['line_index']) || !is_numeric($row['line_index'])) continue;
        $records[] = [
            'line' => (int) $row['line_index'],
            'name' => $name,
            'type' => (string) ($row['record_type'] ?? ''),
            'ttl' => (int) ($row['ttl'] ?? 0),
            'values' => $values,
        ];
    }
    return ['serial' => $serial, 'records' => $records];
}

function ecloudhosting_dns_authority(ECloudCpanelApi $api, string $domain, array $expectedNameservers): bool
{
    if (!$expectedNameservers) return false;
    $data = $api->call('DNS', 'has_local_authority', ['domain' => $domain]);
    if (!is_array($data) || (string) ($data[0]['domain'] ?? '') !== $domain ||
        (int) ($data[0]['local_authority'] ?? 0) !== 1) return false;
    $public = dns_get_record($domain, DNS_NS);
    if (!is_array($public) || !$public) return false;
    $actual = [];
    foreach ($public as $record) {
        if (isset($record['target'])) $actual[] = strtolower(rtrim((string) $record['target'], '.'));
    }
    $expected = array_map(static fn($n) => strtolower(rtrim((string) $n, '.')), $expectedNameservers);
    sort($actual); sort($expected);
    return $actual === $expected;
}

function ecloudhosting_dns_apply(ECloudCpanelApi $api, string $domain, array $request): void
{
    $before = ecloudhosting_dns_zone($api, $domain);
    $serial = $before['serial'];
    if ($serial === null || !preg_match('/^[0-9]{8,14}$/D', $serial)) {
        throw new RuntimeException('No se pudo leer el serial SOA; DNS permanece sin cambios.');
    }
    $intent = (string) ($request['action'] ?? '');
    $params = ['zone' => $domain, 'serial' => $serial];
    $item = ecloudhosting_dns_validate(
        $domain,
        (string) ($request['record_name'] ?? ''),
        (string) ($request['record_type'] ?? ''),
        (string) ($request['record_value'] ?? ''),
        (int) ($request['ttl'] ?? 14400)
    );
    if ($intent === 'dns_add') {
        $params['add'] = json_encode($item, JSON_THROW_ON_ERROR);
    } elseif ($intent === 'dns_edit') {
        $line = filter_var($request['line'] ?? null, FILTER_VALIDATE_INT);
        if ($line === false || $line < 0) throw new InvalidArgumentException('Índice DNS inválido.');
        $found = null;
        foreach ($before['records'] as $r) {
            if ($r['line'] === $line) { $found = $r; break; }
        }
        if (!$found || ecloudhosting_dns_protected($found['name'], $domain, $found['type'], implode('', $found['values']))) {
            throw new RuntimeException('Registro protegido o inexistente.');
        }
        $fingerprint = (string) ($request['fingerprint'] ?? '');
        if (!hash_equals(hash('sha256', json_encode($found)), $fingerprint)) {
            throw new RuntimeException('El registro cambió. Actualice la página.');
        }
        $params['edit'] = json_encode(['line_index' => $line] + $item, JSON_THROW_ON_ERROR);
    } else {
        throw new InvalidArgumentException('Acción DNS no admitida.');
    }
    $api->call('DNS', 'mass_edit_zone', $params, true);
}
