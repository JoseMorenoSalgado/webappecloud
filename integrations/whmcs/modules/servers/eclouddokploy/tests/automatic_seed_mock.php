<?php
declare(strict_types=1);

namespace Illuminate\Support\Facades {
    /**
     * Mock facade dispatch, not a real WHMCS credential/encryption key.
     * encryptString/decryptString intentionally do NOT exist as concrete
     * static methods: real Laravel Facades call through __callStatic.
     */
    class Crypt {
        public static function __callStatic(string $name, array $args) {
            $key = str_repeat("\x11", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            if ($name === 'encryptString') {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                return base64_encode($nonce . sodium_crypto_secretbox(
                    (string) $args[0], $nonce, $key
                ));
            }
            if ($name === 'decryptString') {
                $payload = base64_decode((string) $args[0], true);
                if ($payload === false) throw new \RuntimeException('Mock ciphertext invalid.');
                $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
                if ($plain === false) throw new \RuntimeException('Mock decrypt failed.');
                return $plain;
            }
            throw new \BadMethodCallException('Unsupported mock crypto method.');
        }
    }
}

namespace WHMCS\Database {
    class FakeSchema {
        public function hasTable(string $name): bool {
            return isset(Capsule::$tables[$name]);
        }
        public function create(string $name, callable $fn): void {
            Capsule::$tables[$name] = [];
            $fn(new class {
                public function unsignedInteger(string $name): self { return $this; }
                public function primary(): self { return $this; }
                public function text(string $name): self { return $this; }
                public function timestamps(): void {}
            });
        }
    }
    class FakeConnection {
        public function selectOne(string $sql, array $args): object {
            if (str_contains($sql, 'GET_LOCK')) return (object) ['acquired' => 1];
            return (object) ['released' => 1];
        }
    }
    class FakeBuilder {
        private string $table;
        private ?int $id = null;
        public function __construct(string $table) { $this->table = $table; }
        public function where(string $column, int $value): self {
            if ($column !== 'id') throw new \RuntimeException('Mock where key unsupported.');
            $this->id = $value;
            return $this;
        }
        public function first(array $fields): ?object {
            $row = Capsule::$tables[$this->table][$this->id ?? 0] ?? null;
            if (!$row) return null;
            return (object) array_intersect_key($row, array_fill_keys($fields, true));
        }
        public function insert(array $row): void {
            if (isset(Capsule::$tables[$this->table][$row['id']])) {
                throw new \RuntimeException('Duplicate seed row');
            }
            Capsule::$tables[$this->table][$row['id']] = $row;
        }
    }
    class Capsule {
        public static array $tables = [];
        public static function schema(): FakeSchema { return new FakeSchema(); }
        public static function connection(): FakeConnection { return new FakeConnection(); }
        public static function table(string $name): FakeBuilder { return new FakeBuilder($name); }
    }
}

namespace {
    define('WHMCS', true);
    require_once dirname(__DIR__) . '/eclouddokploy.php';

    function ensure(bool $ok, string $message): void {
        if (!$ok) throw new \RuntimeException('Auto seed mock failed: ' . $message);
    }

    putenv('ECLOUD_DOKPLOY_PASSWORD_SEED');
    $seed1 = eclouddokploy_auto_seed();
    ensure(eclouddokploy_auto_seed_valid($seed1), 'generated 256-bit random seed');
    $row = \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_runtime'][1] ?? [];
    ensure(isset($row['encrypted_seed']) && !str_contains($row['encrypted_seed'], $seed1),
        'master key is not saved in plaintext');
    $seed2 = eclouddokploy_auto_seed();
    ensure(hash_equals($seed1, $seed2), 'repeated calls reuse original seed');
    ensure(count(\WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_runtime']) === 1,
        'exactly one seed row');
    putenv('ECLOUD_DOKPLOY_PASSWORD_SEED=' . str_repeat('cd', 32));
    ensure(eclouddokploy_auto_seed() === str_repeat('cd', 32),
        'legacy deployment seed takes precedence');
    putenv('ECLOUD_DOKPLOY_PASSWORD_SEED');
    ensure(eclouddokploy_auto_seed() === $seed1, 'existing encrypted seed remains intact');
    \WHMCS\Database\Capsule::$tables['mod_ecloud_dokploy_runtime'][1]['encrypted_seed'] =
        'not-valid';
    try {
        eclouddokploy_auto_seed();
        throw new \RuntimeException('TEST_ERROR_UNEXPECTEDLY_REGENERATED');
    } catch (\Throwable $e) {
        ensure($e->getMessage() !== 'TEST_ERROR_UNEXPECTEDLY_REGENERATED',
            'tampering must fail rather than silently regenerate passwords');
    }
    echo "Automatic encrypted seed mock contracts passed\n";
}
