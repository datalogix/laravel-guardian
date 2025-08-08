<?php

namespace Datalogix\Fortress\Commands;

use Datalogix\Fortress\Facades\Fortress;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fortress:cache-components')]
class CacheComponentsCommand extends Command
{
    protected $description = 'Cache all components';

    protected $signature = 'fortress:cache-components';

    public function handle(): int
    {
        $this->info('Caching registered components...');

        foreach (Fortress::getFortresses() as $fortress) {
            $fortress->cacheComponents();
        }

        $this->info('All done!');

        return static::SUCCESS;
    }
}
