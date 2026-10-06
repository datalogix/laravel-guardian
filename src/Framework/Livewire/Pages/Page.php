<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

use Closure;
use Datalogix\Guardian\Framework\Livewire\Layout;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Guardian;
use Illuminate\Support\Str;
use Livewire\Component;

abstract class Page extends Component
{
    protected static string $layout;

    protected string $pageName;

    public function render()
    {
        return view($this->getView(), $this->getViewData())
            ->layout($this->getLayout(), $this->getLayoutData());
    }

    protected function getView(): string
    {
        return $this->view ??= app(LivewireAdapter::class)->viewFor($this->getPageName(), Guardian::getCurrentOrDefaultFortress());
    }

    protected function getPageName()
    {
        return $this->pageName ??= Str::of(static::class)
            ->afterLast('\\')
            ->kebab()
            ->toString();
    }

    protected function getLayout(): string
    {
        return static::$layout ?? Guardian::getLayoutForPage($this->getPageName()) ?? Layout::Simple->value;
    }

    protected function getViewData(): array
    {
        return [];
    }

    protected function getLayoutData(): array
    {
        return [];
    }

    /**
     * Livewire sends public properties back to the browser, so secrets are cleared after use.
     */
    protected function forgettingSecrets(Closure $action, string ...$properties): mixed
    {
        try {
            return $action();
        } finally {
            $this->reset(...$properties);
        }
    }
}
