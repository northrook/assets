<?php

declare(strict_types=1);

namespace Core;

// : MUST allow dynamic fetching of valid sources

use Cache\CacheHandler;
use Core\AssetManager\AssetManifest;
use Core\Autowire\Logger;
use Core\Asset\{Meta, Type};
use Core\AssetManager\Assets\StyleAsset;
use Core\Exception\AssetException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * Pathfinder keys:
 * ```
 * dir.assets        = %dir.root%/assets
 * dir.assets.meta   = %dir.core%/var/assets/meta
 * dir.public        = %dir.root%/public
 * dir.public.assets = %dir.root%/public/assets
 * ```
 */
class AssetManager
{
    public const string LOCATOR_ID = 'assets.service_locator';

    public const string MANIFEST_ID = AssetManifest::class;

    use Logger;

    protected readonly CacheHandler $cache;

    /**
     * @param array<array-key,string>    $assetDirectories
     * @param AssetManifest              $manifest
     * @param Pathfinder                 $pathfinder
     * @param null|ServiceLocator<Asset> $serviceLocator
     * @param ?CacheItemPoolInterface    $cache
     */
    final public function __construct(
        protected readonly array           $assetDirectories,
        public readonly AssetManifest      $manifest,
        protected readonly Pathfinder      $pathfinder,
        protected readonly ?ServiceLocator $serviceLocator = null,
        ?CacheItemPoolInterface            $cache = null,
    ) {
        $this->cache = new CacheHandler( $cache, 'asset' );
    }

    /**
     * Accepts
     *
     * @param string $asset
     *
     * @return Asset
     */
    final public function getAsset( string $asset ) : Asset
    {
        // :: Identifier - can be:
        // . final AssetClass | hexdec16
        // :: Can be called as a non-registered by direct path/url
        // :: Assets with an Identifier have persistent Meta
        // :: In Templates, they are called by <asset:Identifier /> for "slot" style access
        // :: but can also be called directly like <link href="assets/style/global.css"..>

        // ?? If $asset is a URL, always assume Detached
        // ?? If $asset is a local path, check for Manifest
        // .. Create ad-hoc Manifests for images etc.
        return match ( true ) {
            // is_path( $asset ) => $this->resolveLocalAsset( $asset ),
            // is_url( $asset )  => $this->resolveRemoteAsset( $asset ),
            default => $this->getRegisteredAsset( $asset ),
        };
    }

    // final protected function resolveLocalAsset( string $asset ) : AssetInterface
    // {
    //     return new DetachedAsset( __METHOD__, new Element( 'local' ) );
    // }

    // final protected function resolveRemoteAsset( string $asset ) : AssetInterface
    // {
    //     return new DetachedAsset( __METHOD__, new Element( 'remote' ) );
    // }

    /**
     * @param class-string<Asset>|string $asset
     *
     * @return Asset
     */
    final public function getRegisteredAsset( string $asset ) : Asset
    {
        if ( ! $this->serviceLocator ) {
            throw new LogicException( 'Service locator is not set.' );
        }

        $meta = $this->manifest->getMeta( $asset );

        if ( \strlen( $asset ) === 16 && \ctype_alnum( $asset ) ) {
            $asset = $meta->get( 'class', Asset::class );
        }

        if ( ! \is_subclass_of( $asset, Asset::class ) ) {
            throw new InvalidArgumentException( 'Class must be a subclass of RegisteredAsset.' );
        }

        if ( ! $this->serviceLocator->has( $asset ) ) {
            throw new InvalidArgumentException( 'AbstractAsset is not registered.' );
        }

        return $this->serviceLocator->get( $asset );
    }

    final public function resolveAssetKey( string|Meta|Asset $from ) : string
    {
        $string = match ( true ) {
            $from instanceof Asset => $from->meta->id,
            $from instanceof Meta  => $from->id,
            default                => $from,
        };

        $length = \strlen( $string );

        if ( \ctype_alnum( $string ) && $length === 16 ) {
            return $string;
        }

        if ( $string[3] === '.' ) {
            $path = $this->pathfinder->get( $string );

            if ( \file_exists( $path ) ) {
                return Meta::getAssetId( $this->resolveAssetClass( $path ), $path );
            }
        }

        dump( \get_defined_vars() );
        return __METHOD__;
    }

    final public function hasAssetMeta( string $key ) : bool
    {
        return \file_exists( 'dir.assets.meta/'.$key.'.php' );
    }

    /**
     * @param string $key
     * @param bool   $nullable
     *
     * @return ($nullable is true ? null|Meta : Meta)
     */
    final public function getAssetMeta( string $key, bool $nullable = false ) : ?Asset
    {
        try {
            return $this->manifest->getMeta( $key );
        }
        catch ( AssetException $exception ) {
            if ( $nullable ) {
                return null;
            }
            throw new AssetException(
                message  : 'AbstractAsset is not registered.',
                previous : $exception,
            );
        }
    }

    /**
     * @param string|Stringable|Type $from
     *
     * @return class-string<Asset>
     */
    final protected function resolveAssetClass( string|Type|Stringable $from ) : string
    {
        return match ( $from instanceof Type ? $from : Type::resolve( $from ) ) {
            Type::STYLE => StyleAsset::class,
            default     => throw new InvalidArgumentException( 'Class must be a subclass of RegisteredAsset.' ),
        };
    }
}
