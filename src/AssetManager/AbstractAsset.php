<?php

namespace Core\AssetManager;

use Cache\{CacheHandler};
use Core\Interface\{AssetInterface, LogHandler, Loggable, SettingsProviderInterface};
use Core\Asset\{Data, Meta, Type};
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
    use LogHandler, CacheHandler, StopwatchProfiler;

    public const Type TYPE = Type::NULL;

    protected readonly Pathfinder $pathfinder;

    protected readonly SettingsProviderInterface $settings;

    protected readonly string $invokedSource;

    public readonly string $source;

    public readonly Type $type;

    public readonly Meta|Data $meta;

    final public function setDependencies(
        Pathfinder                $pathfinder,
        SettingsProviderInterface $settings,
        ?CacheItemPoolInterface   $cache = null,
        ?Stopwatch                $stopwatch = null,
    ) : void {
        $this->pathfinder = $pathfinder;
        $this->settings   = $settings;
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

    /**
     * Get a setting by its key.
     *
     * If no setting is found, but a valid `set` key and `value` is provided, and given the current `user` has relevant permissions, the Setting will be set and saved.
     *
     * @template Setting of null|array<array-key, scalar>|scalar
     *
     * @param string  $setting
     * @param Setting $default
     *
     * @return null|array|bool|float|int|string
     * @phpstan-return Setting
     */
    final public function getSetting(
        string $setting,
        mixed  $default,
    ) : mixed {
        return $this->settings->get( $this->type->key( $setting ), $default );
    }
}
