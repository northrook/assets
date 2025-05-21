<?php

declare(strict_types=1);

namespace Core\Asset;

use Core\AssetManager\AbstractAsset;
use Support\Minify;

/**
 * @template T_Minify of Minify
 *
 * @phpstan-require-extends AbstractAsset
 */
trait Minifier
{
    /** @var Minify<T_Minify> */
    private readonly Minify $minifier;

    protected ?string $minified = null;

    /**
     * @return Minify<T_Minify>
     */
    abstract protected function getMinifier() : Minify;

    abstract protected function minify() : self;

    final public function getMinified() : ?string
    {
        return $this->minify()->minified;
    }
}
