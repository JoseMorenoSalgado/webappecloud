<?php
declare(strict_types=1);

/**
 * Native WHMCS AdminServicesTabFieldsSave → Create Project → Install App
 * contract against local HTTPS Dokploy mock. No real WHMCS, DNS or Docker.
 */
namespace WHMCS\Database {
    final class FakeTableDefinition {
        public function unsignedInteger(string $name): self { return $this; }
        public function string(string $name, ?int $size = null): self { return $this; }
        public function text(string $name): self { return $this; }
        public function dateTime(string $name): self { return $this; }
        public function nullable(): self { return $this; }
        public function primary(): self { return $this; }
        public function default($value): self { return $this; }
        public function timestamps(): void {}
    }
    final class FakeSchema {
        public function hasTable(string $name): bool {
            return array_key_exists($name, Capsule::$tables);
        }
        public function hasColumn(string $table, string $column): bool {
            return $table === 'mod_ecloud_dokploy_services';
        }
        public function create(string $name, callable $fn): void {
            if ($this->hasTable($name)) throw new \RuntimeException('Duplicate mock table');
            Capsule::$tables[$name] = [];
            $fn(new FakeTableDefinition());
        }
        public function table(string $name, callable $fn): void {
            $fn(new FakeTableDefinition());
        }
    }
    final class FakeQuery {
        private string $table;
        private array $filters = [];
        private array $inFilters = [];
        public function __construct(string $table) { $this->table = $table; }
        public function where(string $field, $operator, $value = null): self {
            $this->filters[$field] = func_num_args() === 3
                ? [$operator, $value] : ['=', $operator];
            return $this;
        }
        public function whereIn(string $field, array $values): self {
            $this->inFilters[$field] = $values;
            return $this;
        }
        private function indexes(): array {
            $list = [];
            foreach (Capsule::$tables[$this->table] ?? [] as $id => $row) {
                $matches = true;
                foreach ($this->filters as $field => [$operator, $value]) {
                    if (!array_key_exists($field, $row)) { $matches=false; break; }
                    if ($operator === '=' && (string) $row[$field] !== (string) $value) {
                        $matches = false; break;
                    }
                    if ($operator === '>' && (int) $row[$field] <= (int) $value) {
                        $matches = false; break;
                    }
                }
                foreach ($this->inFilters as $field => $values) {
                    if (!in_array($row[$field] ?? null, $values, true)) $matches = false;
                }
                if ($matches) $list[] = $id;
            }
            return $list;
        }
        public function first(array $columns = []): ?object {
            $indexes = $this->indexes();
            if (!$indexes) return null;
            $row = Capsule::$tables[$this->table][$indexes[0]];
            if ($columns) $row = array_intersect_key($row, array_fill_keys($columns, true));
            return (object) $row;
        }
        public function count(): int { return count($this->indexes()); }
        public function insert(array $row): void {
            $id = $row['service_id'] ?? $row['id'] ?? count(Capsule::$tables[$this->table]) + 1;
            if (isset(Capsule::$tables[$this->table][$id])) {
                throw new \RuntimeException('Duplicate mock row');
            }
            Capsule::$tables[$this->table][$id] = $row;
        }
        public function update(array $changes): int {
            $indexes = $this->indexes();
            foreach ($indexes as $id) {
                Capsule::$tables[$this->table][$id] = array_merge(
                    Capsule::$tables[$this->table][$id], $changes
                );
            }
            return count($indexes);
        }
        public function updateOrInsert(array $key, array $fields): void {
            foreach (Capsule::$tables[$this->table] ?? [] as $id => $row) {
                $matches = true;
                foreach ($key as $field => $value) {
                    if (($row[$field] ?? null) !== $value) { $matches=false; break; }
                }
                if ($matches) {
                    Capsule::$tables[$this->table][$id] = array_merge($row, $fields);
                    return;
                }
            }
            $this->insert($key + $fields);
        }
    }
    final class FakeConnection {
        public function table(string $table): FakeQuery { return new FakeQuery($table); }
        public function selectOne(string $sql, array $params): object {
            if (str_contains($sql, 'GET_LOCK')) return (object) ['acquired'=>1];
            if (str_contains($sql, 'RELEASE_LOCK')) return (object) ['released'=>1];
            throw new \RuntimeException('Unknown mock SQL operation');
        }
    }
    final class Capsule {
        public static array $tables = [
            'tblhosting' => [
                510 => [
                    'id'=>510,'userid'=>25,'orderid'=>101,'server'=>1,
                    'packageid'=>12,'domain'=>'app-510.example.test',
                    'domainstatus'=>'Active',
                ],
                511 => [
                    'id'=>511,'userid'=>26,'orderid'=>102,'server'=>1,
                    'packageid'=>12,'domain'=>'app-511.example.test',
                    'domainstatus'=>'Active',
                ],
            ],
            'tblorders' => [
                101 => ['id'=>101, 'invoiceid'=>501],
                102 => ['id'=>102, 'invoiceid'=>502],
            ],
            'tblinvoices' => [
                501 => ['id'=>501,'userid'=>25,'status'=>'Paid'],
                502 => ['id'=>502,'userid'=>26,'status'=>'Unpaid'],
            ],
            'tblservers' => [
                1 => ['id'=>1,'maxaccounts'=>10],
            ],
            'mod_ecloud_dokploy_services' => [],
        ];
        public static function schema(): FakeSchema { return new FakeSchema(); }
        public static function table(string $name): FakeQuery { return new FakeQuery($name); }
        public static function connection(): FakeConnection { return new FakeConnection(); }
    }
}

namespace {
    define('WHMCS', true);
    $GLOBALS['admin_audit_events'] = [];
    function logActivity(string $description, int $clientId): void {
        $GLOBALS['admin_audit_events'][] = [
            'description' => $description,
            'client_id' => $clientId,
        ];
    }
    require_once dirname(__DIR__) . '/eclouddokploy.php';

    function must(bool $value, string $name): void {
        if (!$value) throw new \RuntimeException('Admin HTTPS support test failed: ' . $name);
    }

    if ((string) getenv('ECLOUD_DOKPLOY_URL') !== 'https://dokploy.test'
        || (string) getenv('ECLOUD_DOKPLOY_API_KEY') !== 'test-key-very-long') {
        throw new \RuntimeException('Admin support integration tests are local TLS mock only.');
    }

    putenv('ECLOUD_DOKPLOY_PASSWORD_SEED=' . str_repeat('a1', 32));
    $_SESSION['adminid'] = 9;
    $_SESSION['uid'] = 0;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $params = [
        'serviceid'=>510, 'userid'=>25, 'pid'=>12, 'serverid'=>1,
        'domain'=>'app-510.example.test', 'serverhostname'=>'dokploy.test',
        'serverpassword'=>'test-key-very-long', 'serverusername'=>'',
        'configoption5'=>'none', 'configoption6'=>'guiado',
    ];
    $paramsUnpaid = array_replace($params, [
        'serviceid'=>511, 'userid'=>26,'domain'=>'app-511.example.test',
    ]);

    // No customer impersonation, no first-invoice bypass.
    $_SESSION['adminid'] = 0;
    must(eclouddokploy_AdminCreateProject($params) !== 'success',
        'unauthenticated project creation refused');
    $_SESSION['adminid'] = 9;
    must(eclouddokploy_AdminCreateProject($paramsUnpaid) !== 'success',
        'unpaid second account cannot create a Dokploy project');

    $before = eclouddokploy_AdminServicesTabFields($params);
    must(isset($before['Repositorio GitHub del cliente']),
        'WHMCS displays native support source editor');
    must(!str_contains(implode(' ', $before), 'test-key-very-long'),
        'Dokploy API key never shown in admin support tab');

    $_POST['modulefields'] = [
        0 => 'https://github.com/example/client-510.git',
        1 => 'main',
        2 => '/',
        3 => 'nextjs',
        4 => '3000',
        5 => 'MY_PUBLIC_LABEL=ManagedBySupport',
        6 => '510',
    ];
    eclouddokploy_AdminServicesTabFieldsSave($params);
    $draft = eclouddokploy_admin_draft(510);
    must($draft !== null && $draft['git_url'] === 'https://github.com/example/client-510.git',
        'admin saves service-specific Git source in private database table');
    must(!str_contains((string) $draft['env_ciphertext'], 'ManagedBySupport'),
        'admin environment values encrypted before storage');

    must(eclouddokploy_AdminCreateProject($params) === 'success',
        'native admin module command creates paid private project');
    $state = eclouddokploy_admin_state(510);
    must($state !== null && $state->state === 'environment_ready'
        && is_string($state->project_id) && is_string($state->environment_id),
        'support project ID and production environment persisted');
    must(eclouddokploy_AdminCreateProject($params) === 'success',
        'admin project creation is idempotent');

    $api = new ECloudDokployClient('https://dokploy.test', 'test-key-very-long');
    $projectsBefore = $api->get('project.all', []);
    $matches = array_values(array_filter($projectsBefore, static fn($p) =>
        is_array($p) && ($p['name'] ?? '') === 'ec-client-25-service-510'
    ));
    must(count($matches) === 1, 'project visible to Dokploy API after admin action');

    must(eclouddokploy_AdminInstallApp($params) === 'success',
        'support installs client app without impersonation');
    $deployed = eclouddokploy_admin_state(510);
    must($deployed !== null && $deployed->state === 'deployment_requested'
        && !empty($deployed->application_id),
        'support install requests application deploy using SAME project');

    $remote = $api->get('application.one', [
        'applicationId'=>(string) $deployed->application_id,
    ]);
    must(($remote['environmentId'] ?? '') === $state->environment_id,
        'application belongs to paid customers private project environment');
    must(($remote['applicationStatus'] ?? '') === 'running',
        'mock accepts GitHub+Nixpacks+environment+domain and starts deploy');

    $projectsAfter = $api->get('project.all', []);
    must(count($projectsBefore) === count($projectsAfter),
        'admin installation did not create a duplicate project');
    must(eclouddokploy_AdminInstallApp($params) !== 'success',
        'support cannot blindly duplicate an already requested deployment');
    must(!eclouddokploy_admin_can_edit(510, eclouddokploy_admin_state(510)),
        'Git locked after deploy');
    $after = eclouddokploy_AdminServicesTabFields($params);
    must(str_contains($after['Repositorio GitHub del cliente'], 'disabled'),
        'support form rendered read-only after provisioning starts');

    must(count($GLOBALS['admin_audit_events']) >= 3,
        'WHMCS administrator save, create and install actions are auditable');
    foreach ($GLOBALS['admin_audit_events'] as $event) {
        must($event['client_id'] === 25, 'admin logs scoped to owning client');
        must(!str_contains($event['description'], 'ManagedBySupport')
            && !str_contains($event['description'], 'test-key-very-long')
            && !str_contains($event['description'], 'client-510.git'),
            'audit trail never contains source URLs, API token or env values');
    }
    echo "WHMCS admin assisted installation over TLS mock passed\n";
}
