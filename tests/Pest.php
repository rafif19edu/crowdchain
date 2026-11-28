<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel app and use a fresh PostgreSQL test database
| (crowdchain_test, see phpunit.xml) for every test.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
