<?php

declare(strict_types=1);

namespace Core\Assets\Interface;

use Core\Assets\Exception\UndefinedAssetReferenceException;
use Core\Assets\Factory\AssetReference;

interface AssetManifestInterface
{
    /**
     * Check if the Manifest has a given {@see AssetReference} by `name` or `object`.
     *
     * @param AssetReference|string $asset
     *
     * @return bool
     */
    public function hasReference( string|AssetReference $asset ) : bool;

    /**
     * Retrieve a {@see AssetReference} by `name`.
     *
     * @param string                     $asset
     * @param ?callable():AssetReference $register
     *
     * @return AssetReference
     * @throws UndefinedAssetReferenceException
     */
    public function getReference( string $asset, ?callable $register = null ) : AssetReference;

    /**
     * Register a provided {@eee \Core\Assets\Factory\AssetReference}.
     *
     * The `name` is derived from the `$reference->name`.
     *
     * @param AssetReference $reference
     *
     * @return self
     */
    public function registerReference( AssetReference $reference ) : self;

    public function commit() : bool;
}
