<?php

declare(strict_types=1);

namespace Core\AssetManager;

use Core\View\Element;
use LogicException;

/**
 * Handles non-registered assets.
 */
class DetachedAsset implements AssetInterface
{
    public function __construct(
        protected string  $source,
        protected Element $element,
    ) {}

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

    public function getSourcePath() : string
    {
        return $this->source;
    }

    public function getSourceUrl( bool $version = false ) : string
    {
        return $this->source.( $version ? '?v='.$this->getVersion() : '' );
    }

    public function getVersion() : string
    {
        $version = \filemtime( $this->source );
        if ( ! $version ) {
            throw new LogicException(
                'Unable to get the version of a detached asset.',
            );
        }
        return (string) $version;
    }

    public function getHtml() : string
    {
        return $this->element->__toString();
    }

    public function getElement( ...$attributes ) : Element
    {
        return $this->element;
    }

    final public function __toString() : string
    {
        // check if build() has been called
        return $this->getHtml();
    }
}
