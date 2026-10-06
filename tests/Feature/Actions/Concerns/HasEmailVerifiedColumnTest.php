<?php

namespace Datalogix\Guardian\Tests\Feature\Actions\Concerns;

use Datalogix\Guardian\Actions\Concerns\HasEmailVerifiedColumn;
use Datalogix\Guardian\Tests\Fixtures\OtherConnectionAdmin;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class HasEmailVerifiedColumnTest extends TestCase
{
    public function test_the_column_is_looked_for_on_the_connection_of_the_model(): void
    {
        config(['database.connections.admins' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

        Schema::connection('admins')->create('admins', function ($table) {
            $table->id();
            $table->timestamp('email_verified_at')->nullable();
        });

        // A table with the same name on the default connection, without the column.
        Schema::create('admins', fn ($table) => $table->id());

        $harness = new class
        {
            use HasEmailVerifiedColumn;

            public function has(string $modelClass): bool
            {
                return $this->hasEmailVerifiedColumn($modelClass);
            }
        };

        $this->assertTrue($harness->has(OtherConnectionAdmin::class));
    }
}
