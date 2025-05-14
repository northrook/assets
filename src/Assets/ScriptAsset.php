<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\Asset;
use Core\AssetManager\Asset\Inlinable;
use Core\AssetManager\Asset\Minifier;
use Psr\Cache\CacheItemPoolInterface;
use Support\{JavaScriptMinifier};
use Core\AssetManager\Asset\{Printable};

class ScriptAsset extends Asset
{
    use Printable, Inlinable, Minifier;

    protected function getMinifier() : JavaScriptMinifier
    {
        return $this->minifier ??= new JavaScriptMinifier(
            cachePool : $this->cache instanceof CacheItemPoolInterface ? $this->cache : null,
            logger    : $this->logger,
        );
    }

    protected function build() : void
    {
        // TODO: Implement build() method.
    }

    protected function render() : void
    {
        // TODO: Implement render() method.
    }
}
