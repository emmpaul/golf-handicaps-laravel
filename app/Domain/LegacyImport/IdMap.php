<?php

namespace App\Domain\LegacyImport;

use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use Illuminate\Support\Str;

/**
 * The UUID given to each SQLite id, table by table, kept in memory for the time of the command
 * and never in the base (decision 6 of the port). The UUIDs of a table follow the order of the
 * SQLite ids (design D5): two rows created in the same second keep the order they had in Next.
 */
final class IdMap
{
    /** @var array<string, array<int, string>> */
    private array $uuids = [];

    /**
     * Gives a new UUID v7 to each id, sorted UUIDs to ascending ids.
     *
     * @param  list<int>  $ids
     */
    public function assign(string $table, array $ids): void
    {
        sort($ids);

        $uuids = array_map(fn (): string => (string) Str::uuid7(), $ids);
        // Byte order, the order of PostgreSQL on uuid: lowercase hexadecimal sorts the same.
        sort($uuids, SORT_STRING);

        $this->uuids[$table] = array_combine($ids, $uuids);
    }

    /**
     * The UUID of the SQLite id of $table.
     *
     * @param  string  $reference  how the failure names the row that holds the reference
     *
     * @throws LegacyImportFailed when the id has no row in $table
     */
    public function uuid(string $table, int $id, string $reference): string
    {
        return $this->uuids[$table][$id] ?? throw new LegacyImportFailed("Référence absente : {$reference}");
    }
}
