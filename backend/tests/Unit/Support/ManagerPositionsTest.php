<?php

use App\Models\Opportunity;
use App\Support\ManagerPositions;

// Spec 0087, D-2: ManagerPositions::OPERATOR is the neutral home for the
// "GA2 = Operatore" slot, shared by Opportunity (via its own, unrenamed
// alias — 15 call sites untouched, engineering.md §1.6) and Quote (which
// reads the constant directly).

it('OPERATOR is position 2', function () {
    expect(ManagerPositions::OPERATOR)->toBe(2);
});

it('Opportunity::OPERATOR_MANAGER_POSITION is an alias of ManagerPositions::OPERATOR', function () {
    expect(Opportunity::OPERATOR_MANAGER_POSITION)->toBe(ManagerPositions::OPERATOR);
});
