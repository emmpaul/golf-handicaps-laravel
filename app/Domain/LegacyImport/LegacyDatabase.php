<?php

namespace App\Domain\LegacyImport;

use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Pdo\Sqlite;

/**
 * The SQLite base of the Next application, opened read only on the `legacy` connection declared
 * on the fly (design D2): any write on it fails, and the file is never changed.
 */
final readonly class LegacyDatabase
{
    public const string CONNECTION = 'legacy';

    private function __construct(private Connection $connection) {}

    /**
     * @throws LegacyImportFailed when the file does not exist or cannot be read
     */
    public static function open(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new LegacyImportFailed("Base SQLite introuvable : {$path}");
        }

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
            'options' => [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY],
        ]]);
        DB::purge(self::CONNECTION);

        return new self(DB::connection(self::CONNECTION));
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return list<string> the tables of the base, internal tables of SQLite left out
     */
    public function tables(): array
    {
        /** @var list<string> $tables */
        $tables = $this->connection->table('sqlite_master')
            ->where('type', 'table')
            ->where('name', 'not like', 'sqlite\_%')
            ->pluck('name')
            ->all();

        return $tables;
    }

    /**
     * @return list<string> the columns of $table
     */
    public function columns(string $table): array
    {
        return array_values(array_map(
            fn (object $column): string => (string) ($column->name ?? ''),
            $this->connection->select('select name from pragma_table_info(?)', [$table]),
        ));
    }
}
