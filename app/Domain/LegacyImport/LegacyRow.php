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

    /**
     * A column of JSON text, decoded then encoded again for a jsonb column: a text that is not
     * JSON fails the import before PostgreSQL sees it.
     *
     * @throws LegacyImportFailed
     */
    public function nullableJson(string $column): ?string
    {
        $json = $this->nullableString($column);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw $this->unreadable($column);
        }

        return (string) json_encode($decoded);
    }

    private function unreadable(string $column): LegacyImportFailed
    {
        return new LegacyImportFailed("Valeur illisible : {$this->table}.{$column}");
    }
}
