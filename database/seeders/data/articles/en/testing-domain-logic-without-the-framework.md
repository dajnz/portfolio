The most valuable tests cover the rules your business cannot function without — and those rules rarely need a database, an HTTP layer or a framework container.

## Push the framework to the edges

If an entity depends on the ORM, every rule test drags in a bootstrap. Keep domain objects free of infrastructure imports and the whole suite runs in milliseconds.

```php
$invoice = Invoice::issue(
    new InvoiceNumber('2026-0042'),
    new Money(1250, 'EUR'),
);

expect($invoice->toTotal()->amount())->toBe(1250);
```

> [!IMPORTANT]
> A domain test that needs a container is usually testing the wiring, not the rule.

## Value objects carry the validation

Constructing an invalid object should be impossible. Then every downstream test can assume valid input.

| Rule | Enforced by |
| --- | --- |
| Positive amount | `Money` |
| Non-empty title | `Title` |
| Valid status transition | `Invoice` |

## Integration tests for the seams

One integration test per repository or endpoint proves the mapping works. The rest stays fast and focused.

![Domain testing](/assets/demo/articles/testing.svg)

> [!TRADEOFF]
> Pure domain code is more verbose up front. The payoff is a suite you can run on every save instead of once a day.

## Fixtures as builders

Builders keep tests readable and hide the parts of setup a given test does not care about.

```php
$invoice = InvoiceFactory::new()->overdue()->make();
```
