<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Portfolio\Infra\Mappers\ContactMapper;
use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed contact repository. Writes go through a transaction
 * with the translation sync; `reorder()` applies the zero-indexed
 * positions with a single batched update (A4, content-management.md:155
 * style) — no per-row queries.
 */
final class EloquentContactRepository implements ContactRepositoryInterface, ContactListQueryRepositoryInterface
{
    public function __construct(
        private readonly ContactMapper $mapper,
        private readonly EloquentContactTranslationRepository $translations,
    ) {
    }

    public function findAll(): array
    {
        $models = ContactModel::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->hydrateAll($models);
    }

    public function findById(int $id): ?Contact
    {
        $model = ContactModel::query()->find($id);

        return $model === null
            ? null
            : $this->hydrate($model);
    }

    public function save(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $model = ContactModel::query()->updateOrCreate(
                ['id' => $contact->toId()],
                $this->mapper->toRow($contact),
            );

            $this->translations->syncForContact($model->id, $contact->toTranslations());
        });
    }

    public function delete(int $id): void
    {
        ContactModel::query()->whereKey($id)->delete();
    }

    public function reorder(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            $ids = array_values(array_map('intval', $ids));

            // Single batched update — no per-row N+1 writes. The CASE
            // operands are validated integers interpolated directly into
            // the expression (DB::raw carries no bindings).
            $cases = [];
            foreach ($ids as $position => $id) {
                $cases[] = sprintf('WHEN %d THEN %d', $id, $position);
            }

            DB::table('contacts')
                ->whereIn('id', $ids)
                ->update([
                    'sort_order' => DB::raw('CASE id '.implode(' ', $cases).' END'),
                ]);
        });
    }

    public function listQuery(): Builder
    {
        return ContactModel::query()->with('translations');
    }

    /**
     * @param  EloquentCollection<int, ContactModel>  $models
     * @return Contact[]
     */
    private function hydrateAll($models): array
    {
        $translations = $this->translations->findForContacts($models->pluck('id')->all());

        return $models
            ->map(fn (ContactModel $model): Contact => $this->hydrate($model, $translations))
            ->all();
    }

    /**
     * @param  EloquentCollection<int, ContactTranslationModel>|null  $allTranslations  Pre-loaded rows for batch hydration.
     */
    private function hydrate(ContactModel $model, ?EloquentCollection $allTranslations = null): Contact
    {
        if ($allTranslations === null) {
            return $this->mapper->toEntity($model);
        }

        $rows = $allTranslations->where('contact_id', $model->id)->values();

        return $this->mapper->toEntity($model->setRelation('translations', $rows));
    }
}
