<?php
declare(strict_types=1);
define('WHMCS', true);
require_once dirname(__DIR__) . '/ecloudhosting.php';

function check(bool $ok, string $name): void
{
    if (!$ok) throw new RuntimeException('Failed: ' . $name);
}

check(ecloudhosting_domain_valid('example.com'), 'domain valid');
check(ecloudhosting_domain_valid('customer.example.com'), 'subdomain valid');
check(!ecloudhosting_domain_valid('bad_name.com'), 'invalid domain');
check(!ecloudhosting_domain_valid('example.com/evil'), 'invalid domain path');
check(ecloudhosting_dns_name('@', 'example.com') === 'example.com.', 'root record');
check(ecloudhosting_dns_name('www', 'example.com') === 'www.example.com.', 'relative name');
check(ecloudhosting_dns_name('app.example.com', 'example.com') === 'app.example.com.', 'fqdn');
try {
    ecloudhosting_dns_name('www.attacker.net', 'example.com');
    throw new RuntimeException('Failed: cross-domain name accepted');
} catch (InvalidArgumentException $e) {
    check(true, 'cross-domain rejected');
}
check(ecloudhosting_dns_protected('mail.example.com.', 'example.com', 'A'), 'mail host protected');
check(ecloudhosting_dns_protected('example.com.', 'example.com', 'MX'), 'MX protected');
check(ecloudhosting_dns_protected('example.com.', 'example.com', 'NS'), 'NS protected');
check(ecloudhosting_dns_protected('_dmarc.example.com.', 'example.com', 'TXT'), 'DMARC protected');
check(ecloudhosting_dns_protected('selector._domainkey.example.com.', 'example.com', 'TXT'), 'DKIM protected');
$record = ecloudhosting_dns_validate('example.com', 'app', 'A', '192.0.2.1', 3600);
check($record['dname'] === 'app.example.com.', 'A normalized');
check($record['record_type'] === 'A', 'A allowed');
try {
    ecloudhosting_dns_validate('example.com', '@', 'A', 'bad-ip', 3600);
    throw new RuntimeException('Failed: invalid IP accepted');
} catch (InvalidArgumentException $e) {
    check(true, 'invalid IP rejected');
}
try {
    ecloudhosting_dns_validate('example.com', 'www', 'TXT', "bad\nrecord", 3600);
    throw new RuntimeException('Failed: newline accepted');
} catch (InvalidArgumentException $e) {
    check(true, 'newline rejected');
}
try {
    new ECloudCpanelApi('attacker.example', 'clientuser', str_repeat('a', 32), ['cp.example.com']);
    throw new RuntimeException('Failed: untrusted host accepted');
} catch (RuntimeException $e) {
    check(true, 'host allowlist');
}
echo "Mail/DNS tests passed\n";
