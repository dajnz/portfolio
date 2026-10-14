<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Controllers;

use App\Features\Backup\App\Exceptions\ImportValidationException;
use App\Features\Backup\App\UseCases\CreateBackupUseCase;
use App\Features\Backup\App\UseCases\DestroyBackupUseCase;
use App\Features\Backup\App\UseCases\DownloadBackupUseCase;
use App\Features\Backup\App\UseCases\GetBackupListUseCase;
use App\Features\Backup\App\UseCases\GetBackupStatusUseCase;
use App\Features\Backup\App\UseCases\ImportAndRestoreBackupUseCase;
use App\Features\Backup\App\UseCases\StartBackupRestoreUseCase;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Exceptions\BackupNotFoundException;
use App\Features\Backup\Domain\Exceptions\BackupNotRestorableException;
use App\Features\Backup\Infra\Requests\RestoreBackupRequest;
use App\Features\Backup\Infra\Requests\UploadRestoreRequest;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as ResponseFacade;
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
 * async — the start use case schedules the job, the UI polls the status
 * endpoint.
 */
final class BackupController
{
    public function __construct(
        private readonly GetBackupListUseCase $listCase,
        private readonly CreateBackupUseCase $createCase,
        private readonly DestroyBackupUseCase $destroyCase,
        private readonly DownloadBackupUseCase $downloadCase,
        private readonly StartBackupRestoreUseCase $restoreCase,
        private readonly ImportAndRestoreBackupUseCase $importCase,
        private readonly GetBackupStatusUseCase $statusCase,
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
        return Redirect::back()->with('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_creation_started')]);
    }

    public function destroy(int $backup): HttpResponse
    {
        try {
            $this->destroyCase->destroy($backup);
        } catch (BackupNotFoundException) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        } catch (Throwable $exception) {
            return Redirect::back()->with('notification', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return Redirect::back()->with('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_deleted')]);
    }

    public function restore(RestoreBackupRequest $request): HttpResponse
    {
        try {
            $this->restoreCase->start((int) $request->validated('backup_id'));
        } catch (BackupNotFoundException) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        } catch (BackupNotRestorableException) {
            return Redirect::back()->with('notification', [
                'type' => 'error',
                'message' => __('admin.toasts.backup_restore_failed'),
            ]);
        }

        return Redirect::back()->with('notification', [
            'type' => 'success',
            'message' => __('admin.toasts.backup_restore_started'),
        ]);
    }

    public function uploadRestore(UploadRestoreRequest $request): HttpResponse
    {
        $file = $request->file('file');

        try {
            $this->importCase->importAndRestore($file->getPathname(), (string) $request->validated('key'));
        } catch (ImportValidationException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('admin.toasts.upload_failed')]);
        }

        return Redirect::to('/admin/backups')->with('notification', [
            'type' => 'success',
            'message' => __('admin.toasts.backup_restore_started'),
        ]);
    }

    public function download(int $backup): HttpResponse
    {
        try {
            $download = $this->downloadCase->download($backup);
        } catch (Throwable) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $stream = $this->backupsDisk->readStream($download->path);

        if ($stream === false) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return ResponseFacade::streamDownload(function () use ($stream): void {
            while (! feof($stream)) {
                echo fread($stream, 8192);
            }

            fclose($stream);
        }, $download->filename, ['Content-Type' => 'application/zip']);
    }

    public function status(int $backup): JsonResponse
    {
        try {
            $status = $this->statusCase->get($backup);
        } catch (BackupNotFoundException) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return ResponseFacade::json([
            'data' => [
                'id' => $status->id,
                'status' => $status->status->value,
                'progress' => $status->progress,
                'completed_at' => $status->completedAt?->format('c'),
                'error_message' => $status->errorMessage,
            ],
        ]);
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
