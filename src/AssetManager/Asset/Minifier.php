<?php

declare(strict_types=1);

namespace Core\AssetManager\Asset;

use Core\AssetManager\AbstractAsset;
use Support\Minify;

/**
 * @phpstan-require-extends AbstractAsset
 */
trait Minifier
{
    private readonly Minify $minifier;

    protected ?string $minified = null;

    abstract protected function getMinifier() : Minify;

    abstract protected function minify() : self;

    final public function getMinified() : ?string
    {
        return $this->minify()->minified;
    }
}
