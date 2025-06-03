<?php

declare(strict_types=1);

namespace Core\AssetManager\Assets;

use Core\Asset;
use Core\Compiler\Hook\OnBuild;
use Core\Asset\{Inlinable, Minifiable, Printable, Type};
use Stringable;
use Support\JavaScriptMinifier;
use function Support\file_save;

class ScriptAsset extends Asset implements Inlinable, Minifiable, Stringable
{
    use Printable;

    public const Type TYPE = Type::SCRIPT;

    protected readonly JavaScriptMinifier $minifier;

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
            ->set( 'src', $this->getUrl() )
            ->set( 'defer', true );

        return <<<HTML
            <script{$this->attributes}></script>
            HTML;
    }

    protected function inlineHtml() : string
    {
        return <<<HTML
            <script{$this->attributes}>{$this->minifier->getString()}</script>
            HTML;
    }

    final protected function build() : void
    {
        $this->minifier = new JavaScriptMinifier(
            $this->cache->getCacheAdapter(),
            $this->logger ?? null,
            $this->profiler,
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
