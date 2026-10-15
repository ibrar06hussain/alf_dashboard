<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            if (! \Illuminate\Support\Facades\Schema::hasTable('tehsils')) {
                \Illuminate\Support\Facades\Schema::create('tehsils', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('district_id');
                    $table->string('name', 80);
                });
            }
            if (! \Illuminate\Support\Facades\Schema::hasTable('union_councils')) {
                \Illuminate\Support\Facades\Schema::create('union_councils', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('tehsil_id');
                    $table->string('name', 100);
                });
            }
        }
    }
}
