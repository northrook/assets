<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\Asset;
use Core\AssetManager\Asset\Inlinable;
use Core\AssetManager\Asset\Printable;
use Core\View\Element;
use Core\AssetManager\Asset\{Minifier};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Support\StylesheetMinifier;

class StyleAsset extends Asset implements Stringable
{
    use Printable, Inlinable, Minifier;

    public function getMinifier() : StylesheetMinifier
    {
        return $this->minifier ??= new StylesheetMinifier(
            cachePool : $this->cache instanceof CacheItemPoolInterface ? $this->cache : null,
            logger    : $this->logger,
        );
    }

    protected function build() : void
    {
        $this->element->attributes->set( 'asset-id', $this->meta->id );
    }

    protected function render() : void
    {
        if ( $this->meta->get( 'prefersInline', true ) ) {
            $this->getInlineHtml();
        }
        else {
            $this->getStyleHtml();
        }
    }

    public function getInlineHtml() : Element
    {
        $this->element->tag->set( 'style' );
        $this->element->content( ['inline' => 'CSS'] );

        return $this->element;
    }

    public function getStyleHtml() : Element
    {
        $this->element->tag->set( 'link' );
        $this->element->attributes(
            href : 'CSS',
            rel  : 'stylesheet',
        );

        return $this->element;
    }
}
