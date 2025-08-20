<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Enums\Layout;
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
        return $this->view ??= 'guardian::'.$this->getPageName();
    }

    protected function getPageName()
    {
        return $this->pageName ??= Str::of(static::class)
            ->afterLast('\\')
            ->kebab()
            ->toString();
    }

    protected function getLayout(): string|Layout
    {
        return static::$layout ?? Guardian::getLayoutForPage($this->getPageName());
    }

    protected function getViewData(): array
    {
        return [];
    }

    protected function getLayoutData(): array
    {
        return [];
    }
}
