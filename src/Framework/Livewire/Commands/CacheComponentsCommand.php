<?php

namespace Datalogix\Guardian\Framework\Livewire\Commands;

use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
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
            $adapter = $fortress->getFrameworkAdapter();

            if ($adapter instanceof LivewireAdapter) {
                $adapter->cacheComponents($fortress);
            }
        }

        $this->info('All done!');

        return static::SUCCESS;
    }
}
