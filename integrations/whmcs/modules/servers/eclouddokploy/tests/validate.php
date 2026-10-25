<?php
declare(strict_types=1);

/** Offline, no-network smoke tests for module validation and secret derivation. */
define('WHMCS', true);
require_once dirname(__DIR__) . '/eclouddokploy.php';

function check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('Test failed: ' . $label);
}

check(eclouddokploy_valid_domain('example.com'), 'valid domain');
check(eclouddokploy_valid_domain('app.example.com'), 'valid subdomain');
check(!eclouddokploy_valid_domain('bad_domain.com'), 'reject underscore');
check(!eclouddokploy_valid_domain('example.com/path'), 'reject domain path');
check(eclouddokploy_valid_git('https://github.com/JoseMorenoSalgado/webappecloud.git', 'main'), 'valid Git URL');
check(!eclouddokploy_valid_git('http://github.com/owner/repo', 'main'), 'reject plain HTTP');
check(!eclouddokploy_valid_git('https://github.com/owner/repo?token=secret', 'main'), 'reject query token');
check(!eclouddokploy_valid_git('https://git.bad.example/owner/repo', 'main'), 'reject untrusted host');
$seed = str_repeat('ab', 32);
$one = eclouddokploy_password($seed, 'db-user', 123);
check(strlen($one) === 64, 'derived password length');
check(hash_equals($one, eclouddokploy_password($seed, 'db-user', 123)), 'deterministic retries');
check(!hash_equals($one, eclouddokploy_password($seed, 'db-root', 123)), 'separate database root secret');
check(!hash_equals($one, eclouddokploy_password($seed, 'db-user', 124)), 'separate client credentials');

try {
    new ECloudDokployClient('http://localhost:3000', 'x');
    throw new RuntimeException('Test failed: accepted HTTP');
} catch (RuntimeException $e) {
    check($e->getMessage() !== 'Test failed: accepted HTTP', 'reject HTTP Dokploy API');
}
echo "Dokploy provisioning validations passed\n";
