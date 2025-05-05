<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Asset\Inlinable;
use Core\AssetManager\AssetDefinition;
use Core\View\Element;

/**
 * Extend this class to create a Script asset.
 */
class ScriptAsset extends AssetDefinition
{
    use Inlinable;

    public readonly string $source;

    public function __invoke(
        ?string $source = null,
    ) : self {
        $this->source = $source ?? '';
        // $this->aspect      = Aspect::from( $this->source );
        // $this->orientation = $this->aspect->orientation;
        return $this;
    }

    public function getElement( mixed ...$attributes ) : Element
    {
        return new Element( 'style', __METHOD__, ...$attributes );
    }

    public function getSourcePath() : string
    {
        return __METHOD__;
    }

    public function getSourceUrl( bool $version = false ) : string
    {
        return __METHOD__;
    }

    public function getVersion() : string
    {
        return __METHOD__;
    }
}
