<?php

declare(strict_types=1);

namespace Core\AssetManager;

// : The Asset should likely create the Meta
// : That way both Registered and Detached generate from the same place
// ? Storage Backed Meta must be optional
// . Use in-memory unless called by the AssetManager or manually at runtime

// . AssetID will for registered generated from class+name, else class+name+sources
// . SourceResolver URL will use ~ as a query divider - see current ImageAsset for implementation

use Cache\CachePoolTrait;
use Core\Interface\AssetInterface;
use Core\Pathfinder;
use Core\Symfony\DependencyInjection\SettingsAccessor;
use Core\Compiler\{Hook};
use Core\Profiler\Interface\Profilable;
use Core\Profiler\ProfilerTrait;
use Core\Interface\{LogHandler, Loggable};
use Core\AssetManager\Asset\{Meta};
use Psr\Cache\CacheItemPoolInterface;
use function Support\{normalize_url};
use const Time\HOUR_4;

// : All classes extending the Asset class will be registered as Action/Service
// . Only classes annotated with #[Asset] will be configurable at compile time

/**
 */
abstract class Asset implements AssetInterface, Loggable, Profilable
{
    use LogHandler, ProfilerTrait, CachePoolTrait, SettingsAccessor;

    protected readonly Pathfinder $pathfinder;

    /** @var ?string */
    protected ?string $publicPath = null;

    public readonly Meta $meta;

    /**
     * @param Meta                        $meta
     * @param Pathfinder                  $pathfinder
     * @param null|CacheItemPoolInterface $cache
     *
     * @return $this
     */
    final public function setDependencies(
        Meta                    $meta,
        Pathfinder              $pathfinder,
        ?CacheItemPoolInterface $cache = null,
    ) : self {
        $this->meta       = $meta;
        $this->pathfinder = $pathfinder;

        $this->assignCacheAdapter(
            adapter    : $cache,
            prefix     : 'manifest',
            defer      : $this->getSetting( 'asset.cache.defer', true ),
            expiration : $this->getSetting( 'asset.cache.expiration', HOUR_4 ),
        );

        Hook::fire(
            $this,
            Hook\SetDependencies::class,
            Hook\OnBuild::class,
        );

        return $this;
    }

    /**
     * @return array<array-key,SourceResolver>
     */
    final public function getSources() : array
    {
        return \array_map(
            fn( $source ) => new SourceResolver( $source, $this->pathfinder->get( 'dir.assets' ) ),
            $this->meta->sources(),
        );
    }

    public function getPath() : string
    {
        return __METHOD__;
    }

    public function getUrl(
        bool $relative = false,
        bool $version = false,
    ) : string {
        return normalize_url( $this->meta->url );
    }

    final public function getVersion() : string
    {
        return $this->meta->version;
    }
}
