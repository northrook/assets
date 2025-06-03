<?php

declare(strict_types=1);

namespace Core\AssetManager\Assets;

use Core\Asset;
use Core\Asset\Type;

abstract class FontAsset extends Asset
{
    public const Type TYPE = Type::FONT;

    final public function __toString() : string
    {
        return __METHOD__;
    }
}
