<?php

namespace Datalogix\Guardian\Framework\Livewire;

use Datalogix\Guardian\Fortress;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ComponentCache
{
    public function path(Fortress $fortress): string
    {
        return (config('guardian.livewire.cache_path') ?? base_path('bootstrap/cache/guardian')).DIRECTORY_SEPARATOR."{$fortress->getId()}.php";
    }

    /**
     * Only real requests read the cache, so artisan always sees the components on disk.
     */
    public function exists(Fortress $fortress): bool
    {
        return ! app()->runningInConsole() && app(Filesystem::class)->exists($this->path($fortress));
    }

    /**
     * @return array<string, string> component name => class
     */
    public function read(Fortress $fortress): array
    {
        $cache = require $this->path($fortress);

        return $cache['livewireComponents'] ?? [];
    }

    /**
     * @param  array<string, string>  $components  component name => class
     */
    public function write(Fortress $fortress, array $components): void
    {
        $path = $this->path($fortress);
        $filesystem = app(Filesystem::class);

        $filesystem->ensureDirectoryExists(Str::of($path)->beforeLast(DIRECTORY_SEPARATOR)->toString());

        $filesystem->put($path, '<?php return '.var_export(['livewireComponents' => $components], true).';');
    }

    public function clear(Fortress $fortress): void
    {
        app(Filesystem::class)->delete($this->path($fortress));
    }
}
