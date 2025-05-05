<?php

declare(strict_types=1);

namespace Core;

// : MUST allow dynamic fetching of valid sources

use Cache\CachePoolTrait;
use Core\Asset\Meta;
use Core\AssetManager\{AssetDefinition, AssetInterface};
use Core\Exception\AssetException;
use Core\Interface\LazyService;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use InvalidArgumentException;
use LogicException;

class AssetManager implements LazyService, LoggerAwareInterface
{
    use CachePoolTrait;

    protected ?LoggerInterface $logger = null;

    /**
     * @param string                               $manifestDirectory
     * @param null|ServiceLocator<AssetDefinition> $serviceLocator
     * @param ?CacheItemPoolInterface              $cache
     */
    final public function __construct(
        public readonly string             $manifestDirectory,
        protected readonly ?ServiceLocator $serviceLocator = null,
        ?CacheItemPoolInterface            $cache = null,
    ) {
        $this->assignCacheAdapter( $cache, 'assets' );
    }

    final public function getAsset( string $asset ) : AssetInterface
    {
        // ?? If $asset is a URL, always assume Detached
        // ?? If $asset is a local path, check for Manifest
        // .. Create ad-hoc Manifests for images etc.
        return $this->getAssetDefinition( $asset );
    }

    /**
     * @param class-string<AssetDefinition>|string $asset
     *
     * @return AssetDefinition
     */
    final public function getAssetDefinition( string $asset ) : AssetDefinition
    {
        if ( ! $this->serviceLocator ) {
            throw new LogicException( 'Service locator is not set.' );
        }

        if ( \strlen( $asset ) === 16 && \ctype_alnum( $asset ) ) {
            $manifestPath = $this->manifestDirectory.'/'.$asset.'.php';

            if ( ! \file_exists( $manifestPath ) ) {
                throw new InvalidArgumentException( 'Asset is not registered.' );
            }

            $asset = $this->getAssetMeta( $asset )->get( 'class', AssetInterface::class );
        }

        if ( ! \is_subclass_of( $asset, AssetDefinition::class ) ) {
            throw new InvalidArgumentException( 'Class must be a subclass of RegisteredAsset.' );
        }

        if ( ! $this->serviceLocator->has( $asset ) ) {
            throw new InvalidArgumentException( 'Asset is not registered.' );
        }

        return $this->serviceLocator->get( $asset );
    }

    /**
     * @param string $key
     * @param bool   $nullable
     *
     * @return ($nullable is true ? null|Meta : Meta)
     */
    final public function getAssetMeta( string $key, bool $nullable = false ) : ?Meta
    {
        $path = $this->manifestDirectory.'/'.$key.'.php';
        if ( \file_exists( $path ) ) {
            return require $path;
        }

        if ( $nullable ) {
            return null;
        }

        throw new AssetException( 'Asset is not registered.' );
    }

    /**
     * Sets a logger.
     *
     * @internal
     *
     * @param ?LoggerInterface $logger
     */
    final public function setLogger( ?LoggerInterface $logger ) : void
    {
        $this->logger = $logger;
    }
}
