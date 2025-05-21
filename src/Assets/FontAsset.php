<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Asset\Type;
use Core\AssetManager\AbstractAsset;

class FontAsset extends AbstractAsset
{
    public const Type TYPE = Type::FONT;

    protected function build() : void
    {
        // TODO: Implement build() method.
    }
}
