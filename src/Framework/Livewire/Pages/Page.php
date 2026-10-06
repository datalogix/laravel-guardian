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
     * Livewire sends every public property back to the browser in the snapshot of
     * the page, and the browser sends it again on every later request of the page.
     * So what the user typed as a secret is cleared once the action used it, also
     * when it failed.
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
