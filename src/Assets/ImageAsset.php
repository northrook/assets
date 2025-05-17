<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\{AbstractAsset};
use Intervention\Image\Interfaces\ImageInterface;
use Support\Image\{Aspect, Orientation};

class ImageAsset extends AbstractAsset
{
    protected readonly ImageInterface $image;

    public readonly Orientation $orientation;

    public readonly Aspect $aspect;

    protected function build() : void
    {
        $this->aspect      = Aspect::from( $this->source );
        $this->orientation = $this->aspect->orientation;
    }
}
