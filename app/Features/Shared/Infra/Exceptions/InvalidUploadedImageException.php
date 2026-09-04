<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Exceptions;

use RuntimeException;

/**
 * The uploaded file could not be decoded as an image — raised by
 * `UploadedImageProcessor` when the upload pipeline receives bytes that
 * pass the MIME sniffing but are not a readable image (corrupt or
 * truncated file). The upload controller converts it into a 422
 * validation error on the `file` field.
 */
final class InvalidUploadedImageException extends RuntimeException
{
}
