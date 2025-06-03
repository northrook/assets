<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Asset\Meta;
use Core\AssetManager\Assets\ImageAsset;
use Stringable;

final class Image extends ImageAsset
{
    public function __invoke(
        Meta|Stringable|string $source,
    ) : self {
        return $this->initialize( ...\get_defined_vars() );
    }
}
