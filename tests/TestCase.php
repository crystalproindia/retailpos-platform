<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->assertIsolatedTestEnvironment();

        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($this->isMariaDbQualificationRun()) {
            $connectionConfig = config("database.connections.{$connection}");

            if ($connection !== 'mysql'
                || ($connectionConfig['host'] ?? null) !== '127.0.0.1'
                || (string) ($connectionConfig['port'] ?? null) !== '33077'
                || $database !== 'retailpos_qualification_regression') {
                throw new LogicException('MariaDB qualification tests must use the loopback-only retailpos_qualification_regression database.');
            }
        } elseif ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new LogicException('Tests must run in the testing environment against the isolated SQLite in-memory database.');
        }

        $this->withoutVite();
    }

    private function assertIsolatedTestEnvironment(): void
    {
        if (($this->environmentValue('APP_ENV') ?? null) !== 'testing') {
            throw new LogicException('Tests must run with APP_ENV=testing.');
        }

        if ($this->isMariaDbQualificationRun()) {
            if (($this->environmentValue('DB_CONNECTION') ?? null) !== 'mysql'
                || ($this->environmentValue('DB_HOST') ?? null) !== '127.0.0.1'
                || (string) ($this->environmentValue('DB_PORT') ?? null) !== '33077'
                || ($this->environmentValue('DB_DATABASE') ?? null) !== 'retailpos_qualification_regression') {
                throw new LogicException('MariaDB qualification requires the explicit loopback-only disposable database configuration.');
            }

            return;
        }

        if (($this->environmentValue('DB_CONNECTION') ?? null) !== 'sqlite'
            || ($this->environmentValue('DB_DATABASE') ?? null) !== ':memory:') {
            throw new LogicException('Tests must use the isolated SQLite in-memory database.');
        }
    }

    private function isMariaDbQualificationRun(): bool
    {
        return ($this->environmentValue('RETAILPOS_MARIADB_QUALIFICATION') ?? null) === '1';
    }

    private function environmentValue(string $key): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    }
}
