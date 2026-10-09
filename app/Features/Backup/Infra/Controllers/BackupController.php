<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Controllers;

use App\Features\Backup\App\Exceptions\ImportValidationException;
use App\Features\Backup\App\UseCases\CreateBackupUseCase;
use App\Features\Backup\App\UseCases\DestroyBackupUseCase;
use App\Features\Backup\App\UseCases\DownloadBackupUseCase;
use App\Features\Backup\App\UseCases\GetBackupListUseCase;
use App\Features\Backup\App\UseCases\ImportFromPathUseCase;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Infra\Jobs\RestoreBackupJob;
use App\Features\Backup\Infra\Requests\RestoreBackupRequest;
use App\Features\Backup\Infra\Requests\UploadRestoreRequest;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * The admin backup surface: the list page, create/dispatch, delete,
 * restore-submit, DR upload, download stream and the status polling
 * endpoint. All actions run behind the admin auth chain; the restore is
 * async — the controller only dispatches `RestoreBackupJob`, the UI
 * polls the status endpoint.
 */
final class BackupController
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly GetBackupListUseCase $listCase,
        private readonly CreateBackupUseCase $createCase,
        private readonly DestroyBackupUseCase $destroyCase,
        private readonly DownloadBackupUseCase $downloadCase,
        private readonly ImportFromPathUseCase $importCase,
        private readonly Filesystem $backupsDisk,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $list = $this->listCase->get((int) $request->query('page', 1));

        return $inertia->render('Backup/Admin/Pages/BackupListPage', [
            'backups' => array_map(self::entryProps(...), $list->backups),
            'meta' => [
                'current_page' => $list->currentPage,
                'from' => $list->from,
                'to' => $list->to,
                'last_page' => $list->lastPage,
                'per_page' => $list->perPage,
                'total' => $list->total,
            ],
            'encryption_key_prompt' => $list->encryptionKeyPrompt,
        ]);
    }

    public function create(): HttpResponse
    {
        $this->createCase->create();

        // The async create only STARTS here: the flash announces the
        // start, the UI fires the "Backup created." toast when its
        // status poll observes the completed record.
        return back()->with('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_creation_started')]);
    }

    public function destroy(int $backup): HttpResponse
    {
        $entity = $this->findBackup($backup);

        try {
            $this->destroyCase->destroy($entity);
        } catch (Throwable $exception) {
            return back()->with('notification', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_deleted')]);
    }

    public function restore(RestoreBackupRequest $request): HttpResponse
    {
        $entity = $this->findBackup((int) $request->validated('backup_id'));

        if (! $entity->canDownload()) {
            return back()->with('notification', [
                'type' => 'error',
                'message' => __('admin.toasts.backup_restore_failed'),
            ]);
        }

        Bus::dispatch(new RestoreBackupJob($entity->toId()));

        return back()->with('notification', [
            'type' => 'success',
            'message' => __('admin.toasts.backup_restore_started'),
        ]);
    }

    public function uploadRestore(UploadRestoreRequest $request): HttpResponse
    {
        $file = $request->file('file');

        try {
            $imported = $this->importCase->import($file->getPathname(), (string) $request->validated('key'));
        } catch (ImportValidationException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('admin.toasts.upload_failed')]);
        }

        Bus::dispatch(new RestoreBackupJob($imported->toId()));

        return redirect('/admin/backups')->with('notification', [
            'type' => 'success',
            'message' => __('admin.toasts.backup_restore_started'),
        ]);
    }

    public function download(int $backup): HttpResponse
    {
        $entity = $this->findBackup($backup);

        try {
            $path = $this->downloadCase->download($entity);
        } catch (Throwable) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $stream = $this->backupsDisk->readStream($path);

        if ($stream === false) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return response()->streamDownload(function () use ($stream): void {
            while (! feof($stream)) {
                echo fread($stream, 8192);
            }

            fclose($stream);
        }, $entity->toFilename(), ['Content-Type' => 'application/zip']);
    }

    public function status(int $backup): JsonResponse
    {
        $entity = $this->findBackup($backup);

        return response()->json([
            'data' => [
                'id' => $entity->toId(),
                'status' => $entity->toStatus()->value,
                'progress' => null,
                'completed_at' => $entity->toCompletedAt()?->format('c'),
                'error_message' => $entity->toStatus() === BackupStatus::Failed
                    ? Cache::get("backup:error:{$entity->toId()}")
                    : null,
            ],
        ]);
    }

    private function findBackup(int $id): Backup
    {
        $backup = $this->backups->find($id);

        if ($backup === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return $backup;
    }

    /**
     * The wire shape of one list row.
     *
     * @return array<string, mixed>
     */
    private static function entryProps(Backup $backup): array
    {
        return [
            'id' => $backup->toId(),
            'filename' => $backup->toFilename(),
            'size_bytes' => $backup->toSizeBytes() ?? 0,
            'status' => $backup->toStatus()->value,
            'created_at' => $backup->toCreatedAt()->format('c'),
        ];
    }
}
