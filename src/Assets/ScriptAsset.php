<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\{AbstractAsset};
use Core\AssetManager\Asset\{Inlinable, Minifier};
use Psr\Cache\CacheItemPoolInterface;
use Support\{JavaScriptMinifier};
use Core\AssetManager\Asset\{Printable};

class ScriptAsset extends AbstractAsset
{
    use Printable, Inlinable, Minifier;

    protected function getMinifier() : JavaScriptMinifier
    {
        return $this->minifier ??= new JavaScriptMinifier(
            cachePool : $this->cache instanceof CacheItemPoolInterface ? $this->cache : null,
            logger    : $this->logger,
        );
    }

    final protected function minify() : self
    {
        if ( $this->minified ) {
            $this->log( 'Already minified', level : 'error' );
            return $this;
        }

        return $this;
    }

    protected function render() : void
    {
        // TODO: Implement render() method.
    }

    protected function build() : void
    {
        // TODO: Implement build() method.
    }
}
