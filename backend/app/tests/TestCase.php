<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

abstract class TestCase extends BaseTestCase {
    use CreatesApplication;
    use DatabaseTransactions;

    static function setUpBeforeClass(): void {
        $miniApp = (new class() {
            use CreatesApplication;
        })->createApplication();

        $miniApp[Kernel::class]->call('db:create');
        $miniApp[Kernel::class]->call('migrate:fresh');
        $miniApp[Kernel::class]->call('db:seed');
    }

    function setUp(): void {
        parent::setUp();
        // Shows errors instead of wrapping them in a 500 response, so that I can see what happened.
        $this->withoutExceptionHandling();
    }
}
