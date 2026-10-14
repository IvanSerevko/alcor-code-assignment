# Earning Line with a Traceable History of Manual Adjustments

A dependency-free PHP 8.4 domain model for the payroll business case: one
automatically calculated earning line that a specialist can manually correct,
with an audit history that can never be rewritten.

## Run it

```bash
composer install && vendor/bin/phpunit   # 45 unit tests + 1 scenario test
php bin/demo.php
```

No local PHP? The environment is pinned in the image:

```bash
docker compose run --rm test
docker compose run --rm demo
```

## The model

- `EarningLine` (aggregate root) — owns all invariants. `recalculate()` and
  `applyAdjustment()` are the only ways the line changes; the current value and
  the full `AuditTrail` can be read at any point.
- `ManualAdjustment` — one recorded correction: ordinal, signed amount, mandatory
  comment. Readonly and self-validating.
- `AuditTrail` — projection of the frozen system value + ordered adjustments;
  the current value is always derived, never stored.
- `Money` — integer cents (parsing accepts "$1,050.00", "-$45.55"), because float
  arithmetic on payroll money is how fractions of a cent silently drift.

## How each business rule maps to code

| Rule | Implementation |
|---|---|
| Corrections are append-only, never edited or deleted | `ManualAdjustment` is readonly; the aggregate exposes no mutation API (pinned by a reflection test) |
| Mistakes fixed by a compensating correction | opposite-signed `applyAdjustment()`, history untouched |
| After the first correction, recalculation must no longer affect the line | `recalculate()` returns `false` and changes nothing once `isFrozen()` |
| Comment mandatory, amount signed | validated in `ManualAdjustment` constructor (blank comment / zero amount throw) |
| Current value + full audit always derivable | `currentValue()`, `auditTrail()` |
| Worked example from the brief | `tests/Scenario/WorkedExampleTest.php` asserts every intermediate value and the final audit table ($1,104.45) |

## Design notes

- **No Event Sourcing.** The brief allows a simpler OO design; what the rules
  need is an append-only trail of corrections, which is exactly the adjustment
  list inside the aggregate. ES machinery (serialised events, replay) would add
  no strength to any required rule. If this grew, the same list could be stored
  as an event stream behind a repository without touching the domain rules.
- **`recalculate()` ignores, never throws.** "Must be ignored" is normal flow,
  so the method is total and returns a bool the caller can log.
- **Two variants exist by intent:** `master` is the lean, review-first solution;
  branch `variant-full` additionally models currency-tagged money, cross-currency
  guards and expanded rationale, kept to show the trade-off thinking.

## Assumptions

- Single currency (USD) per line; multi-currency handling lives in `variant-full`.
- No persistence layer - in-memory aggregate; `(systemValue, adjustments)` is
  sufficient to reconstruct the line since values are derived.
- Zero corrections are invalid ("positive or negative" per the brief).
- Comment length caps belong to the storage/UI boundary, out of this scope.
- Adjustment ordinals are per-line and gapless, as shown in the brief's table.
