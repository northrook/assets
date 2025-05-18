<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\{AbstractAsset};
use Core\Asset\{Printable};
use Core\View\Element;
use Core\Asset\{Inlinable, Minifier, Type};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Support\StylesheetMinifier;

class StyleAsset extends AbstractAsset implements Stringable
{
    use Printable, Inlinable, Minifier;

    public const Type TYPE = Type::STYLE;

    protected function build() : void
    {
        // TODO: Implement build() method.
    }

    final protected function getMinifier() : StylesheetMinifier
    {
        return $this->minifier ??= new StylesheetMinifier(
            cachePool : $this->cache instanceof CacheItemPoolInterface ? $this->cache : null,
            logger    : $this->logger,
        );
    }

    final protected function minify() : self
    {
        if ( $this->minified ) {
            return $this;
        }

        foreach ( $this->meta->sources() as $source ) {
            dump( $source );
            // $isPath = is_path( $source );
            // if ( $isPath ) {
            //     if ( \glob( $source ) ) {
            //         $this->getMinifier()->setSource( ...\glob( $source ) ?: [] );
            //     }
            //     elseif ( \file_exists( $source ) ) {
            //         $this->getMinifier()->setSource( $source );
            //     }
            // }
            // else {
            //     $this->getMinifier()->setSource( $source );
            // }
        }

        $this->getMinifier()->minify( $this->meta->id );

        $this->minified = $this->getMinifier()->__toString();

        return $this;
    }

    protected function render() : void
    {
        $this->element->attributes->set( 'asset-id', $this->meta->id );

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
