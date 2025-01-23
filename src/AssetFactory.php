<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Assets\Factory\{AssetLocator, AssetReference};
use Core\Assets\Exception\{InvalidAssetTypeException, UndefinedAssetReferenceException};
use Core\Assets\Interface\{AssetHtmlInterface, AssetManifestInterface, AssetModelInterface};
use Core\Assets\Factory\Asset\{ImageAsset, ScriptAsset, StyleAsset, Type};
use Core\PathfinderInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(
    lazy   : true,
    public : false,
)]
class AssetFactory
{
    private readonly AssetLocator $locator;

    protected bool $lock = false;

    /** @var array<string, callable(AssetModelInterface):AssetModelInterface> */
    protected array $assetModelCallback = [];

    /** @var array<string, callable(AssetModelInterface):AssetModelInterface> */
    protected array $assetTypeCallback = [];

    /**
     * @param AssetManifest        $manifest
     * @param PathfinderInterface  $pathfinder
     * @param string               $publicDirectory
     * @param string|string[]      $assetDirectories
     * @param null|LoggerInterface $logger
     */
    final public function __construct(
        public readonly AssetManifestInterface $manifest,
        protected readonly PathfinderInterface $pathfinder,
        protected readonly string              $publicDirectory,
        protected readonly string|array        $assetDirectories,
        protected readonly ?LoggerInterface    $logger = null,
    ) {}

    final public function addAssetReferenceCallback( string $reference, callable $callback ) : self
    {
        if ( $this->lock ) {
            $message = "Unable to add assetModelCallback to '{$reference}', the AssetManager is locked.";
            throw new RuntimeException( $message );
        }
        $this->assetModelCallback[$reference] = $callback;
        return $this;
    }

    final public function addAssetTypeCallback( Type $type, callable $callback ) : self
    {
        if ( $this->lock ) {
            $message = "Unable to add assetTypeCallback to '{$type->name}', the AssetManager is locked.";
            throw new RuntimeException( $message );
        }
        $this->assetTypeCallback[$type->name] = $callback;
        return $this;
    }

    final public function locator() : AssetLocator
    {
        return $this->locator ??= new AssetLocator(
            $this->manifest,
            $this->pathfinder,
            $this->publicDirectory,
            (array) $this->assetDirectories,
            $this->logger,
        );
    }

    /**
     * @param AssetReference|string                     $asset
     * @param ?string                                   $assetID
     * @param array<string, null|bool|float|int|string> $attributes
     *
     * @return AssetHtmlInterface
     * @throws InvalidAssetTypeException
     * @throws UndefinedAssetReferenceException
     */
    final public function getAssetHtml(
        AssetReference|string $asset,
        ?string               $assetID = null,
        array                 $attributes = [],
    ) : AssetHtmlInterface {
        $assetModel = $this->getAssetModel( $asset, $assetID );

        return $assetModel->render( $attributes );
    }

    /**
     * @param AssetReference|string $asset
     * @param ?string               $assetID
     *
     * @return AssetModelInterface
     *
     * @throws InvalidAssetTypeException|UndefinedAssetReferenceException on failure
     */
    final public function getAssetModel(
        string|AssetReference $asset,
        ?string               $assetID = null,
    ) : AssetModelInterface {
        $reference = $this->resolveAssetReference( $asset );

        $model = match ( $reference->type ) {
            Type::STYLE  => StyleAsset::class,
            Type::SCRIPT => ScriptAsset::class,
            Type::IMAGE  => ImageAsset::class,
            default      => null,
        };

        if ( ! $model ) {
            throw new InvalidAssetTypeException( $reference->type );
        }

        $asset = $model::fromReference(
            $reference,
            $this->pathfinder,
        );

        $model = $asset->build( $assetID );

        $this->handleAssetCallback( $model );

        return $model;
    }

    /**
     * @param AssetReference|string $asset
     *
     * @return AssetReference
     *
     * @throws UndefinedAssetReferenceException on failure
     */
    final public function resolveAssetReference(
        string|AssetReference $asset,
    ) : AssetReference {
        if ( $asset instanceof AssetReference ) {
            $asset = $asset->name;
        }

        try {
            return $this->manifest->getReference( $asset );
        }
        catch ( UndefinedAssetReferenceException $exception ) {
            $validType = Type::from( \strstr( $asset, '.', true ) ?: $asset );

            if ( $validType ) {
                $this->logger?->warning(
                    'Unable to resolve asset model for {asset} with type {type}. Autodiscover triggered.',
                    ['asset' => $asset, 'type' => $validType->name],
                );
                $this->locator()->locateAsset( $validType );
            }
            else {
                $this->logger?->emergency(
                    $exception->getMessage(),
                    [
                        'asset'     => $asset,
                        'exception' => $exception,
                    ],
                );
                throw $exception;
            }
            return $this->manifest->getReference( $asset );
        }
    }

    /**
     * Handle registered pre-render `callback` functions.
     *
     * @param AssetModelInterface $assetModel
     *
     * @return void
     */
    private function handleAssetCallback( AssetModelInterface &$assetModel ) : void
    {
        if ( \array_key_exists(
            $type = $assetModel->getType()->name,
            $this->assetTypeCallback,
        ) ) {
            $assetModel = ( $this->assetTypeCallback[$type] )( $assetModel );
        }
        if ( \array_key_exists(
            $reference = $assetModel->getReference()->reference,
            $this->assetModelCallback,
        ) ) {
            $assetModel = ( $this->assetModelCallback[$reference] )( $assetModel );
        }
    }
}
