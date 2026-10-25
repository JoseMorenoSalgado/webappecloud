<?php
declare(strict_types=1);

/** Only exercise the ephemeral mail.test local HTTPS mock, never production. */
define('WHMCS', true);
require_once dirname(__DIR__) . '/eclouddokploy.php';

function ensure(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('Mail HTTPS test failed: ' . $label);
}

$host = (string) getenv('ECLOUD_CPANEL_TEST_HOST');
$key = (string) getenv('ECLOUD_CPANEL_TEST_TOKEN');
if ($host !== 'mail.test' || $key !== 'cpanel-staging-token-123456789') {
    throw new RuntimeException('Mail UAPI tests may only run against local TLS mock.');
}
$api = new ECloudCpanelMailClient($host, 'eclient', $key);
$rows = eclouddokploy_mail_rows(
    $api->call('list_pops_with_disk', ['domain'=>'empresa.test', 'skip_main'=>1]),
    'empresa.test'
);
ensure(count($rows) === 1 && $rows[0]['address'] === 'admin@empresa.test', 'list account');
$api->call('add_pop', [
    'email'=>'ventas', 'domain'=>'empresa.test',
    'password'=>'ValidMockPassword2026!',
    'quota'=>1024,
], true);
$rows = eclouddokploy_mail_rows(
    $api->call('list_pops_with_disk', ['domain'=>'empresa.test', 'skip_main'=>1]),
    'empresa.test'
);
ensure(eclouddokploy_email_exists($rows, 'ventas'), 'created mailbox listed');
$api->call('passwd_pop',[
    'email'=>'ventas','domain'=>'empresa.test','password'=>'NewPassword2026!!!',
],true);
$api->call('edit_pop_quota',[
    'email'=>'ventas','domain'=>'empresa.test','quota'=>768,
],true);
$rows = eclouddokploy_mail_rows(
    $api->call('list_pops_with_disk',['domain'=>'empresa.test']),
    'empresa.test'
);
foreach ($rows as $row) {
    if ($row['local'] === 'ventas') ensure($row['quota'] === '768 MB', 'quota change');
}

try {
    $api->call('add_pop',[
        'email'=>'outside','domain'=>'attacker.test',
        'password'=>'StrongPassword123!','quota'=>512,
    ],true);
    throw new RuntimeException('Test failed: cross-domain mailbox accepted.');
} catch (RuntimeException $e) {
    ensure(!str_starts_with($e->getMessage(),'Test failed:'), 'cross-domain operation refused by cPanel');
}
$api->call('delete_pop', ['email'=>'ventas','domain'=>'empresa.test'], true);
$rows=eclouddokploy_mail_rows(
    $api->call('list_pops_with_disk',['domain'=>'empresa.test']),
    'empresa.test'
);
ensure(!eclouddokploy_email_exists($rows, 'ventas'), 'deleted mailbox absent');
try {
    $wrong = new ECloudCpanelMailClient($host, 'eclient', 'wrong-token-123456789');
    $wrong->call('list_pops_with_disk', ['domain'=>'empresa.test']);
    throw new RuntimeException('Test failed: unauthorized API token accepted.');
} catch (RuntimeException $e) {
    ensure(!str_starts_with($e->getMessage(),'Test failed:'), 'unauthorized token rejected');
}
echo "cPanel corporate email HTTPS mock contracts passed\n";
