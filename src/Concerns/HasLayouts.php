<?php

namespace Datalogix\Guardian\Concerns;

use BackedEnum;

trait HasLayouts
{
    protected ?string $layout = null;

    protected array $layoutsForPage = [];

    public function layout(string|BackedEnum $layout): static
    {
        $this->layout = $this->layoutName($layout);

        return $this;
    }

    public function layoutForPage(string $page, string|BackedEnum|null $layout = null): static
    {
        $this->layoutsForPage[$page] = $layout === null ? null : $this->layoutName($layout);

        return $this;
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function getLayoutForPage(string $page): ?string
    {
        return $this->layoutsForPage[$page] ?? $this->getLayout();
    }

    protected function layoutName(string|BackedEnum $layout): string
    {
        return $layout instanceof BackedEnum ? (string) $layout->value : $layout;
    }
}
