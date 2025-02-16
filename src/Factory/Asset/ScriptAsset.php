<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Asset;

use Core\Assets\Factory\Compiler\{AbstractAssetModel, BundlableAssetInterface, InlinableAsset, JavascriptAssetCompiler};
use Core\Assets\Factory\AssetHtml;
use Core\Assets\Interface\AssetHtmlInterface;
use Core\Pathfinder\Path;
use Core\View\Element;
use Support\Normalize;
use RuntimeException;
use Stringable;

final class ScriptAsset extends AbstractAssetModel implements BundlableAssetInterface
{
    use InlinableAsset;

    /** @var array{before: Path[]|string[], import: Path[], source: ?Path, after: Path[]|string[]} */
    protected array $sources = [
        'before' => [],
        'import' => [],
        'source' => null,
        'after'  => [],
    ];

    private readonly Path $publicAssetPath;

    protected function compile() : string
    {
        $sources = [];

        foreach ( $this->getReference()->getSources() as $source ) {
            $sources[] = ( new JavascriptAssetCompiler( $source ) )->compile();
        }

        return __METHOD__.'::DEPRECATED';
    }

    protected function construct() : void
    {
        $this->publicAssetPath = $this->pathfinder->getPath(
            "{$this->publicAssetsKey}/scripts/{$this->getReference()->name}.js",
        ) ?? throw new RuntimeException();
    }

    public function render( ?array $attributes = null ) : AssetHtmlInterface
    {
        $compiledJS = $this->compile();

        $attributes['asset-name'] = $this->getName();
        $attributes['asset-id']   = $this->assetID();

        $this->publicAssetPath->save( $compiledJS );

        if ( $this->prefersInline ) {
            $html = (string) new Element(
                tag        : 'script',
                attributes : $attributes,
                content    : $compiledJS,
            );
        }
        else {
            $url = $this->pathfinder->get( (string) $this->publicAssetPath, $this->publicRootKey );

            $attributes['src'] = Normalize::url( $url ).$this->version();

            $html = (string) new Element( 'script', $attributes );
        }

        return new AssetHtml(
            $this->getName(),
            $this->assetID(),
            $this->getType(),
            $html,
        );
    }

    final public function addSource( string|Stringable $source, bool $before = false ) : self
    {
        if ( $before ) {
            $this->sources['before'][] = $source instanceof Path ? $source : new Path( $source );
        }
        else {
            $this->sources['after'][] = $source instanceof Path ? $source : new Path( $source );
        }
        return $this;
    }
}
