# Earning Line with a Traceable History of Manual Adjustments

A small domain model implementing the Alcor payroll business case: a system-calculated
earning line that a payroll specialist can manually correct, with an audit history that
can never be silently rewritten.

The assignment brief is intentionally open on architecture; this solution uses a
**simple, dependency-light DDD style** (an aggregate root plus immutable value objects).
The full worked example from the brief is reproduced both as a test
(`tests/Scenario/WorkedExampleTest.php`) and as an executable CLI (`bin/demo.php`).

## Quick start

Requirements: PHP >= 8.4 and Composer — or just Docker.

```bash
composer install
vendor/bin/phpunit          # 50 tests
php bin/demo.php            # runs the assignment scenario end to end
```

Without a local PHP at all:

```bash
docker compose run --rm test    # PHPUnit
docker compose run --rm demo    # scenario walkthrough
```

## How each business rule is enforced

| Business rule | Where it lives | Test |
|---|---|---|
| A line can receive multiple corrections over time | `EarningLine::applyAdjustment()` appends; ordinals are assigned by the aggregate | `EarningLineTest::testAdjustmentsAreAppendedWithConsecutiveOrdinals` |
| Every correction stays visible and traceable | `AuditTrail` exposes the frozen system value and every adjustment in recording order | `AuditTrailTest::testExposesAdjustmentsInRecordingOrder` |
| No correction may be edited or deleted once saved | There is **no API** to mutate or remove an adjustment (`ManualAdjustment` is a `readonly` value object; the aggregate's public surface is append/query only) | `EarningLineTest::testExposesOnlyAppendOnlyOperations` |
| Mistakes are fixed by a new compensating correction | Nothing prevents an opposite-signed adjustment; history stays intact | `EarningLineTest::testCompensatingAdjustmentFixesPreviousMistakeWithoutTouchingHistory` |
| After the first manual correction, system recalculation must no longer affect the line | `recalculate()` returns `false` and changes nothing while `isFrozen()` is true; the system value stays frozen forever | `EarningLineTest::testRecalculationIsIgnoredAfterManualCorrection` |
| Current value and full audit history must always be derivable | `currentValue()` is computed from the frozen base + adjustments, never stored separately | `WorkedExampleTest` (all eight steps and the final audit table) |
| Correction requires an explaining comment | `ManualAdjustment` rejects blank/whitespace comments | `ManualAdjustmentTest::testRejectsBlankComment` |
| Correction is a positive or negative amount | `ManualAdjustment` rejects zero amounts | `ManualAdjustmentTest::testRejectsZeroAmount` |

## Design decisions and trade-offs

**Aggregate with an append-only log, not Event Sourcing.**
The brief allows either. The business case needs an immutable trail of *manual
corrections*, not a replayable event store of every state change — the value is
already derivable from `systemValue + Σ adjustments`. Modelling it as ES would add
serialization, replay, and projector machinery without strengthening any of the
required rules, so the in-aggregate append-only log is the honest, smaller design.
If this grew into the real payroll platform, the same aggregate could have its
adjustment list persisted as an event stream behind a repository, with no changes
to the domain rules.

**Money as integer cents in a value object.**
Float arithmetic on money drifts by fractions of a cent — precisely what payroll
cannot ship. `Money` stores `int $cents` plus an ISO 4217 code, rejects sub-cent
input at parse time instead of rounding, and refuses cross-currency arithmetic.

**`recalculate()` ignores, never throws.**
The rule is "must be ignored", so the method is total: it returns `bool` telling the
caller whether the new system value was accepted or dropped because manual
precedence took over. No exception-driven control flow, no try/catch dance in the
caller.

**Immutability is enforced by the interface, not by convention.**
`ManualAdjustment` and `AuditTrail` are `readonly` classes; `EarningLine`'s only
public surface is `fromSystemValue / recalculate / applyAdjustment / currentValue /
auditTrail / isFrozen`. A reflection test pins this list so a future teammate cannot
quietly add a setter to the aggregate.

## Project layout

```
src/
  EarningLine.php        aggregate root — owns the invariants
  ManualAdjustment.php   recorded correction (ordinal, signed Money, comment)
  AuditTrail.php         immutable projection: frozen system value + adjustments
  Money.php              monetary value object (integer cents + ISO currency)
  Exception/             small, intention-revealing domain exceptions
bin/demo.php             CLI walkthrough of the assignment scenario
tests/
  Unit/                  per-class behaviour tests
  Scenario/              the brief's worked example, step by step
```

There is no framework: the model is pure PHP 8.4 (`strict_types`, readonly classes,
promoted and named constructor arguments), runnable and testable on its own.

## Assumptions

- **No persistence layer.** The aggregate keeps history in memory; storage is out of
  scope for a data-modelling exercise. The design assumes a repository would serialize
  `(systemValue, adjustments)` — enough to reconstruct the line, since the current
  value is derived, not stored.
- **One currency per line.** The line adopts its currency from the first system value;
  mixed-currency corrections are rejected as a domain error.
- **Zero corrections are invalid.** The brief says "positive or negative"; a zero
  correction changes nothing but would pollute the audit trail.
- **Comment length is not capped.** Real limits (e.g. column width, UI) belong to the
  persistence/presentation boundary, which is not part of this exercise.
- **Ordinals are per-line and gapless** (`Adjustment 1, 2, 3 …` as in the brief). If
  stable global IDs were needed across storage, a `LineId/AdjustmentId` would be added
  at the repository boundary without touching the rules.
