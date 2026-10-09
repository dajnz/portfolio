<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\DestroyFeedbackUseCase;
use App\Features\Portfolio\App\UseCases\ListFeedbackUseCase;
use App\Features\Portfolio\App\UseCases\MarkFeedbackForwardedUseCase;
use App\Features\Portfolio\App\UseCases\MarkFeedbackReadUseCase;
use App\Features\Portfolio\App\UseCases\ShowFeedbackUseCase;
use App\Features\Portfolio\App\UseCases\SubmitFeedbackUseCase;
use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Portfolio\Infra\Requests\SubmitFeedbackRequest;
use App\Features\Shared\Infra\Persistence\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            return response('', 200);
        }

        $this->submitCase->submit(
            senderName: $request->input('sender_name', ''),
            senderEmail: $request->input('sender_email', ''),
            message: $request->input('message', ''),
            ip: (string) $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        return response(null, 201);
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $page = $this->listCase->list($request);

        return $inertia->render('Portfolio/Admin/Pages/FeedbackListPage', [
            'feedback' => $this->items($page),
            'meta' => $this->meta($page),
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

        return response()->json(['data' => ['id' => $feedback, 'is_read' => true]]);
    }

    public function markForwarded(Request $request, int $feedback): JsonResponse
    {
        $this->markForwardedCase->markForwarded($feedback);

        return response()->json(['data' => ['id' => $feedback, 'is_forwarded' => true]]);
    }

    public function destroy(Request $request, int $feedback): RedirectResponse
    {
        $this->destroyCase->destroy($feedback);

        // The detail URL 404s after deletion — redirect to the list.
        return redirect('/admin/feedback')
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.feedback_deleted')]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(CursorPage $page): array
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
            $page->items,
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
     * @return array<string, mixed>
     */
    private function meta(CursorPage $page): array
    {
        return [
            'total' => $page->total,
            'per_page' => $page->perPage,
            'current_page' => $page->currentPage,
            'sort_column' => $page->sorting->sortColumn,
            'sort_direction' => $page->sorting->sortDirection,
            'cursors' => $page->cursors->toArray(),
        ];
    }
}
