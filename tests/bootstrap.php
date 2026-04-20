<?php

use App\Kernel;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform as LegacySQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Let PHPUnit double the numerous final Symfonicat services (DomainService,
// ProjectService, RoutingRuleService, ...). dg/bypass-finals is a dev-only
// dep that strips the `final` keyword at autoload time; production bytecode
// is untouched.
if (class_exists(\DG\BypassFinals::class)) {
    \DG\BypassFinals::enable();
}

// compose.yaml and typical development shells export DATABASE_URL / REDIS_URL
// pointing at the real Dockerized Postgres + Redis. PHP's Dotenv won't
// override already-set process env vars, so we unset the subset the test env
// is expected to own before loading .env / .env.test / .env.test.local.
// APP_ENV is intentionally preserved (phpunit.xml.dist forces it to "test").
foreach ([
    'DATABASE_URL',
    'REDIS_URL',
    'MESSENGER_TRANSPORT_DSN',
    'MESSENGER_FAILED_TRANSPORT_DSN',
    'MESSENGER_CONSUMER_NAME',
] as $testOwnedVar) {
    unset($_ENV[$testOwnedVar], $_SERVER[$testOwnedVar]);
    putenv($testOwnedVar);
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// WebTestCase requests that render the public layout reach into
// encore_entry_*('symfonicat'), which blows up unless the Webpack manifest
// exists. The test suite stubs an empty entrypoints/manifest pair rather than
// requiring `npm run dev` before phpunit. Only written if absent, so a real
// build (if one was produced) is preserved.
(static function (): void {
    $buildDir = dirname(__DIR__).'/public/build';
    if (!is_dir($buildDir) && !@mkdir($buildDir, 0755, true) && !is_dir($buildDir)) {
        return;
    }

    $entrypoints = $buildDir.'/entrypoints.json';
    if (!is_file($entrypoints)) {
        file_put_contents($entrypoints, json_encode(
            ['entrypoints' => (object) []],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));
    }

    $manifest = $buildDir.'/manifest.json';
    if (!is_file($manifest)) {
        file_put_contents($manifest, json_encode((object) [], JSON_PRETTY_PRINT));
    }
})();

// Drop and rebuild the SQLite schema once per phpunit invocation. Individual
// tests are responsible for seeding (and for truncating between tests via
// App\Tests\Support\SymfonicatTestCase), but the empty schema must exist
// before the first kernel boot.
(static function (): void {
    $kernel = new Kernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    $kernel->boot();

    $container = $kernel->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $connection = $entityManager->getConnection();

    $platform = $connection->getDatabasePlatform();
    $isSqlite = $platform instanceof SQLitePlatform
        || (class_exists(LegacySQLitePlatform::class) && $platform instanceof LegacySQLitePlatform);

    if ($isSqlite) {
        $params = $connection->getParams();
        $path = $params['path'] ?? $params['dbname'] ?? null;
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
    if ($metadata !== []) {
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }

    $kernel->shutdown();
})();
