<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\DTOs\AdminFeedbackListQueryDto;
use App\Features\Portfolio\App\UseCases\DestroyFeedbackUseCase;
use App\Features\Portfolio\App\UseCases\ListFeedbackUseCase;
use App\Features\Portfolio\App\UseCases\MarkFeedbackForwardedUseCase;
use App\Features\Portfolio\App\UseCases\MarkFeedbackReadUseCase;
use App\Features\Portfolio\App\UseCases\ShowFeedbackUseCase;
use App\Features\Portfolio\App\UseCases\SubmitFeedbackUseCase;
use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Portfolio\Infra\Requests\SubmitFeedbackRequest;
use App\Features\Shared\App\DTOs\CursorDto;
use App\Features\Shared\App\DTOs\CursorPaginationDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Feedback surface: the public `POST /api/feedback` submission plus the
 * admin list / detail / flag / delete actions.
 *
 * The public store runs the layered protection: `throttle:feedback` +
 * `rate_limit.fingerprint` on the route, and the honeypot check here — a
 * filled honeypot is silently rejected (200, nothing stored), and the check
 * is skipped when the session ID cannot be resolved.
 */
final class FeedbackController
{
    public function __construct(
        private readonly SubmitFeedbackUseCase $submitCase,
        private readonly ListFeedbackUseCase $listCase,
        private readonly ShowFeedbackUseCase $showCase,
        private readonly MarkFeedbackReadUseCase $markReadCase,
        private readonly MarkFeedbackForwardedUseCase $markForwardedCase,
        private readonly DestroyFeedbackUseCase $destroyCase,
    ) {
    }

    public function store(SubmitFeedbackRequest $request): HttpResponse
    {
        if ($request->honeypotFilled()) {
            return ResponseFacade::make('', 200);
        }

        $this->submitCase->submit(
            senderName: $request->input('sender_name', ''),
            senderEmail: $request->input('sender_email', ''),
            message: $request->input('message', ''),
            ip: (string) $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return ResponseFacade::make(null, 201);
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $result = $this->listCase->list($this->buildListQuery($request));

        return $inertia->render('Portfolio/Admin/Pages/FeedbackListPage', [
            'feedback' => $this->items($result->rows),
            'meta' => $this->meta($result->pagination),
        ]);
    }

    public function show(ResponseFactory $inertia, int $feedback): Response
    {
        $item = $this->showCase->show($feedback);

        if ($item === null) {
            abort(404);
        }

        return $inertia->render('Portfolio/Admin/Pages/FeedbackDetailPage', [
            'item' => $this->itemProps($item),
        ]);
    }

    public function markRead(Request $request, int $feedback): JsonResponse
    {
        $this->markReadCase->markRead($feedback);

        return ResponseFacade::json(['data' => ['id' => $feedback, 'is_read' => true]]);
    }

    public function markForwarded(Request $request, int $feedback): JsonResponse
    {
        $this->markForwardedCase->markForwarded($feedback);

        return ResponseFacade::json(['data' => ['id' => $feedback, 'is_forwarded' => true]]);
    }

    public function destroy(Request $request, int $feedback): RedirectResponse
    {
        $this->destroyCase->destroy($feedback);

        // The detail URL 404s after deletion — redirect to the list.
        return Redirect::to('/admin/feedback')
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.feedback_deleted')]);
    }

    /**
     * The app-layer admin list query — the raw query values are
     * extracted here, the paginator parsing happens in the infra
     * paginator implementation.
     */
    private function buildListQuery(Request $request): AdminFeedbackListQueryDto
    {
        return new AdminFeedbackListQueryDto(
            page: max(1, (int) $request->query('page', 1)),
            cursorParams: is_array($request->input('cursor')) ? $request->input('cursor') : null,
            sortColumn: $this->sortColumn($request),
            sortDirection: $this->sortDirection($request),
            url: $request->fullUrl(),
        );
    }

    private function sortColumn(Request $request): ?string
    {
        $column = trim((string) $request->query('sort_column', ''));

        return $column === '' ? null : $column;
    }

    private function sortDirection(Request $request): ?string
    {
        $direction = strtolower(trim((string) $request->query('sort_direction', '')));

        return $direction === '' ? null : $direction;
    }

    /**
     * @param  array<int, mixed>  $models  Raw rows from the paginator result.
     * @return array<int, array<string, mixed>>
     */
    private function items(array $models): array
    {
        return array_map(
            static fn (FeedbackModel $model): array => [
                'id' => $model->id,
                'sender_name' => $model->sender_name,
                'sender_email' => $model->sender_email,
                'message' => $model->message,
                'is_read' => $model->is_read,
                'is_forwarded' => $model->is_forwarded,
                'created_at' => $model->created_at->toISO8601String(),
            ],
            $models,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function itemProps(Feedback $item): array
    {
        return [
            'id' => $item->toId(),
            'sender_name' => $item->toSenderName(),
            'sender_email' => $item->toSenderEmail(),
            'message' => $item->toMessage(),
            'is_read' => $item->toIsRead(),
            'is_forwarded' => $item->toIsForwarded(),
            'created_at' => $item->toCreatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * The admin list `meta` wire shape from the cursor pagination
     * facts.
     *
     * @return array<string, mixed>
     */
    private function meta(CursorPaginationDto $pagination): array
    {
        return [
            'total' => $pagination->total,
            'per_page' => $pagination->perPage,
            'current_page' => $pagination->currentPage,
            'sort_column' => $pagination->sortColumn,
            'sort_direction' => $pagination->sortDirection,
            'cursors' => [
                'first' => self::cursorProps($pagination->cursors->first),
                'last' => self::cursorProps($pagination->cursors->last),
                'page' => $pagination->cursors->page,
            ],
        ];
    }

    /**
     * @return array{id: int, sort_value: mixed}|null
     */
    private static function cursorProps(?CursorDto $cursor): ?array
    {
        return $cursor === null
            ? null
            : [
                'id' => $cursor->id,
                'sort_value' => $cursor->sortValue,
            ];
    }
}
