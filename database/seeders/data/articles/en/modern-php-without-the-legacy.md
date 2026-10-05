Modern PHP is not a different language from the legacy you inherited. It is the same language used with more discipline: strict types, small objects and explicit boundaries.

## Strict types everywhere

`declare(strict_types=1)` turns silent coercion into a loud failure at the boundary. That is exactly where you want it.

```php
declare(strict_types=1);

final readonly class Email
{
    public function __construct(public string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email.');
        }
    }
}
```

> [!IMPORTANT]
> An entity that protects its invariants cannot be put into an invalid state by careless calling code.

## Replace array soup with objects

Passing associative arrays through five layers hides the contract. A named object documents it and lets the type checker help.

| Instead of | Use |
| --- | --- |
| `['id' => 1, 'sum' => 100]` | `Money`, `InvoiceId` |
| `string $status` | `InvoiceStatus` enum |
| `null` return | exception or empty object |

## Dependencies point inward

Domain code should not import the framework. When it does, the rules become impossible to test in isolation.

> [!TRADEOFF]
> More types mean more up-front code. The payoff is that refactoring is a compiler error, not a treasure hunt.

## Adopt incrementally

You do not have to rewrite the legacy system. Draw a boundary, make the new code strict, and let the old code shrink.
