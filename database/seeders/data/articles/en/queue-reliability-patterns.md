Queues move slow work out of the request cycle. The hard part is not enqueuing a job — it is keeping jobs correct when things fail halfway through.

## Idempotency first

Assume every job will run at least once. Persist a natural key and make the handler safe to repeat before adding any retry logic.

```php
public function handle(OrderId $order): void
{
    if ($this->processed->has($order)) {
        return;
    }

    $this->process($order);

    $this->processed->put($order, true);
}
```

> [!WARNING]
> A retry is not a rollback. External side effects that already happened cannot be undone by replaying the job.

## Retries and backoff

Exponential backoff gives a failing dependency time to recover instead of hammering it.

| Attempt | Delay |
| --- | --- |
| 1 | 1 min |
| 2 | 5 min |
| 3 | 30 min |

## Dead letters

After the last attempt the job should land somewhere durable and visible. A dead-letter queue that nobody reads is just a more expensive log.

![Queue reliability](/assets/demo/articles/queues.svg)

> [!TIP]
> Track the dead-letter depth as a first-class metric. Its growth is the earliest signal of a broken dependency.

## Monitoring what matters

Queue length alone is misleading: a healthy throughput with growing latency is worse than a short spike. Watch depth, failure rate and job age together.

## Separate queues by priority

Long imports should not starve password resets. Route by intent so a slow batch job cannot delay user-facing work.
