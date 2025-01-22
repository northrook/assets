<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Asset;

use Core\Assets\Factory\AssetHtml;
use Core\Assets\Factory\Compiler\AbstractAssetModel;
use Core\Assets\Interface\AssetHtmlInterface;

final class ImageAsset extends AbstractAssetModel
{
    protected function construct() : void
    {
        // TODO: Implement construct() method.
    }

    public function render( ?array $attributes = null ) : AssetHtmlInterface
    {
        // dump( $this );
        return new AssetHtml(
            $this->getName(),
            $this->assetID(),
            $this->getType(),
            '<img src="#" alt="" />',
        );
    }
}
