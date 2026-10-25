<?php
declare(strict_types=1);

/**
 * Memory-only WHMCS DB + authenticated local HTTPS Dokploy mock.
 * Real paid CreateAccount now creates the project+environment before Git.
 */
namespace WHMCS\Database {
    final class FakeQuery {
        private string $name;
        private ?int $serviceId = null;
        public function __construct(string $name) { $this->name = $name; }
        public function where(string $column, $value): self {
            if ($column === 'service_id') {
                $this->serviceId = (int) $value;
            }
            return $this;
        }
        public function first(array $columns = []): ?object {
            $row = Capsule::$tables[$this->name][$this->serviceId ?? 0] ?? null;
            return $row ? (object) $row : null;
        }
        public function update(array $changes): int {
            if (!isset(Capsule::$tables[$this->name][$this->serviceId ?? 0])) {
                return 0;
            }
            foreach ($changes as $key => $value) {
                Capsule::$tables[$this->name][$this->serviceId][$key] = $value;
            }
            return 1;
        }
    }
    final class Capsule {
        public static array $tables = [
            'mod_ecloud_dokploy_services' => [
                310 => [
                    'service_id'=>310, 'owner_client_id'=>25,
                    'state'=>'awaiting_setup', 'route_fingerprint'=>null,
                    'project_id'=>null, 'environment_id'=>null,
                ],
                311 => [
                    'service_id'=>311, 'owner_client_id'=>25,
                    'state'=>'awaiting_setup', 'route_fingerprint'=>null,
                    'project_id'=>null, 'environment_id'=>null,
                ],
            ],
        ];
        public static function table(string $name): FakeQuery {
            return new FakeQuery($name);
        }
    }
}
namespace {
    define('WHMCS', true);
    require_once dirname(__DIR__) . '/eclouddokploy.php';

    function assertion(bool $valid, string $label): void {
        if (!$valid) throw new \RuntimeException('Project bootstrap failed: ' . $label);
    }
    $url = (string) getenv('ECLOUD_DOKPLOY_URL');
    if ($url !== 'https://dokploy.test') {
        throw new \RuntimeException('Only the local Dokploy TLS mock is permitted.');
    }
    $fingerprint = str_repeat('e',64);
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][310]['route_fingerprint'] = $fingerprint;
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][311]['route_fingerprint'] = $fingerprint;

    $api = new ECloudDokployClient($url, (string) getenv('ECLOUD_DOKPLOY_API_KEY'));

    assertion(
        eclouddokploy_prepare_private_project_locked($api, 310, 26, $fingerprint)
            !== 'success',
        'unrelated WHMCS customer cannot provision a private project'
    );
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 310, 25, str_repeat('d', 64))
            !== 'success',
        'changed WHMCS Dokploy server route is refused'
    );

    assertion(
        eclouddokploy_prepare_private_project_locked($api, 310, 25, $fingerprint)
            === 'success',
        'first paid account should create a Dokploy project and production environment'
    );
    $row = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][310];
    assertion($row['state'] === 'environment_ready', 'project ready before GitHub installation');
    assertion(is_string($row['project_id']) && is_string($row['environment_id'])
        && $row['project_id'] !== $row['environment_id'], 'both IDs persisted');
    $projects = $api->get('project.all');
    $matches = array_values(array_filter($projects, static fn($p) =>
        is_array($p) && ($p['name'] ?? '') === 'ec-client-25-service-310'
    ));
    assertion(count($matches) === 1, 'project visible in remote Dokploy project.all');
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 310, 25, $fingerprint)
            === 'success',
        'safe repeated WHMCS module Create does not create a new project'
    );
    $projects2 = $api->get('project.all');
    assertion(count($projects2) === count($projects), 'no duplicate project after retry');

    // In mock service 311 Dokploy does not make production automatically;
    // the module creates its private environment explicitly.
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 311, 25, $fingerprint)
            === 'success',
        'paid second service gets distinct project and environment'
    );
    $other = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][311];
    assertion($other['project_id'] !== $row['project_id'],
        'different services never share a Dokploy project');
    assertion($other['environment_id'] !== $row['environment_id'],
        'different services never share production environment');

    $unfinished = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][310];
    $unfinished['state'] = 'creating_project';
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][310] = $unfinished;
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 310, 25, $fingerprint)
            !== 'success',
        'ambiguous operation cannot be automatically retried'
    );
    echo "WHMCS paid account project+environment mock contracts passed\n";
}
