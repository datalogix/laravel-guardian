<?php

namespace Datalogix\Guardian\Commands;

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
            $fortress->clearCachedComponents();
        }

        $this->info('All done!');

        return static::SUCCESS;
    }
}
