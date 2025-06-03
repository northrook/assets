<?php

namespace Core\Interface;

interface AssetInterface
{
    /**
     * @return string absolute path to the public asset
     */
    public function getPath() : string;

    /**
     * Returns the URL to the public version of this asset
     *
     * Returns `absolute` by default.
     *
     * ```
     * absolute: [schema][hostname.tld]/assets/type/fileName.ext
     * relative: /assets/type/fileName.ext
     * ```
     *
     * @param bool $absolute [false]
     * @param bool $version  Append `?v=`{@see getVersion}
     *
     * @return string URL relative to `dir.public`
     */
    public function getUrl(
        bool $absolute = false,
        bool $version = false,
    ) : string;

    /**
     * Get a version string for this AbstractAsset.
     *
     * Provides the {@see self::$assetId} by default.
     *
     * @return string
     */
    public function getVersion() : string;
}
