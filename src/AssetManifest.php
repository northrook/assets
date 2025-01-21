<?php

declare(strict_types=1);

namespace Core\Assets;

use Cache\LocalStorage;
use Core\Assets\Exception\UndefinedAssetReferenceException;
use Core\Assets\Factory\Compiler\AssetReference;
use Core\Assets\Interface\{AssetManagerInterface, AssetManifestInterface};
use Support\PhpStormMeta;

final readonly class AssetManifest implements AssetManifestInterface
{
    protected LocalStorage $storage;

    public function __construct( string $storagePath )
    {
        $this->storage = new LocalStorage(
            filePath  : $storagePath,
            name      : 'asset_manifest',
            generator : $this::class,
            autosave  : false,
            validate  : true,
        );
    }

    final public function hasReference( AssetReference|string $asset ) : bool
    {
        if ( $asset instanceof AssetReference ) {
            $asset = $asset->name;
        }

        return $this->storage->has( $asset );
    }

    final public function getReference( string $asset, ?callable $register = null ) : AssetReference
    {
        $reference = $this->storage->get( $asset, $register );

        if ( ! $reference ) {
            throw new UndefinedAssetReferenceException( $asset, $this->storage->getKeys() );
        }

        return $reference;
    }

    final public function registerReference( AssetReference $reference ) : AssetManifestInterface
    {
        $this->storage->set( $reference->name, $reference );
        return $this;
    }

    final public function hasChanges() : bool
    {
        return $this->storage->hasChanges();
    }

    final public function commit() : bool
    {
        return $this->storage->save();
    }

    /**
     * @param ?string                                                           $projectDirectory
     * @param array{0: class-string, 1: string}|callable|callable-string|string ...$functionReference
     *
     * @return void
     */
    final public function updatePhpStormMeta(
        ?string                  $projectDirectory,
        array|string|callable ...$functionReference,
    ) : void {
        $meta = new PhpStormMeta( $projectDirectory );

        $meta->registerArgumentsSet(
            'asset_reference_keys',
            ...\array_keys( $this->storage->getKeys() ),
        );

        $generateReferences = \array_merge(
            [
                [AssetManifestInterface::class, 'hasReference'],
                [AssetManifestInterface::class, 'getReference'],
                [AssetManagerInterface::class, 'getAssetHtml'],
                [AssetManagerInterface::class, 'getAssetModel'],
                [AssetManagerInterface::class, 'getReference'],
            ],
            $functionReference,
        );

        foreach ( $generateReferences as $generateReference ) {
            $meta->expectedArguments( $generateReference, [0 => 'asset_reference_keys'] );
        }

        $meta->save( 'asset_manifest' );
    }
}
