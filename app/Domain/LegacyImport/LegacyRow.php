<?php

namespace App\Domain\LegacyImport;

use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;

/**
 * A row read in the SQLite base of Next, each value read with the type its column has in the
 * Drizzle schema. SQLite keeps whatever it is given: a value of another type fails the import.
 */
final readonly class LegacyRow
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private string $table, private array $values) {}

    /**
     * @throws LegacyImportFailed
     */
    public function int(string $column): int
    {
        return $this->nullableInt($column) ?? throw $this->unreadable($column);
    }

    /**
     * @throws LegacyImportFailed
     */
    public function nullableInt(string $column): ?int
    {
        $value = $this->values[$column] ?? null;
        if ($value !== null && ! is_int($value)) {
            throw $this->unreadable($column);
        }

        return $value;
    }

    /**
     * @throws LegacyImportFailed
     */
    public function string(string $column): string
    {
        return $this->nullableString($column) ?? throw $this->unreadable($column);
    }

    /**
     * @throws LegacyImportFailed
     */
    public function nullableString(string $column): ?string
    {
        $value = $this->values[$column] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw $this->unreadable($column);
        }

        return $value;
    }

    private function unreadable(string $column): LegacyImportFailed
    {
        return new LegacyImportFailed("Valeur illisible : {$this->table}.{$column}");
    }
}
