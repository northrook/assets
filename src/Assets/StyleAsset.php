<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\{AbstractAsset};
use Core\Asset\{Inlinable, Type};

class StyleAsset extends AbstractAsset
{
    use Inlinable;

    public const Type TYPE = Type::STYLE;

    protected function build() : void
    {
        // TODO: Implement build() method.
    }
}
