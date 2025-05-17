<?php

namespace Core\AssetManager;

use Cache\{CacheHandler};
use Core\Interface\{AssetInterface, LogHandler, Loggable};
use Core\Compiler\Hook;
use Core\Compiler\Hook\SetDependencies;
use Core\AssetManager\Asset\{Meta, Type};
use Core\Pathfinder;
use Core\Symfony\DependencyInjection\SettingsAccessor;
use Core\Profiler\{StopwatchProfiler};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Symfony\Component\Stopwatch\Stopwatch;
use function Support\slug;

abstract class AbstractAsset implements AssetInterface, Loggable
{
    use LogHandler, CacheHandler, StopwatchProfiler, SettingsAccessor;

    protected readonly string $invokedSource;

    public readonly string $source;

    public readonly Type $type;

    public readonly Meta $meta;

    public function __construct(
            protected readonly Pathfinder $pathfinder,
            ?CacheItemPoolInterface       $cache = null,
            ?Stopwatch                    $stopwatch = null,
    )
    {
        $this->assignProfiler(
                profiler : $stopwatch,
                category : 'asset',
        );
        $this->assignCacheAdapter(
                adapter    : $cache,
                prefix     : 'asset',
                defer      : true,
                expiration : 14_400, // 4 hours
        );
    }

    /**
     * Returns a new instance of {@see self}.
     *
     * @param string|Stringable  $source
     * @param ?Meta              $meta
     *
     * @return $this
     */
    final public function __invoke(
            string | Stringable $source,
            ?Meta $meta = null,
    ) : self
    {
        $source = (string) $source;
        $slug   = slug( $source );
        $type   = Type::from( $source );

        if ( $asset = $this->getCache( $slug ) ) {
            dump( $asset );
        }

        // ? OnRender - cache this asset: serialize data, key using hashed $path
        // : __invoke - if cached, unserialize; validate ? return : parse

        // set cache hash here
        $asset                = clone $this;
        $asset->invokedSource = $source;
        $asset->type          = $type;
        $asset->meta          = $meta ?? Meta::create( $this::class, $source );

        Hook::fire( $asset, SetDependencies::class );
        $asset->build();
        $this->setCache( $slug, $asset );
        return $asset;
    }

    /**
     * Parses and compiles all provided sources.
     *
     * Called by {@see self::__invoke()}.
     */
    abstract protected function build() : void;

    public function getPath() : string
    {
        return __METHOD__;
    }

    public function getUrl(
            bool $relative = false,
            bool $version = false,
    ) : string
    {
        return __METHOD__;
    }

    final public function getVersion() : string
    {
        return __METHOD__;
    }
}
