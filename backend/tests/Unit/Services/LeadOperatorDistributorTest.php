<?php

use App\Services\LeadOperatorDistributor;

/**
 * Business-rule br-balanced (spec 0048) — the pure, DB-free core of
 * LeadOperatorDistributor::distribute()/groupByOperator(). No RefreshDatabase:
 * these two methods take/return plain arrays only.
 */
it('assigns each target to the least-loaded operator, round-robin when loads start equal', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distribute(
        operatorIds: [1, 2, 3],
        initialLoads: [],
        targetIds: [10, 11, 12, 13, 14, 15],
    );

    // 6 targets over 3 equally-loaded operators -> 2 each.
    expect(array_count_values($assignments))->toBe([1 => 2, 2 => 2, 3 => 2]);
});

it('respects pre-existing load: fills the least-loaded operator first, converging to a balanced final load', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distribute(
        operatorIds: [1, 2],
        initialLoads: [1 => 2, 2 => 0],
        targetIds: [100, 101, 102, 103],
    );

    // Operator 2 starts behind (0 vs 2) and gets 3 of the 4 targets to
    // catch up; operator 1 gets the 1 it needs to stay balanced. Final
    // load: operator 1 = 2+1 = 3, operator 2 = 0+3 = 3 (br-balanced: the
    // total is uniformed, not merely split evenly per target).
    expect($assignments)->toBe([100 => 2, 101 => 2, 102 => 1, 103 => 2]);
});

it('breaks a load tie by the LOWEST operator id', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distribute(
        operatorIds: [2, 5, 8],
        initialLoads: [2 => 3, 5 => 3, 8 => 3],
        targetIds: [1],
    );

    expect($assignments)->toBe([1 => 2]);
});

it('assigns every target to the single operator when there is only one', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distribute(
        operatorIds: [7],
        initialLoads: [7 => 10],
        targetIds: [1, 2, 3],
    );

    expect($assignments)->toBe([1 => 7, 2 => 7, 3 => 7]);
});

it('processes targets in the given order, so the final distribution is deterministic', function () {
    $distributor = new LeadOperatorDistributor;

    $first = $distributor->distribute([1, 2], [], [10, 11, 12]);
    $second = $distributor->distribute([1, 2], [], [10, 11, 12]);

    expect($first)->toBe($second);
});

it('throws when there are zero operators to distribute to', function () {
    $distributor = new LeadOperatorDistributor;

    expect(fn () => $distributor->distribute([], [], [1, 2]))
        ->toThrow(InvalidArgumentException::class);
});

it('groupByOperator inverts a target=>operator map into operator=>[targets]', function () {
    $distributor = new LeadOperatorDistributor;

    $grouped = $distributor->groupByOperator([10 => 1, 11 => 2, 12 => 1]);

    expect($grouped)->toBe([1 => [10, 12], 2 => [11]]);
});

/**
 * Spec 0110 — distributeAmong(): the same greedy when every target carries
 * its OWN candidate pool (the competence filter narrows the Sede's operators
 * record by record). distribute() is now a thin wrapper over it, so the block
 * above doubles as its regression net.
 */
it('0110 AC-020: distributeAmong balances inside each pool while sharing one load map', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distributeAmong(
        candidatesByTarget: [10 => [1, 2], 11 => [1, 2], 12 => [3], 13 => [1, 2]],
        initialLoads: [],
    );

    // 1 and 2 alternate on their own three targets; 3 only ever receives the
    // target it is the sole candidate for, idle as the others may be.
    expect($assignments)->toBe([10 => 1, 11 => 2, 12 => 3, 13 => 1]);
});

it('0110 AC-020: distributeAmong breaks a tie inside a pool by the LOWEST operator id', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distributeAmong([7 => [8, 5, 2]], [2 => 3, 5 => 3, 8 => 3]);

    expect($assignments)->toBe([7 => 2]);
});

it('0110 AC-021: distributeAmong SKIPS a target with an empty candidate pool', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distributeAmong([10 => [1], 11 => [], 12 => [1]], []);

    expect($assignments)->toBe([10 => 1, 12 => 1]);
});

it('0110: distributeAmong walks the targets in ascending id order regardless of the caller\'s order', function () {
    $distributor = new LeadOperatorDistributor;

    $assignments = $distributor->distributeAmong([12 => [1, 2], 10 => [1, 2], 11 => [1, 2]], []);

    expect($assignments)->toBe([10 => 1, 11 => 2, 12 => 1]);
});
