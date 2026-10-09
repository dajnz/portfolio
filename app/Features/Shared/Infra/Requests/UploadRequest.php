<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `POST /api/upload` validation: images (`blog`/`projects`/`about`) accept
 * jpeg/png/gif/webp/avif up to 3072 KB; `about` also accepts PDF up to 5120 KB
 * (resume upload). The per-MIME size cap for `about` is enforced in `after()`
 * because a single rule cannot express "3 MB for images, 5 MB for PDFs".
 */
final class UploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $entityType = $this->input('entity_type');

        return match ($entityType) {
            'blog', 'projects' => [
                'file' => ['required', 'file', 'mimes:jpeg,png,gif,webp,avif', 'max:3072'],
                'entity_type' => ['required', 'string', Rule::in(['blog', 'projects', 'about'])],
            ],
            'about' => [
                'file' => ['required', 'file', 'mimes:jpeg,png,gif,webp,avif,pdf'],
                'entity_type' => ['required', 'string', Rule::in(['blog', 'projects', 'about'])],
            ],
            default => [
                'file' => ['required', 'file'],
                'entity_type' => ['required', 'string', Rule::in(['blog', 'projects', 'about'])],
            ],
        };
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('entity_type') !== 'about') {
                    return;
                }

                $file = $this->file('file');
                if ($file === null) {
                    return;
                }

                $maxKb = $file->getMimeType() === 'application/pdf' ? 5120 : 3072;

                if ($file->getSize() > $maxKb * 1024) {
                    $validator->errors()->add('file', "The file may not be greater than {$maxKb} kilobytes.");
                }
            },
        ];
    }
}
