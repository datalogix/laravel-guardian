<?php

namespace Datalogix\Guardian\Http\Livewire;

use Illuminate\Support\Str;
use Livewire\Component;

abstract class Page extends Component
{
    protected static string $layout = 'guardian::layouts.split';

    public function render()
    {
        return view($this->getView(), $this->getViewData())
            ->layout($this->getLayout(), $this->getLayoutData());
    }

    public function getView(): string
    {
        return $this->view ??= Str::of(static::class)
            ->replace('Datalogix\Guardian\Http\Livewire\\', '')
            ->kebab()
            ->replace('\-', '.')
            ->prepend('guardian::')
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
