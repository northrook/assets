<?php

declare(strict_types=1);

namespace Core\Assets\Interface;

use Core\Assets\Factory\AssetReference;

/**
 * @author Martin Nielsen <mn@northrook.com>
 */
interface AssetManagerInterface
{
    /**
     * @param AssetReference|string                     $asset
     * @param ?string                                   $assetID
     * @param array<string, null|bool|float|int|string> $attributes
     *
     * @return null|AssetHtmlInterface
     */
    public function getAssetHtml(
        string|AssetReference $asset,
        ?string               $assetID = null,
        array                 $attributes = [],
    ) : ?AssetHtmlInterface;

    /**
     * @param AssetReference|string $asset
     * @param ?string               $assetID
     *
     * @return null|AssetModelInterface
     */
    public function getAssetModel(
        string|AssetReference $asset,
        ?string               $assetID = null,
    ) : ?AssetModelInterface;

    /**
     * @param string $asset
     *
     * @return null|AssetReference
     */
    public function getReference(
        string $asset,
    ) : ?AssetReference;
}
