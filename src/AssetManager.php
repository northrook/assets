<?php

declare(strict_types=1);

namespace Core;

// : MUST allow dynamic fetching of valid sources

use Cache\CachePoolTrait;
use Core\Asset\Meta;
use Core\AssetManager\{AssetDefinition, AssetInterface, DetachedAsset};
use Core\Exception\AssetException;
use Core\Interface\LazyService;
use Core\View\Element;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use InvalidArgumentException;
use LogicException;
use function Support\{is_path, is_url};

class AssetManager implements LazyService, LoggerAwareInterface
{
    use CachePoolTrait;

    protected ?LoggerInterface $logger = null;

    /**
     * @param string                               $manifestDirectory
     * @param Pathfinder                           $pathfinder
     * @param null|ServiceLocator<AssetDefinition> $serviceLocator
     * @param ?CacheItemPoolInterface              $cache
     */
    final public function __construct(
        public readonly string             $manifestDirectory,
        protected readonly Pathfinder      $pathfinder,
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
        return match ( true ) {
            is_path( $asset ) => $this->resolveLocalAsset( $asset ),
            is_url( $asset )  => $this->resolveRemoteAsset( $asset ),
            default           => $this->getRegisteredAsset( $asset ),
        };
    }

    final protected function resolveLocalAsset( string $asset ) : AssetInterface
    {
        return new DetachedAsset( __METHOD__, new Element( 'local' ) );
    }

    final protected function resolveRemoteAsset( string $asset ) : AssetInterface
    {
        return new DetachedAsset( __METHOD__, new Element( 'remote' ) );
    }

    /**
     * @param class-string<AssetDefinition>|string $asset
     *
     * @return AssetDefinition
     */
    final public function getRegisteredAsset( string $asset ) : AssetDefinition
    {
        if ( ! $this->serviceLocator ) {
            throw new LogicException( 'Service locator is not set.' );
        }

        if ( \strlen( $asset ) === 16 && \ctype_alnum( $asset ) ) {
            $asset = $this
                ->getAssetMeta( $asset )
                ->get( 'class', AssetInterface::class );
        }

        if ( ! \is_subclass_of( $asset, AssetDefinition::class ) ) {
            throw new InvalidArgumentException( 'Class must be a subclass of RegisteredAsset.' );
        }

        if ( ! $this->serviceLocator->has( $asset ) ) {
            throw new InvalidArgumentException( 'Asset is not registered.' );
        }

        return $this->serviceLocator->get( $asset );
    }

    final public function hasAssetMeta( string $key ) : bool
    {
        return \file_exists( $this->manifestDirectory.'/'.$key.'.php' );
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
