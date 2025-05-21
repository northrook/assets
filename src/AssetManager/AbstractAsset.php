<?php

namespace Core\AssetManager;

use Cache\{CacheHandler};
use Core\Interface\{AssetInterface, LogHandler, Loggable, SettingsProviderInterface};
use Core\Asset\{Meta, Type};
use Core\Compiler\Hook;
use Core\Compiler\Hook\{OnBuild, SetDependencies};
use Core\Pathfinder;
use Core\Profiler\{ClerkProfiler, StopwatchProfiler};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Symfony\Component\Stopwatch\Stopwatch;
use function Support\slug;

/**
 */
abstract class AbstractAsset implements AssetInterface, Loggable
{
    use LogHandler,
        StopwatchProfiler;

    public const Type TYPE = Type::NULL;

    protected readonly CacheHandler $cache;

    protected readonly ?ClerkProfiler $profiler;

    protected readonly string $invokedSource;

    public readonly Type $type;

    public readonly Meta $meta;

    /**
     * @param Pathfinder                     $pathfinder
     * @param null|SettingsProviderInterface $settings
     * @param null|CacheItemPoolInterface    $cache
     * @param null|Stopwatch                 $stopwatch
     */
    public function __construct(
        protected readonly Pathfinder               $pathfinder,
        private readonly ?SettingsProviderInterface $settings = null,
        ?CacheItemPoolInterface                     $cache = null,
        ?Stopwatch                                  $stopwatch = null,
    ) {
        \assert(
            $this::TYPE != Type::NULL,
            $this::class." must define 'public const Type' matching its expected type.",
        );

        $this->profiler = ClerkProfiler::from(
            profiler : $stopwatch,
            category : $this::TYPE->key(),
        );
        $this->cache = new CacheHandler(
            adapter     : $cache,
            prefix      : $this::TYPE->key(),
            expiration  : $this->getSetting( 'cache.expiration', 14_400 ),
            deferCommit : $this->getSetting( 'cache.defer', true ),
            stopwatch   : $stopwatch,
        );

        $this->meta = Meta::new( $this::class );

        Hook::fire( $this, SetDependencies::class );
    }

    /**
     * Returns a new instance of {@see self}.
     *
     * @param Meta|string|Stringable $source
     *
     * @return $this
     */
    public function __invoke(
        string|Stringable|Meta $source,
    ) : self {
        if ( $source instanceof Meta ) {
            $meta   = $source;
            $source = $meta->source;
            $this->meta->import( $meta );
        }

        $slug = slug( $source );
        $type = Type::from( $source );

        if ( $asset = $this->cache->get( $slug ) ) {
            dump( $asset );
        }

        // ? OnRender - cache this asset: serialize data, key using hashed $path
        // : __invoke - if cached, unserialize; validate ? return : parse

        // set cache hash here
        $asset                = clone $this;
        $asset->invokedSource = $source;
        $asset->type          = $type;

        Hook::fire( $asset, OnBuild::class );

        $asset->build();
        $this->cache->set( $slug, $asset );
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

    /**
     * @template Setting of null|array<array-key, scalar>|scalar
     *
     * @param string  $key
     * @param Setting $default
     *
     * @return Setting
     */
    final protected function getSetting(
        string $key,
        mixed  $default,
    ) : mixed {
        return isset( $this->settings )
                ? $this->settings->get(
                    slug( "asset.{$this::TYPE->name}.{$key}", '.' ),
                    $default,
                )
                : $default;
    }
}
