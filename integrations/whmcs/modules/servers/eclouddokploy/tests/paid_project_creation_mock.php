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
                410 => [
                    'service_id'=>410, 'owner_client_id'=>25,
                    'state'=>'awaiting_setup', 'route_fingerprint'=>null,
                    'project_id'=>null, 'environment_id'=>null,
                ],
                411 => [
                    'service_id'=>411, 'owner_client_id'=>25,
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
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][410]['route_fingerprint'] = $fingerprint;
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][411]['route_fingerprint'] = $fingerprint;

    $api = new ECloudDokployClient($url, (string) getenv('ECLOUD_DOKPLOY_API_KEY'));

    assertion(
        eclouddokploy_prepare_private_project_locked($api, 410, 26, $fingerprint)
            !== 'success',
        'unrelated WHMCS customer cannot provision a private project'
    );
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 410, 25, str_repeat('d', 64))
            !== 'success',
        'changed WHMCS Dokploy server route is refused'
    );

    assertion(
        eclouddokploy_prepare_private_project_locked($api, 410, 25, $fingerprint)
            === 'success',
        'first paid account should create a Dokploy project and production environment'
    );
    $row = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][410];
    assertion($row['state'] === 'environment_ready', 'project ready before GitHub installation');
    assertion(is_string($row['project_id']) && is_string($row['environment_id'])
        && $row['project_id'] !== $row['environment_id'], 'both IDs persisted');
    $projects = $api->get('project.all', []);
    $matches = array_values(array_filter($projects, static fn($p) =>
        is_array($p) && ($p['name'] ?? '') === 'ec-client-25-service-410'
    ));
    assertion(count($matches) === 1, 'project visible in remote Dokploy project.all');
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 410, 25, $fingerprint)
            === 'success',
        'safe repeated WHMCS module Create does not create a new project'
    );
    $projects2 = $api->get('project.all', []);
    assertion(count($projects2) === count($projects), 'no duplicate project after retry');

    // Dokploy v0.30.9 creates production automatically for normal projects.
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 411, 25, $fingerprint)
            === 'success',
        'paid second service gets distinct project and environment'
    );
    $other = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][411];
    assertion($other['project_id'] !== $row['project_id'],
        'different services never share a Dokploy project');
    assertion($other['environment_id'] !== $row['environment_id'],
        'different services never share production environment');

    // Interrupted project.create: the remote project does exist. Reconcile
    // only that project's exact name; do not make a second POST.
    $unfinished = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][410];
    $unfinished['state'] = 'creating_project';
    $unfinished['project_id'] = null;
    $unfinished['environment_id'] = null;
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][410] = $unfinished;
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 410, 25, $fingerprint)
            === eclouddokploy_import_question(),
        'existing project requires explicit yes rather than automatic import'
    );
    assertion(
        eclouddokploy_import_existing_locked($api, 410, 25, $fingerprint, $row['project_id'])
            === 'success',
        'explicit admin confirmation restores project and production ID'
    );
    assertion(
        count($api->get('project.all', [])) === count($projects2) + 1,
        'lost response recovery did not create duplicate project'
    );

    // Incomplete local environment ID: verify existing production, no new write.
    $existingEnv = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][411];
    $existingEnv['state'] = 'creating_environment';
    $existingEnv['environment_id'] = null;
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][411] = $existingEnv;
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 411, 25, $fingerprint)
            === 'success',
        'environment.create lost response safely recovered by project-scoped production read'
    );
    assertion(
        \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][411]['environment_id']
            === $other['environment_id'],
        'recovered exact prior production environment identifier'
    );

    // Previous WHMCS modules could leave state="new" even though a valid
    // service was reserved: this MUST be treated as a fresh create state.
    foreach ([412, 413, 414, 415] as $sid) {
        \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][$sid] = [
            'service_id'=>$sid,'owner_client_id'=>25,'route_fingerprint'=>$fingerprint,
            'state'=>'new','project_id'=>null,'environment_id'=>null,
        ];
    }
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 412, 25, $fingerprint)
            === 'success',
        'legacy state new now creates project and production environment'
    );

    // In creating_project with nothing remote, a second POST is unsafe:
    // preserve state; explain how support must reconcile.
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][413]['state'] =
        'creating_project';
    $projectCount = count($api->get('project.all', []));
    $noRemote = eclouddokploy_prepare_private_project_locked(
        $api, 413, 25, $fingerprint
    );
    assertion(str_contains($noRemote, 'No encontramos el proyecto pendiente'),
        'unknown outcome remains blocked when project list has no exact match');
    assertion(count($api->get('project.all', [])) === $projectCount,
        'unknown project creation did not issue another project.create');

    // Dokploy accepted project.create but the HTTP response was lost.
    $precreated = $api->post('project.create',
        eclouddokploy_project_create_payload(25, 414)
    );
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][414]['state'] =
        'creating_project';
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 414, 25, $fingerprint)
            === eclouddokploy_import_question(),
        'existing remote project requests import confirmation, does not auto-adopt'
    );
    assertion(
        eclouddokploy_import_existing_locked(
            $api, 414, 25, $fingerprint, $precreated['project']['projectId']
        ) === 'success',
        'only explicit import adopts project and production IDs'
    );
    assertion(
        \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][414]['project_id']
            === $precreated['project']['projectId'],
        'existing remote project ID preserved after reconciliation'
    );

    // Dokploy accepted project.create without producing an environment.
    // Preserve the uncertain environment state and never POST automatically.
    $p415 = $api->post('project.create',
        eclouddokploy_project_create_payload(25, 415)
    );
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][415]['state'] =
        'creating_environment';
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][415]['project_id'] =
        $p415['project']['projectId'];
    assertion(str_contains(
        eclouddokploy_prepare_private_project_locked($api, 415, 25, $fingerprint),
        'entorno production no aparece'),
        'uncertain environment POST never retried without confirming remote ID'
    );
    $createdEnv = $api->post('environment.create', [
        'name'=>'production', 'projectId'=>$p415['project']['projectId']
    ]);
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 415, 25, $fingerprint)
            === 'success',
        'pending environment safely adopted after it appears remotely'
    );
    assertion(
        \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][415]['environment_id']
            === $createdEnv['environmentId'],
        'reconciled production ID saved'
    );

    // Pure read-only selector refuses duplicates, mismatched binding and
    // invalid identifiers even if the project name is exactly the same.
    $name = eclouddokploy_project_name(25, 999);
    foreach ([
        [['name'=>$name, 'projectId'=>'one123'],
         ['name'=>$name, 'projectId'=>'two456']],
        [['name'=>$name, 'projectId'=>'valid-123',
          'description'=>'Another WHMCS service']],
        [['name'=>$name, 'projectId'=>'!bad!']],
    ] as $listing) {
        try {
            eclouddokploy_find_exact_service_project($listing, 25, 999);
            throw new \RuntimeException('TEST_UNSAFE_PROJECT_WAS_ADOPTED');
        } catch (\RuntimeException $e) {
            assertion($e->getMessage() !== 'TEST_UNSAFE_PROJECT_WAS_ADOPTED',
                'unsafe project adoption rejected');
        }
    }
    assertion(eclouddokploy_find_exact_service_project(
        [['name'=>'unrelated', 'projectId'=>'valid-123']], 25, 999
    ) === null, 'unrelated project cannot be adopted');
    // Exact scenario photographed in Dokploy:
    // ec-client-225-service-293 / production already exists before WHMCS
    // knows its projectId. Recover both remote IDs, no second creation.
    $existing293 = $api->post(
        'project.create', eclouddokploy_project_create_payload(225, 293)
    );
    $real293 = $existing293['project']['projectId'];
    $production293 = eclouddokploy_find_production_environment(
        $api->get('environment.byProjectId', ['projectId' => $real293]),
        $real293
    );
    assertion(is_string($production293), 'project 225/293 already has production');
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293] = [
        'service_id'=>293, 'owner_client_id'=>225,
        'route_fingerprint'=>$fingerprint,
        'state'=>'awaiting_setup', 'project_id'=>null, 'environment_id'=>null,
    ];
    // The lightweight search API (not fragile project.all) must find the
    // pre-existing remote object by the exact client/service name.
    $small = $api->get('project.search', [
        'name'=>'ec-client-225-service-293', 'limit'=>100, 'offset'=>0,
    ]);
    assertion(isset($small['items'], $small['total']) && $small['total'] >= 1,
        'Dokploy returns documented compact project.search items and total');
    assertion(
        eclouddokploy_lookup_service_project($api, 225, 293) === $real293,
        'exact 225/293 project recovered using project.search, not project.all'
    );
    assertion(
        eclouddokploy_lookup_service_project($api, 225, 295) === null,
        'search verifies absent project without creating anything'
    );
    $diagnostic = eclouddokploy_safe_project_diagnostic(
        new RuntimeException('Dokploy request failed: project.search (HTTP 500).')
    );
    assertion(str_contains($diagnostic, 'HTTP 500')
        && str_contains($diagnostic, 'project.search'),
        'safe admin error exposes API endpoint/status, not generic error');
    $readDiagnostic = eclouddokploy_safe_project_diagnostic(
        new RuntimeException('Dokploy read failed: project.search (HTTP 500).')
    );
    assertion(str_contains($readDiagnostic, 'HTTP 500')
        && str_contains($readDiagnostic, 'project.search'),
        'GET read failure surfaces endpoint and HTTP status rather than generic error');
    $curlDiagnostic = eclouddokploy_safe_project_diagnostic(
        new RuntimeException('Dokploy transport failed (cURL 60).')
    );
    assertion(str_contains($curlDiagnostic, 'cURL 60')
        && str_contains($curlDiagnostic, 'TLS'),
        'TLS certificate transport failures are actionable');
    $authDiagnostic = eclouddokploy_safe_project_diagnostic(
        new RuntimeException('Dokploy request failed: project.search (HTTP 403).')
    );
    assertion(str_contains($authDiagnostic, 'permisos'),
        'safe admin message identifies API permission denial');
    $scrubbed = eclouddokploy_safe_project_diagnostic(
        new RuntimeException('SQL secret=mysql://private:password@host/db')
    );
    assertion(!str_contains($scrubbed, 'private') && !str_contains($scrubbed, 'password'),
        'hidden SQL and raw exception details never printed to WHMCS');
    $before293 = count($api->get('project.all', []));
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 293, 225, $fingerprint)
            === eclouddokploy_import_question(),
        'project 225/293 detected: asks if administrator wants to import it'
    );
    assertion(
        eclouddokploy_import_existing_locked($api, 293, 225, $fingerprint, $real293)
            === 'success',
        'administrator explicitly imports existing project 225/293'
    );
    $recovered293 = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293];
    assertion($recovered293['project_id'] === $real293
        && $recovered293['environment_id'] === $production293
        && $recovered293['state'] === 'environment_ready',
        'both Dokploy identifiers now registered against service 293');
    assertion(count($api->get('project.all', [])) === $before293,
        'exact existing project was adopted, not duplicated');
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 293, 225, $fingerprint)
            === 'success',
        'same WHMCS service checks existing project IDs rather than blindly succeeding'
    );
    // State may be environment_ready but one of the IDs was never persisted.
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293]['project_id'] = null;
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293]['environment_id'] = null;
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 293, 225, $fingerprint)
            === eclouddokploy_import_question(),
        'lost project ID prompts again rather than silently linking it'
    );
    assertion(
        eclouddokploy_import_existing_locked($api, 293, 225, $fingerprint, $real293)
            === 'success',
        'confirmation restores missing identifiers in ready state'
    );
    $fixed293 = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293];
    assertion($fixed293['project_id'] === $real293
        && $fixed293['environment_id'] === $production293,
        'ready state restored both IDs from authoritative API');

    // A stale WHMCS ID cannot be silently swapped for the real remote ID.
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293]['project_id'] = 'other-project-id';
    $mismatch293 = eclouddokploy_prepare_private_project_locked(
        $api, 293, 225, $fingerprint
    );
    assertion($mismatch293 === eclouddokploy_import_question(),
        'old mismatched project ID demands confirmation, not automatic reassignment');
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293]['project_id'] = $real293;
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293]['environment_id'] = 'other-env-id';
    $envMismatch = eclouddokploy_prepare_private_project_locked(
        $api, 293, 225, $fingerprint
    );
    assertion(str_contains($envMismatch, 'no coincide'),
        'mismatched production ID rejected; no cross-tenant environment');
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][293]['environment_id'] = $production293;

    // A supposedly ready service whose remote project disappeared is NOT
    // success and must not generate a replacement project automatically.
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_services'][294] = [
        'service_id'=>294, 'owner_client_id'=>225,
        'route_fingerprint'=>$fingerprint,'state'=>'environment_ready',
        'project_id'=>'unknown-project-id','environment_id'=>'unknown-production',
    ];
    $countBeforeStale = count($api->get('project.all', []));
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 294, 225, $fingerprint)
            !== 'success',
        'stale ID verified remotely and flagged instead of falsely returning success'
    );
    assertion(count($api->get('project.all', [])) === $countBeforeStale,
        'stale WHMCS resource not recreated or overwritten');
    assertion(
        eclouddokploy_prepare_private_project_locked($api, 293, 224, $fingerprint)
            !== 'success',
        'same numeric service ID may never be adopted for a different client'
    );
    assertion(count($api->get('project.all', [])) === $before293,
        'all 225/293 reconciliation checks performed read-only');

    echo "WHMCS paid account creation and safe reconciliation mock contracts passed\n";
}
