<?php

declare(strict_types=1);

namespace Core\AssetManager;

use Core\AssetManager\Asset\Meta;
use Core\Interface\DataInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\VarExporter;
use Throwable;
use Countable;
use function Support\{datetime, normalize_newline};

final class AssetManifest implements DataInterface, Countable
{
    /** @var array<string,string> */
    private array $map;

    /** @var array<string,string> */
    private array $manifest;

    private ?string $hash = null;

    protected bool $hasChanges = false;

    /**
     * @param string $filePath
     * @param string $metaDirectory
     */
    public function __construct(
        protected readonly string $filePath,
        protected readonly string $metaDirectory,
    ) {}

    public function __destruct()
    {
        if ( $this->hasChanges ) {
            $this->commit();
        }
    }

    public function count() : int
    {
        return \count( $this->loadStorage()->manifest );
    }

    /**
     * @param class-string<Config\Asset>|string  $asset
     *
     * @return Meta
     */
    public function getMeta( string $asset ) : Meta
    {
        return Meta::load( $this->getPath( $asset ) );
    }

    /**
     * @param class-string<Config\Asset>|string  $asset
     *
     * @return string
     */
    public function getPath( string $asset ) : string
    {
        $asset = $this->validateAssetId( $asset );

        if ( ! isset( $this->loadStorage()->manifest[$asset] ) ) {
            $path = $this->metaDirectory.DIR_SEP.$asset.'.php';
            if ( \file_exists( $path ) ) {
                $this->set( $asset, $path );
            }
            else {
                throw new InvalidArgumentException(
                    $this::class." for '{$asset}' has no path.",
                );
            }
        }

        return $this->loadStorage()->manifest[$asset];
    }

    /**
     * @return array<string,string>
     */
    public function all() : array
    {
        return $this->loadStorage()->manifest;
    }

    public function has( string $key ) : bool
    {
        return isset( $this->loadStorage()->manifest[$key] );
    }

    public function register( Meta $meta ) : self
    {
        // $key = $this->validateAssetId( $meta->getAssetId() );

        // if ( $meta->getRegistration() === Registration::STATIC ) {
        //     $this->set( $meta->class, $key );
        //
        //     $name = $meta->getName();
        //
        //     if ( $this->has( $this ) ) {
        //         throw new DuplicateEntryException(
        //             "Static Assets must hae a unique name. '{$name}' is already registered.",
        //         );
        //     }
        //
        //     $this->set( $name, $key );
        // }
        //
        // foreach ( $meta->getSources() as $source ) {
        //     $this->set( $source, $key );
        // }
        //
        // foreach ( $meta->getPublicPaths() as $publicPath ) {
        //     $this->set( $publicPath, $key );
        // }

        return $this;
    }

    /**
     * @param string $key
     * @param string $assetId
     *
     * @return $this
     */
    public function set( string $key, string $assetId ) : self
    {
        $assetId = $this->validateAssetId( $assetId );

        $this->loadStorage()->manifest[$key] = $assetId;
        $this->hasChanges                    = true;
        return $this;
    }

    public function hasChanges( true $set = null ) : bool
    {
        if ( $set ) {
            $this->hasChanges = true;
        }

        return $this->hasChanges;
    }

    public function export( bool $JSON = false ) : string
    {
        try {
            return $JSON
                    ? \json_encode( $this->manifest, JSON_THROW_ON_ERROR )
                    : normalize_newline( VarExporter::export( $this->manifest ) );
        }
        catch ( Throwable $e ) {
            throw new LogicException( $e->getMessage(), $e->getCode(), $e );
        }
    }

    public function commit( bool $force = false ) : bool
    {
        if ( ! $this->filePath ) {
            throw new LogicException( 'Cannot commit '.$this::class.' without a file path.' );
        }

        if ( $force ) {
            $this->hasChanges = true;
        }

        // Do not attempt to commit anything if nothing has changed
        if ( ! $force && ( empty( $this->manifest ) || $this->hasChanges === false ) ) {
            return false;
        }

        $dataExport      = $this->export();
        $storageDataHash = \hash( algo : 'xxh3', data : $dataExport );

        if ( ! $force && $storageDataHash === ( $this->hash ?? null ) ) {
            return false;
        }

        $dateTime           = datetime();
        $timestamp          = $dateTime->getTimestamp();
        $formattedTimestamp = $dateTime->format( 'Y-m-d H:i:s e' );

        $dataExport = (string) \preg_replace_callback(
            '#^ *#m',
            static function( $matches ) {
                // Group each $tabSize
                $tabs = \intdiv( \strlen( $matches[0] ), 4 );

                // Replace $tabs with "\t", excess spaces discarded
                // Otherwise leading whitespace is trimmed
                return ( $tabs > 0 ) ? \str_repeat( '    ', $tabs ) : '';
            },
            $dataExport,
        );

        $content = <<<PHP
            <?php
            
            /*------------------------------------------------------%{$timestamp}%-
            
               AbstractAsset Manifest.
               Generated : {$formattedTimestamp}
            
               Managed by the AssetManager.
               Do not edit it manually.
            
            -#{$storageDataHash}#------------------------------------------------*/
            
            return [
            {$dataExport}, 
                '{$storageDataHash}'
            ];
            PHP;

        try {
            ( new Filesystem() )->dumpFile( $this->filePath, $content.PHP_EOL );
        }
        catch ( Throwable $e ) {
            throw new LogicException( $e->getMessage(), $e->getCode(), $e );
        }

        return true;
    }

    /**
     * @param int|string $value
     *
     * @return string
     */
    private function validateAssetId( int|string $value ) : string
    {
        if ( ! \is_string( $value ) ) {
            throw new InvalidArgumentException( $this::class.' keys must be strings.' );
        }

        if ( \class_exists( $value, false ) ) {
            return $value;
        }

        // $value = \strtolower( $value );

        \assert(
            \ctype_alnum( \str_replace( ['.', '-'], '', $value ) ),
            $this::class." keys must only contain ASCII characters, underscores and dashes. '".$value."' provided.",
        );

        return $value;
    }

    /**
     * @return self
     */
    private function loadStorage() : self
    {
        if ( isset( $this->manifest ) ) {
            return $this;
        }

        if ( ! \file_exists( $this->filePath ) ) {
            $this->manifest = [];
            $this->hash     = 'initial';
            return $this;
        }

        try {
            [$this->manifest, $this->hash] = include $this->filePath;
        }
        catch ( Throwable $e ) {
            throw new InvalidArgumentException( $e->getMessage(), $e->getCode(), $e );
        }

        return $this;
    }
}
