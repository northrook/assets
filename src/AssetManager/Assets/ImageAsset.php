<?php

declare(strict_types=1);

namespace Core\AssetManager\Assets;

use Core\Asset;
use Core\Asset\Type;

abstract class ImageAsset extends Asset
{
    public const Type TYPE = Type::IMAGE;

    final public function __toString() : string
    {
        return __METHOD__;
    }
}
