<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use Illuminate\Support\Facades\DB;

/**
 * The framework's transactional connection behind the app-layer
 * {@see TransactionManagerInterface} contract.
 */
final class LaravelTransactionManager implements TransactionManagerInterface
{
    public function transaction(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
