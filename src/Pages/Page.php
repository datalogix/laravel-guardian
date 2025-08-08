<?php

namespace Datalogix\Fortress\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;

abstract class Page extends Component
{
    protected static string $layout = 'laravel-fortress::components.layout.page';

    public function render(): View
    {
        return view($this->getView(), $this->getViewData())
            ->layout($this->getLayout(), $this->getLayoutData());
    }

    public function getView(): string
    {
        return $this->view ??= str(static::class)
            ->replace('Datalogix\Fortress\\', '')
            ->kebab()
            ->replace('\-', '.')
            ->prepend('laravel-fortress::')
            ->toString();
    }

    public function getLayout(): string
    {
        return static::$layout;
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
