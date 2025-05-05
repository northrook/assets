<?php

namespace Core\AssetManager;

use Core\View\Element;
use Stringable;

interface AssetInterface extends Stringable
{
    /**
     * Parses and compiles all provided sources.
     *
     * Called by the {@see \Core\AssetManager}.
     *
     * @return self
     */
    public function build() : self;

    /**
     * @return string Absolute path to the source file
     */
    public function getSourcePath() : string;

    /**
     * @param bool $version Append `?v=`{@see self::getVersion()}
     *
     * @return string URL relative to `public`
     */
    public function getSourceUrl( bool $version = false ) : string;

    /**
     * Get a version string for this Asset.
     *
     * Provides the {@see self::$assetId} by default.
     *
     * @return string
     */
    public function getVersion() : string;

    /**
     * @return string
     */
    public function getHtml() : string;

    /**
     * Return the HTML element for this Asset.
     *
     * Called when using {@see self::getHtml()} or cast to `string`.
     *
     * @param mixed ...$attributes
     *
     * @return Element
     */
    public function getElement( mixed ...$attributes ) : Element;
}
