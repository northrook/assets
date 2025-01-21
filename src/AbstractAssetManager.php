<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Assets\Factory\Compiler\AssetReference;
use Core\Assets\Interface\{AssetHtmlInterface, AssetManagerInterface, AssetModelInterface};
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\Assets\Exception\{InvalidAssetTypeException, UndefinedAssetReferenceException};
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Provides the Asset Manager Service to the Framework.
 *
 * Get Asset:
 * - HtmlData `get`
 * - FactoryModel `getModel`
 * - ManifestReference `getReference`
 *
 * Public access:
 * - Locator
 * - Factory
 *
 * @noinspection PhpClassCanBeReadonlyInspection lazy-load using ghost
 */
#[Autodiscover(
    lazy     : true,
    public   : false,
    autowire : true,
)]
class AbstractAssetManager implements AssetManagerInterface
{
    public function __construct(
        public readonly AssetFactory        $factory, // internal
        protected readonly ?CacheInterface  $cache = null,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    final public function getAssetHtml(
        AssetReference|string $asset,
        ?string               $assetID = null,
        array                 $attributes = [],
    ) : ?AssetHtmlInterface {
        try {
            return $this->factory->getAssetHtml( $asset, $assetID, $attributes );
        }
        catch ( InvalidAssetTypeException|UndefinedAssetReferenceException $exception ) {
            $this->logger?->critical(
                $exception->getMessage(),
                ['exception' => $exception],
            );
        }
        return null;
    }

    final public function getAssetModel(
        AssetReference|string $asset,
        ?string               $assetID = null,
    ) : ?AssetModelInterface {
        try {
            return $this->factory->getAssetModel( $asset, $assetID );
        }
        catch ( InvalidAssetTypeException|UndefinedAssetReferenceException $exception ) {
            $this->logger?->critical(
                $exception->getMessage(),
                ['exception' => $exception],
            );
        }
        return null;
    }

    final public function getReference( string $asset ) : ?AssetReference
    {
        try {
            return $this->factory->resolveAssetReference( $asset );
        }
        catch ( UndefinedAssetReferenceException $exception ) {
            $this->logger?->critical(
                $exception->getMessage(),
                ['exception' => $exception],
            );
        }
        return null;
    }
}
