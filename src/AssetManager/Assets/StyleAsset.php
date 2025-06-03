<?php

declare(strict_types=1);

namespace Core\AssetManager\Assets;

use Core\Asset;
use Core\Compiler\Hook\OnBuild;
use Core\Asset\{Inlinable, Minifiable, Printable, Type};
use Stringable;
use Support\StylesheetMinifier;
use function Support\file_save;

abstract class StyleAsset extends Asset implements Inlinable, Minifiable, Stringable
{
    use Printable;

    public const Type TYPE = Type::STYLE;

    protected readonly StylesheetMinifier $minifier;

    final public function __toString() : string
    {
        $this->attributes
            ->set( 'asset-name', $this->meta->baseName )
            ->set( 'asset-id', $this->meta->id );

        return $this->prefersInline() ? $this->inlineHtml() : $this->linkHtml();
    }

    protected function linkHtml() : string
    {
        $this->attributes
            ->set( 'href', $this->getUrl() )
            ->set( 'rel', 'stylesheet' );

        return <<<HTML
            <link{$this->attributes}/>
            HTML;
    }

    protected function inlineHtml() : string
    {
        return <<<HTML
            <style{$this->attributes}>{$this->minifier->getString()}</style>
            HTML;
    }

    final protected function build() : void
    {
        $this->minifier = new StylesheetMinifier(
            $this->cache->getCacheAdapter(),
            $this->logger ?? null,
        );
    }

    final protected function render() : self
    {
        $this->minifier
            ->setSource( $this->meta->source )
            ->bundleImports( $this->mergeImports() )
            ->minify()
            ->getString();

        if ( $this->minifier->usedCache() === false || ! \file_exists( $this->getPath() ) ) {
            file_save( $this->getPath(), $this->minifier->getString() );
        }

        return $this;
    }

    #[OnBuild]
    final public function prefersInline( ?bool $set = null ) : bool
    {
        if ( $set !== null ) {
            $this->meta->assign( 'prefersInline', $set, true );
        }

        return (bool) $this->meta->get(
            'prefersInline',
            $set ?? $this->getSetting( 'prefersInline', true ),
        );
    }

    #[OnBuild]
    final public function mergeImports( ?bool $set = null ) : bool
    {
        if ( $set !== null ) {
            $this->meta->assign( 'mergeImports', $set, true );
        }

        return (bool) $this->meta->get(
            'mergeImports',
            $set ?? $this->getSetting( 'mergeImports', true ),
        );
    }
}
