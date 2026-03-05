<?php

namespace Datalogix\Guardian\Commands;

use Datalogix\Guardian\Support\TrustedDevices;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'guardian:prune-trusted-devices')]
class PruneTrustedDevicesCommand extends Command
{
    protected $description = 'Prune expired and revoked trusted two-factor devices';

    protected $signature = 'guardian:prune-trusted-devices {--days=30 : Keep revoked devices for this many days before deleting}';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = app(TrustedDevices::class)->prune($days);

        $this->info("Pruned {$deleted} trusted device record(s).");

        return static::SUCCESS;
    }
}
