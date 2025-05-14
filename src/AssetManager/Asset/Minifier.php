<?php

declare(strict_types=1);

namespace Core\AssetManager\Asset;

use Core\AssetManager\Asset;
use Support\Minify;

/**
 * @phpstan-require-extends Asset
 */
trait Minifier
{
    private readonly Minify $minifier;

    abstract protected function getMinifier() : Minify;
}
