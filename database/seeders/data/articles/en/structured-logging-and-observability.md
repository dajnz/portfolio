A production incident is not the time to discover that your logs are unstructured strings. Observability is a design property, not a tool you bolt on later.

## Log events, not sentences

Emit structured context so a machine can group and filter. The message stays short; the fields carry the meaning.

```php
Log::info('order.shipment.failed', [
    'order_id' => $order->toId(),
    'carrier' => $carrier,
    'attempt' => $attempt,
]);
```

> [!TIP]
> Give every event a stable dotted name. Renaming log messages casually breaks every dashboard built on them.

## The three signals

| Signal | Answers | Cost |
| --- | --- | --- |
| Logs | what happened? | storage |
| Metrics | how often? | cardinality |
| Traces | where did time go? | sampling |

## Alert on symptoms

Alert when users are affected, not when a single metric twitches. A page that fires on CPU spikes trains everyone to ignore it.

![Observability](/assets/demo/articles/observability.svg)

> [!WARNING]
> High-cardinality labels (user IDs, request IDs) belong in logs and traces, never in metric names.

## Correlate by default

A request ID threaded through logs and traces turns three separate tools into one investigation.
