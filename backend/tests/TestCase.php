<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The local .env may switch the users import to link-only for the
        // real migration; the suite assumes the full import and the tests
        // that need link-only turn it on themselves.
        config(['migrations.users_link_only' => false]);
    }
}
