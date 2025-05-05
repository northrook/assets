<?php

declare(strict_types=1);

namespace Core\AssetManager;

use Cache\CachePoolTrait;
use Core\Asset\{Meta, Type};
use Core\Pathfinder;
use Core\Symfony\DependencyInjection\SettingsAccessor;
use Core\View\Element;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};
use const Time\HOUR_4;

// @requires - anywhere in the stack
// @require  - parent _must_ implement/override

/**
 * @require-method  __construct()
 * @require-method  self __invoke()
 */
abstract class AssetDefinition implements AssetInterface, LoggerAwareInterface
{
    use CachePoolTrait, SettingsAccessor;

    protected readonly Pathfinder $pathfinder;

    protected readonly ?LoggerInterface $logger;

    protected Element $element;

    public readonly Type $type;

    public readonly Meta $meta;

    /**
     * :: __construct is handled by each extending class
     * .. Autowired by the DependencyInjection extension
     *
     * Initialize serves as a runtime __construct hook.
     *
     * @return $this
     */
    protected function initialize() : self
    {
        return $this;
    }

    /**
     * Called by the {@see AssetManager} when retrieving an Asset.
     *
     * @internal
     *
     * @return $this
     */
    public function build() : self
    {
        return $this;
    }

    public function getHtml() : string
    {
        return $this->getElement()->__toString();
    }

    final public function __toString() : string
    {
        // check if build() has been called
        return $this->getHtml();
    }

    /**
     * Handled by the {@see RegisterAssetsPass} during Container compilation.
     *
     * @internal
     *
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
        \assert(
            $this->hasDependencies() === false,
            __METHOD__.'() must only be called once.',
        );

        $this->meta       = $meta;
        $this->pathfinder = $pathfinder;

        $this->assignCacheAdapter(
            adapter    : $cache,
            prefix     : 'manifest',
            defer      : $this->getSetting( 'asset.cache.defer', true ),
            expiration : $this->getSetting( 'asset.cache.expiration', HOUR_4 ),
        );

        return $this->initialize();
    }

    final public function setLogger( ?LoggerInterface $logger ) : void
    {
        $this->logger = $logger;
    }

    public function getVersion() : string
    {
        return (string) $this->meta->getVersion();
    }

    public function getAssetId() : string
    {
        return (string) $this->meta->getAssetId();
    }

    /**
     * Returns an array of all sources used to build this Asset.
     *
     * @return array<array-key, string>
     */
    public function getSources() : array
    {
        return $this->meta->getSources();
    }

    /**
     * @param mixed ...$attributes
     */
    abstract public function getElement( mixed ...$attributes ) : Element;

    final protected function fileName( ?string $ext = null ) : string
    {
        $fileName = \str_replace( '.', '/', $this->meta->getName() );

        if ( $ext ) {
            $fileName .= '.'.\trim( $ext, '.' );
        }

        return $fileName;
    }

    private function hasDependencies() : bool
    {
        return isset( $this->sources, $this->pathfinder, $this->cache );
    }
}
