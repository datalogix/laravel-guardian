<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Enums\Layout;

trait HasLayouts
{
    protected string $layout = Layout::Simple->value;

    protected array $layoutsForPage = [];

    public function layout(string|Layout $layout): static
    {
        $this->layout = $layout instanceof Layout ? $layout->value : $layout;

        return $this;
    }

    public function layoutForPage(string $page, string|Layout|null $layout = null): static
    {
        $this->layoutsForPage[$page] = $layout instanceof Layout ? $layout->value : $layout;

        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function getLayoutForPage(string $page): string
    {
        return $this->layoutsForPage[$page] ?? $this->getLayout();
    }
}
