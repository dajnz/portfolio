<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\TagDataDto;
use App\Features\Blog\Domain\Exceptions\ArticleTagNotFoundException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;

/**
 * The tag editor data (the create/edit form, blog.yaml#
 * TagFormPageProps): the stored tag with its per-locale names (null
 * when creating).
 */
final class GetTagForEditUseCase
{
    public function __construct(
        private readonly ArticleTagRepositoryInterface $repository,
    ) {
    }

    public function get(?int $id, string $locale): ?TagDataDto
    {
        if ($id === null) {
            return null;
        }

        $tag = $this->repository->findById($id);

        if ($tag === null) {
            throw ArticleTagNotFoundException::forId($id);
        }

        return TagDataDto::fromEntity($tag, $locale);
    }
}
