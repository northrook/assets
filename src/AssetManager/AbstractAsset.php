<?php

namespace Core\AssetManager;

use Cache\CachePoolTrait;
use Core\Interface\{AssetInterface, LogHandler, Loggable};
use Core\Compiler\Hook;
use Core\Pathfinder;
use Core\Profiler\{StopwatchProfiler};
use Psr\Cache\CacheItemPoolInterface;
use Stringable;
use Symfony\Component\Stopwatch\Stopwatch;

class AbstractAsset implements AssetInterface, Loggable
{
    use LogHandler, StopwatchProfiler, CachePoolTrait;

    private bool $returnClone = true;

    protected readonly string $invokedPath;

    public function __construct(
        protected readonly Pathfinder $pathfinder,
        ?CacheItemPoolInterface       $cache = null,
        ?Stopwatch                    $stopwatch = null,
    ) {
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

    final public function __invoke(
        string|Stringable $path,
    ) : self {
        // set cache hash here
        $asset              = clone $this;
        $asset->invokedPath = (string) $path;
        Hook::fire( $asset, Hook\OnBuild::class );
        return $asset;
    }

    public function getPath() : string
    {
        return __METHOD__;
    }

    public function getUrl(
        bool $relative = false,
        bool $version = false,
    ) : string {
        return __METHOD__;
    }

    final public function getVersion() : string
    {
        return __METHOD__;
    }
}
