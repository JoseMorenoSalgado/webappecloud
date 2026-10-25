<?php
declare(strict_types=1);

/** Isolated HTTPS mock contract test: never call a real Dokploy or WHMCS DB. */
define('WHMCS', true);
require_once dirname(__DIR__) . '/eclouddokploy.php';

function expect(bool $valid, string $label): void
{
    if (!$valid) {
        throw new RuntimeException('Mock contract test failed: ' . $label);
    }
}

$url = (string) getenv('ECLOUD_DOKPLOY_URL');
if ($url !== 'https://dokploy.test') {
    fwrite(STDERR, "Only the local .test TLS mock is permitted.\n");
    exit(2);
}
$api = new ECloudDokployClient($url, (string) getenv('ECLOUD_DOKPLOY_API_KEY'));

$projects = [];
$environments = [];
$applications = [];

foreach ([310 => 25, 311 => 25, 312 => 26] as $service => $client) {
    $created = $api->post('project.create', eclouddokploy_project_create_payload($client, $service));
    $project = eclouddokploy_required_id($created, 'projectId');
    expect(!in_array($project, $projects, true), 'unique project per client service');

    $listed = $api->get('environment.byProjectId', ['projectId' => $project]);
    $environment = eclouddokploy_find_production_environment($listed, $project);
    if ($environment === null) {
        $new = $api->post('environment.create', [
            'name' => 'production',
            'projectId' => $project,
        ]);
        $environment = eclouddokploy_required_id($new, 'environmentId');
    }

    $appResponse = $api->post('application.create',
        eclouddokploy_application_create_payload($environment, 'mockapp-' . $service, '')
    );
    $app = eclouddokploy_required_id($appResponse, 'applicationId');
    $remote = $api->get('application.one', ['applicationId' => $app]);
    expect(eclouddokploy_app_environment_matches($remote, $app, $environment),
        'new application belongs to private environment');
    expect(!in_array($environment, $environments, true),
        'private environment not reused by another service');

    $projects[$service] = $project;
    $environments[$service] = $environment;
    $applications[$service] = $app;
}
expect($projects[310] !== $projects[311], 'two services of same client have separate projects');
expect($projects[310] !== $projects[312], 'different clients have separate projects');
expect($environments[310] !== $environments[311], 'each project has independent production environment');
expect(!eclouddokploy_app_environment_matches(
    $api->get('application.one', ['applicationId' => $applications[311]]),
    $applications[311], $environments[310]
), 'cross-service application refused by environment check');

echo "Isolated projects HTTPS mock contracts passed\n";
