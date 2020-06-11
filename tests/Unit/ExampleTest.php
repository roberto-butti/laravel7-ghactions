<?php

namespace Tests\Unit;

//use PHPUnit\Framework\TestCase;
use \Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
//   use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory('App\User')->create();
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
        $this->assertStringStartsWith("TEST_NAME", $this->user->name);

    }
}
