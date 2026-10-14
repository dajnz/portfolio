<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Runs an operation inside a database transaction, so a use case can make a
 * multi-step write atomic without naming the framework's transactional
 * connection. The implementation lives on the infra layer; it returns whatever
 * the operation returns.
 */
interface TransactionManagerInterface
{
    public function transaction(callable $operation): mixed;
}
