<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Asset;

use Core\Assets\Factory\Compiler\{AbstractAssetModel, BundlableAssetInterface, InlinableAsset, BundlableAsset};
use Core\Assets\Factory\AssetHtml;
use Core\Assets\Interface\AssetHtmlInterface;
use Core\Pathfinder\Path;
use Core\View\Element;
use Northrook\{MinifierInterface, StylesheetMinifier};
use RuntimeException;
use InvalidArgumentException;
use Support\{Normalize};

final class StyleAsset extends AbstractAssetModel implements BundlableAssetInterface
{
    use BundlableAsset, InlinableAsset;

    private readonly Path $publicAssetPath;

    protected function construct() : void
    {
        $this->publicAssetPath = $this->pathfinder->getPath(
            "{$this->publicAssetsKey}/styles/{$this->getReference()->name}.css",
        ) ?? throw new RuntimeException();
    }

    public function render( ?array $attributes = null ) : AssetHtmlInterface
    {
        $compiledCSS = ( new StylesheetMinifier(
            $this->getSources(),
        ) )->minify();

        // $this->prefersInline = true;
        $attributes['asset-name'] = $this->getName();
        $attributes['asset-id']   = $this->assetID();

        $this->publicAssetPath->save( $compiledCSS );

        if ( $this->prefersInline ) {
            $html = (string) new Element(
                tag        : 'style',
                attributes : $attributes,
                content    : $compiledCSS,
            );
        }
        else {
            $url = $this->pathfinder->get( (string) $this->publicAssetPath, $this->publicRootKey );

            if ( ! $url ) {
                throw new InvalidArgumentException(
                    'No URL provided.',
                );
            }

            $attributes['rel']  = 'stylesheet';
            $attributes['href'] = Normalize::url( $url ).$this->version();

            $html = (string) new Element( 'link', $attributes );
        }

        return new AssetHtml(
            $this->getName(),
            $this->assetID(),
            $this->getType(),
            $html,
        );
    }

    /**
     * @param null|MinifierInterface $compiler
     *
     * @return MinifierInterface
     */
    protected function compiler( ?MinifierInterface $compiler = null ) : MinifierInterface
    {
        return $this->compiler ??= $compiler ?? new StylesheetMinifier();
    }
}
