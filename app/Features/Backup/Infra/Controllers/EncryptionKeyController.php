<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Controllers;

use App\Features\Backup\App\UseCases\DestroyEncryptionKeyUseCase;
use App\Features\Backup\App\UseCases\GenerateEncryptionKeyUseCase;
use App\Features\Backup\App\UseCases\ListEncryptionKeysUseCase;
use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * The encryption-key surface: the JSON list (with the per-key backup
 * reference counts), the generation endpoint — the raw key value is
 * returned exactly once in the response, the UI displays it in the
 * show-once modal — and the guarded delete.
 */
final class EncryptionKeyController
{
    public function __construct(
        private readonly ListEncryptionKeysUseCase $listCase,
        private readonly GenerateEncryptionKeyUseCase $generateCase,
        private readonly DestroyEncryptionKeyUseCase $destroyCase,
        private readonly EncryptionKeyRepositoryInterface $keys,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'created_at' => $item->createdAt->format('c'),
                    'is_active' => $item->isActive,
                    'backups_count' => $item->backupsCount,
                ],
                $this->listCase->list(),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('backup_encryption_keys', 'name')],
        ]);

        $key = $this->generateCase->generate((string) $validated['name']);

        return response()->json([
            'data' => self::keyProps($key),
        ], HttpResponse::HTTP_CREATED);
    }

    public function destroy(int $key): JsonResponse
    {
        $entity = $this->findKey($key);

        try {
            $this->destroyCase->destroy($entity);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['key' => $exception->getMessage()]);
        }

        return response()->json(null, HttpResponse::HTTP_NO_CONTENT);
    }

    private function findKey(int $id): EncryptionKey
    {
        $key = $this->keys->find($id);

        if ($key === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return $key;
    }

    /**
     * The store-response wire shape — includes the raw key value for
     * the show-once modal.
     *
     * @return array<string, mixed>
     */
    private static function keyProps(EncryptionKey $key): array
    {
        return [
            'id' => $key->toId(),
            'name' => $key->toName(),
            'key_value' => $key->toKeyValue(),
            'is_active' => $key->isActive(),
            'created_at' => $key->toCreatedAt()->format('c'),
        ];
    }
}
