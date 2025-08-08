<?php

namespace Datalogix\Fortress\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fortress:clear-cached-components')]
class ClearCachedComponentsCommand extends Command
{
    protected $description = 'Clear all cached components';

    protected $signature = 'fortress:clear-cached-components';

    public function handle(): int
    {
        $this->info('Clearing cached components...');

        foreach (Fortress::getFortresses() as $fortress) {
            $fortress->clearCachedComponents();
        }

        $this->info('All done!');

        return static::SUCCESS;
    }
}
