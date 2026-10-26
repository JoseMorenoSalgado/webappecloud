<?php
declare(strict_types=1);

/**
 * Lightweight mail discovery: existing cPanel service belonging to the SAME
 * logged-in WHMCS customer, not a new mailbox and no token delegation.
 * The customer opens WHMCS's native cPanel product panel with a normal link.
 *
 * Matching avoids cross-client/domain visibility. Ambiguity -> no link.
 */
function eclouddokploy_auto_domain_candidates(string $domain): array
{
    if (!eclouddokploy_valid_domain($domain)) return [];
    $parts = explode('.', strtolower($domain));
    $candidates = [];
    // Exact first; then parent domains. Never suggest bare TLD as a domain.
    for ($i = 0; $i < count($parts) - 1; $i++) {
        $candidate = implode('.', array_slice($parts, $i));
        if (eclouddokploy_valid_domain($candidate)) $candidates[] = $candidate;
    }
    return $candidates;
}

function eclouddokploy_auto_mail_choose(array $matches, string $domain): ?array
{
    $candidates = eclouddokploy_auto_domain_candidates($domain);
    if (!$candidates) return null;
    $ownedByDomain = [];
    foreach ($matches as $row) {
        if (!is_array($row)) continue;
        $id = filter_var($row['service_id'] ?? null, FILTER_VALIDATE_INT);
        $d = strtolower(rtrim(trim((string) ($row['domain'] ?? '')), '.'));
        if ($id === false || $id <= 0 || !in_array($d, $candidates, true)) continue;
        $ownedByDomain[$d][$id] = ['service_id' => (int) $id, 'domain' => $d];
    }
    // Only consider the most-specific match (exact then parent); NEVER choose
    // between two cPanel services for the same domain automatically.
    foreach ($candidates as $candidate) {
        if (empty($ownedByDomain[$candidate])) continue;
        return count($ownedByDomain[$candidate]) === 1
            ? array_values($ownedByDomain[$candidate])[0] : null;
    }
    return null;
}

function eclouddokploy_auto_cpanel_mail(int $ownerId, string $appDomain): ?array
{
    if ($ownerId < 1 || !eclouddokploy_valid_domain($appDomain)) return null;
    $rows = \WHMCS\Database\Capsule::table('tblhosting')
        ->join('tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid')
        ->where('tblhosting.userid', $ownerId)
        ->where('tblhosting.domainstatus', 'Active')
        ->where('tblproducts.servertype', 'cpanel')
        ->where('tblhosting.server', '>', 0)
        ->get(['tblhosting.id as service_id', 'tblhosting.domain']);
    $values = [];
    foreach ($rows as $row) {
        $values[] = ['service_id' => (int) $row->service_id, 'domain' => (string) $row->domain];
    }
    return eclouddokploy_auto_mail_choose($values, $appDomain);
}
