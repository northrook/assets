<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Asset\Meta;
use Core\AssetManager\Assets\{ScriptAsset};
use Stringable;

final class Script extends ScriptAsset
{
    public function __invoke(
        Meta|Stringable|string $source,
        ?bool                  $prefersInline = null,
    ) : self {
        $prefersInline ??= $this->getSetting( 'prefersInline', false );
        return $this->initialize( ...\get_defined_vars() );
    }
}
