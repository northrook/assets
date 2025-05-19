<?php

namespace Core\AssetManager;

use Cache\{CacheHandler};
use Core\Interface\{AssetInterface, LogHandler, Loggable};
use Core\Autowire\SettingsAccessor;
use Core\Asset\{Meta, Type};
use Core\Compiler\Hook;
use Core\Compiler\Hook\{OnBuild, SetDependencies};
use Core\Pathfinder;
use Core\Profiler\{StopwatchProfiler};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Symfony\Component\Stopwatch\Stopwatch;
use function Support\slug;

abstract class AbstractAsset implements AssetInterface, Loggable
{
    use SettingsAccessor,
        LogHandler,
        CacheHandler,
        StopwatchProfiler;

    public const Type TYPE = Type::NULL;

    protected readonly Pathfinder $pathfinder;

    protected readonly string $invokedSource;

    public readonly string $source;

    public readonly Type $type;

    public readonly Meta $meta;

    final public function setDependencies(
        Pathfinder              $pathfinder,
        ?CacheItemPoolInterface $cache = null,
        ?Stopwatch              $stopwatch = null,
    ) : void {
        $this->pathfinder = $pathfinder;
        $this->assignProfiler(
            profiler : $stopwatch,
            category : 'asset',
        );
        $this->assignCacheAdapter(
            adapter    : $cache,
            prefix     : 'asset',
            defer      : true,
            expiration : 14_400, // 4 hours
            stopwatch  : $stopwatch,
        );
        Hook::fire( $this, SetDependencies::class );
    }

    /**
     * Returns a new instance of {@see self}.
     *
     * @param string|Stringable $source
     * @param ?Meta             $meta
     *
     * @return $this
     */
    final public function __invoke(
        string|Stringable $source,
        ?Meta             $meta = null,
    ) : self {
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

        Hook::fire( $asset, OnBuild::class );

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

    final public function getUrl(
        bool $relative = false,
        bool $version = false,
    ) : string {
        return __METHOD__;
    }

    final public function getVersion() : string
    {
        return __METHOD__;
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
}
