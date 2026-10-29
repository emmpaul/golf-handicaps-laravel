<?php

namespace App\Domain\Imports\Queries;

use App\Domain\Imports\ImportStatus;
use App\Domain\LocalTime;
use App\Models\Import;

/**
 * The latest imports of the import screen (spec scorecard-import, « Liste des imports »), from
 * the most recent to the oldest, each with the actions its state allows.
 *
 * @phpstan-type ImportAction 'review'|'correctEmpty'|'retry'|'viewRound'|'delete'
 */
final class ImportList
{
    public const int LIMIT = 20;

    /**
     * @param  bool  $readingEnabled  an Anthropic key is configured: a failed import may be read again
     * @return list<array{id: string, status: string, label: string, failure: string|null, roundId: string|null, createdAt: string, actions: list<string>}>
     */
    public function get(bool $readingEnabled): array
    {
        return array_values(Import::query()
            ->latest()
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'status', 'failure', 'round_id', 'created_at'])
            ->map(fn (Import $import): array => [
                'id' => $import->id,
                'status' => $import->status->value,
                'label' => $import->status->label(),
                'failure' => $import->failure,
                'roundId' => $import->round_id,
                'createdAt' => LocalTime::format($import->created_at ?? now()),
                'actions' => self::actions($import, $readingEnabled),
            ])
            ->all());
    }

    /**
     * « Vérifier » for a ready import, « Ouvrir l'écran de correction vide » and « Relancer la
     * lecture » for a failed one, « Voir le round » once saved, « Supprimer » unless it is being
     * read or saved.
     *
     * @return list<ImportAction>
     */
    private static function actions(Import $import, bool $readingEnabled): array
    {
        if ($import->round_id !== null) {
            return ['viewRound'];
        }

        return match ($import->status) {
            ImportStatus::Queued => ['delete'],
            ImportStatus::Reading => [],
            ImportStatus::Ready => ['review', 'delete'],
            ImportStatus::Failed => ['correctEmpty', 'delete'],
        };
    }
}
