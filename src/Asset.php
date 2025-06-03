<?php

declare(strict_types=1);

namespace Core;

use Core\Interface\{AssetInterface, Loggable, ProfilerInterface, SettingsInterface};
use Cache\CacheHandler;
use Core\Autowire\Logger;
use Core\Compiler\Hook;
use Core\View\Element\Attributes;
use Core\Compiler\Hook\{OnBuild};
use Core\Asset\{Meta, Type};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Symfony\Component\Stopwatch\Stopwatch;
use function Support\{array_is_associative, normalize_path, normalize_url};

/**
 * The base {@see Asset} class.
 */
abstract class Asset implements AssetInterface, Loggable
{
    use Logger;

    /**
     * The canonical asset type.
     *
     * Checked against the {@see Meta::type()} for validation.
     *
     * @var Type
     */
    public const Type TYPE = Type::NULL;

    protected readonly CacheHandler $cache;

    protected readonly ProfilerInterface $profiler;

    public readonly Meta $meta;

    public readonly Attributes $attributes;

    /**
     * @internal autowired {@see ServiceLocator}
     *
     * @param Pathfinder                            $pathfinder
     * @param null|SettingsInterface                $settings
     * @param null|CacheItemPoolInterface           $cache
     * @param null|bool|ProfilerInterface|Stopwatch $profiler
     */
    final public function __construct(
        protected readonly Pathfinder         $pathfinder,
        private readonly ?SettingsInterface   $settings = null,
        ?CacheItemPoolInterface               $cache = null,
        null|bool|Stopwatch|ProfilerInterface $profiler = null,
    ) {
        \assert(
            $this::TYPE != Type::NULL,
            $this::class." must define 'public const Type' matching its expected type.",
        );

        $this->profiler = Profiler::from(
            profiler : $profiler,
            category : $this::TYPE->key(),
        );
        $this->cache = new CacheHandler(
            adapter     : $cache,
            prefix      : $this::TYPE->key(),
            expiration  : $this->getSetting( 'cache.expiration', 14_400 ),
            deferCommit : $this->getSetting( 'cache.defer', true ),
            profiler    : $profiler,
        );
    }

    /**
     * @internal
     * @return void
     */
    protected function build() : void {}

    /**
     * - Called by {@see self::__invoke()}
     * - Parses and compiles all provided sources.
     * - Returns a new instance of {@see self}
     *
     * @internal
     *
     * @param Meta|string|Stringable $source
     * @param mixed                  ...$arguments
     *
     * @return $this
     */
    final protected function initialize(
        string|Stringable|Meta $source,
        mixed               ...$arguments,
    ) : self {
        \assert(
            array_is_associative( $arguments ),
            'Invoked Asset arguments must use named arguments.',
        );

        $meta = $source instanceof Meta
                ? $source
                : Meta::create( $this::class, $source );

        $meta->add( ...$arguments );

        $asset             = clone $this;
        $asset->meta       = $meta;
        $asset->attributes = new Attributes();

        $asset->build();

        foreach ( Hook::get( $asset, OnBuild::class ) as $hook ) {
            if ( ! $hook->fired ) {
                $asset->{$hook->action}( ...$hook->arguments );
            }

            $hook->fired = true;
        }

        return $asset;
    }

    final public function getPath() : string
    {
        return $this->meta->resolve(
            'public.path',
            function() : string {
                $fragments = [
                    $this->pathfinder->get( 'dir.public.assets' ),
                    $this::TYPE->value,
                    $this->fileName(),
                ];
                return normalize_path( $fragments );
            },
        );
    }

    final public function getUrl(
        bool $absolute = false,
        bool $version = false,
    ) : string {
        $url = [];

        if ( $absolute ) {
            $url[] = $this->getSetting(
                'site.url',
                (string) ( $_SERVER['SERVER_NAME'] ?? 'example.com' ),
            );
        }

        $url[] = $this->pathfinder->get( $this->getPath(), 'dir.public' );

        if ( $version ) {
            $url[] = '?v='.$this->getVersion();
        }

        return normalize_url( $url );
    }

    final public function getVersion() : string
    {
        return $this->meta->version;
    }

    final protected function fileName( ?string $ext = null ) : string
    {
        if ( $ext ) {
            return $this->meta->fileName.'.'.\trim( $ext, '.' );
        }

        return $this->meta->baseName;
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
                    "asset.{$this::TYPE->value}.{$key}",
                    $default,
                )
                : $default;
    }
}
