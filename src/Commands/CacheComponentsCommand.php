<?php

namespace Datalogix\Guardian\Commands;

use Datalogix\Guardian\Guardian;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'guardian:cache-components')]
class CacheComponentsCommand extends Command
{
    protected $description = 'Cache all components';

    protected $signature = 'guardian:cache-components';

    public function handle(): int
    {
        $this->info('Caching registered components...');

        foreach (Guardian::getFortresses() as $fortress) {
            $fortress->cacheComponents();
        }

        $this->info('All done!');

        return static::SUCCESS;
    }
}
