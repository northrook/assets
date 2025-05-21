<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\AbstractAsset;
use Support\{JavaScriptMinifier};
use Core\Asset\{Inlinable, Meta, Type};
use Stringable;

/**
 */
class ScriptAsset extends AbstractAsset
{
    use Inlinable;

    public const Type TYPE = Type::SCRIPT;

    public readonly JavaScriptMinifier $minifier;

    public function __invoke(
        Meta|Stringable|string $source,
        ?bool                  $prefersInline = null,
    ) : ScriptAsset {
        $this->prefersInline( $prefersInline );

        return parent::__invoke( $source );
    }

    protected function build() : void
    {
        // TODO: Implement build() method.
    }
}
