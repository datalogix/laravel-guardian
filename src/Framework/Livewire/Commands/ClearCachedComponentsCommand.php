<?php

namespace Datalogix\Guardian\Framework\Livewire\Commands;

use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Guardian;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'guardian:clear-cached-components')]
class ClearCachedComponentsCommand extends Command
{
    protected $description = 'Clear all cached components';

    protected $signature = 'guardian:clear-cached-components';

    public function handle(): int
    {
        $this->info('Clearing cached components...');

        foreach (Guardian::getFortresses() as $fortress) {
            $adapter = $fortress->getFrameworkAdapter();

            if ($adapter instanceof LivewireAdapter) {
                $adapter->clearCachedComponents($fortress);
            }
        }

        $this->info('All done!');

        return static::SUCCESS;
    }
}
