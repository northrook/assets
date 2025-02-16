<?php

declare(strict_types=1);

namespace Core\Assets\Factory;

use Core\Assets\Factory\Asset\Type;
use Core\Assets\Interface\AssetManifestInterface;
use Core\Interface\PathfinderInterface;
use Core\Pathfinder\Path;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Exception;

/**
 * Locates `assets`, registering each as an {@see AssetReference} in the {@see self::$manifest}.
 *
 * Does **not**:
 * - Generate asset files
 * - Modify source assets
 */
final readonly class AssetLocator
{
    /**
     * @param AssetManifestInterface $manifest
     * @param PathfinderInterface    $pathfinder
     * @param string                 $publicDirectory
     * @param string[]               $scanDirectories
     * @param ?LoggerInterface       $logger
     */
    final public function __construct(
        private AssetManifestInterface $manifest,
        private PathfinderInterface    $pathfinder,
        protected string               $publicDirectory,
        protected array                $scanDirectories,
        private ?LoggerInterface       $logger = null,
    ) {}

    public function __destruct()
    {
        $this->manifest->commit();
    }

    final public function updateManifest() : void
    {
        $this->manifest->commit();
    }

    public function locateAsset( Type $type ) : self
    {
        match ( $type ) {
            Type::STYLE       => $this->locateStylesheets(),
            Type::SCRIPT      => $this->locateScripts(),
            Type::IMAGE       => null,
            Type::VIDEO       => null,
            Type::AUDIO       => null,
            Type::FONT        => null,
            Type::DOCUMENT    => null,
            Type::SPREADSHEET => null,
            Type::TEXT        => null,
            Type::ARCHIVE     => null,
            default           => throw new RuntimeException( $type->name.' has yet to be implemented.' ),
        };

        return $this;
    }

    protected function locateScripts() : void
    {
        $type = Type::SCRIPT;

        foreach ( $this->assetDirectories( $type ) as $scanDirectory ) {
            foreach ( ( $scanDirectory->glob( ['/*.js'] ) ) as $fileInfo ) {
                [$reference, $path] = $this->resolveAssetGlob( (string) $fileInfo, $scanDirectory, $type );

                $this->assetReference( $reference, $type )?->addSource( $path );
            }
        }
    }

    protected function locateStylesheets() : void
    {
        $type = Type::STYLE;

        foreach ( $this->assetDirectories( $type ) as $scanDirectory ) {
            foreach ( ( $scanDirectory->glob( ['/*.css', '/*/*.css'] ) ) as $fileInfo ) {
                [$name, $path] = $this->resolveAssetGlob( (string) $fileInfo, $scanDirectory, $type );

                $this->assetReference( $name, $type )?->addSource( $path );
            }
        }
    }

    private function assetReference( string $reference, Type $type ) : ?AssetReference
    {
        try {
            return $this->manifest->getReference( $reference, fn() => new AssetReference( $type, $reference ) );
        }
        catch ( Exception $exception ) {
            $this->logger?->critical( $exception->getMessage() );
        }
        return null;
    }

    /**
     * @param string $assetPath
     * @param Path   $scanDirectory
     * @param Type   $type
     *
     * @return array{string,string}
     */
    private function resolveAssetGlob( string $assetPath, Path $scanDirectory, Type $type ) : array
    {
        $trimmedPath = \trim( \substr( $assetPath, \strlen( $scanDirectory->getPathname() ) ), DIRECTORY_SEPARATOR );

        $name = \strstr( $trimmedPath, DIRECTORY_SEPARATOR, true )
                ?: \strstr( $trimmedPath, '.', true )
                        ?: $trimmedPath;

        $reference = (string) \preg_replace( '#[ _]+#', '-', \strtolower( "{$type->name}.{$name}" ) );

        return [
            $reference,
            $assetPath,
        ];
    }

    /**
     * @param null|Type $type
     *
     * @return Path[]
     */
    public function assetDirectories( ?Type $type = null ) : array
    {
        $directories = [];

        foreach ( $this->scanDirectories as $directory ) {
            $scan = $this->pathfinder->getPath( $directory );
            if ( ! $type ) {
                $directories[] = $scan;
            }

            foreach ( $scan->glob( "/{$this->typeDirectory( $type )}" ) as $typePath ) {
                if ( $typePath->isDirectory() ) {
                    $directories[] = $typePath;
                }
            }
        }

        return $directories;
    }

    /**
     * # ✅
     *
     * Retrieve one or more root asset directories by {@see Type}
     *
     * @param 'document'|'font'|'image'|'root'|'script'|'style'|'video'|Type ...$get
     *
     * @return array<string, Path>
     */
    final public function getAssetDirectories( string|Type ...$get ) : array
    {
        $directories = [];
        //
        // // Get by arguments
        // if ( $get ) {
        //     foreach ( $get as $directory ) {
        //         $key = \strtolower( $directory instanceof Type ? $directory->name : $directory );
        //
        //         if ( ! \array_key_exists( $key, $this->assetDirectories ) ) {
        //             $message = __METHOD__.": Directory '{$key}' is not a valid asset directory.";
        //             throw new InvalidArgumentException( $message );
        //         }
        //
        //         $directories[$key] = $this->pathfinder->getPath(
        //                 $this->assetDirectories[$key],
        //         );
        //     }
        // }
        // // Get all
        // else {
        //     foreach ( $this->assetDirectories as $key => $parameter ) {
        //         $directories[$key] = $this->pathfinder->getFileInfo( $parameter );
        //     }
        // }

        return $directories;
    }

    protected function typeDirectory( null|string|Type $type ) : string
    {
        if ( \is_string( $type ) ) {
            $type = Type::from( \rtrim( $type, 's' ) );
        }

        return match ( $type ) {
            Type::STYLE    => 'styles',
            Type::SCRIPT   => 'scripts',
            Type::FONT     => 'fonts',
            Type::IMAGE    => 'images',
            Type::VIDEO    => 'videos',
            Type::DOCUMENT => 'documents',
            default        => '*',
        };
    }

    private function getPublicDirectory() : Path
    {
        return $this->pathfinder->getPath( $this->publicDirectory )
               ?? throw new RuntimeException();
    }
}
