<?php

declare( strict_types = 1 );

namespace Core\Assets\Factory;

use Core\Assets\{AssetManifest, Factory\Compiler\AssetReference};
use Core\Assets\Exception\InvalidAssetTypeException;
use Core\Assets\Factory\Asset\Type;
use Core\Assets\Interface\AssetManifestInterface;
use Core\PathfinderInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Support\{FileInfo, Filesystem, Normalize, Str};
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Locates `assets`, registering each as an {@see AssetReference} in the {@see Manifest}.
 *
 * Does **not**:
 * - Generate asset files
 * - Modify source assets
 */
final class AssetLocator
{
    public const string
            DIR_ASSETS_KEY = 'dir.assets',         // where to find application assets
            DIR_PUBLIC_KEY = 'dir.assets.public',  // publicly available assets
            URL_PUBLIC_KEY = 'url.public',         // application url; example.com
            DIR_BUILD_KEY = 'dir.assets.build';   // store optimized and cached assets

    public const string
            DIR_ASSETS = 'dir.assets',
            DIR_STYLES = 'dir.assets/styles',
            DIR_SCRIPTS = 'dir.assets/scripts',
            DIR_FONTS = 'dir.assets/fonts',
            DIR_IMAGES = 'dir.assets/images',
            DIR_VIDEOS = 'dir.assets/videos',
            DIR_DOCUMENTS = 'dir.assets/documents';

    // Flip these, so the key is the parameterKey, and value is name
    /** @var array<string, string> */
    private array $assetDirectories = [
            'root'     => self::DIR_ASSETS,
            'style'    => self::DIR_STYLES,
            'script'   => self::DIR_SCRIPTS,
            'font'     => self::DIR_FONTS,
            'image'    => self::DIR_IMAGES,
            'video'    => self::DIR_VIDEOS,
            'document' => self::DIR_DOCUMENTS,
    ];

    /**
     * @param AssetManifestInterface  $manifest
     * @param PathfinderInterface     $pathfinder
     * @param ?LoggerInterface        $logger
     */
    final public function __construct(
            private readonly AssetManifestInterface $manifest,
            private readonly PathfinderInterface    $pathfinder,
            private readonly ?LoggerInterface       $logger = null,
    ) {}

    final public function updateManifest() : void
    {
        $this->manifest->commit();
    }

    /**
     * # ✅
     *
     * Ensure all {@see AssetCompiler::$assetDirectories} exist and are valid.
     *
     * Usually run during a {@see CompilerPass}.
     *
     * @param bool  $returnException
     *
     * @return InvalidArgumentException|true
     *
     * @throws InvalidArgumentException if a directory is `invalid` or a `file`
     */
    final public function prepareAssetDirectories( bool $returnException = false ) : true | InvalidArgumentException
    {
        // Inverted validation - assume everything will be OK
        $result = true;

        $scanPathfinderKeys = [
                'public' => self::DIR_PUBLIC_KEY,
                ...$this->assetDirectories,
        ];

        foreach ( $scanPathfinderKeys as $directory ) {
            $path = $this->pathfinder->getFileInfo( $directory );

            // If the path is empty or not a directory, it is invalid
            if ( !$path || $path->getExtension() ) {
                $result = new InvalidArgumentException( 'Invalid asset directory: ' . $directory );

                break;
            }

            // Ensure the directory exists
            if ( !$path->exists() ) {
                Filesystem::mkdir( $path );
            }

            // Ensure the set $path is a readable directory.
            if ( $path->isFile() || !$path->isReadable() ) {
                $result = new InvalidArgumentException();

                break;
            }
        }

        // Throw Exceptions by default
        if ( $result instanceof InvalidArgumentException && false === $returnException ) {
            throw $result;
        }

        return $result;
    }

    /**
     * Intended to be blindly called to discover and register all assets.
     *
     * - Scan all {@see self::assetDirectories} by default.
     * - Each {@see AssetReference} is added or updated in the {@see self::$manifest}.
     *
     * @param 'document'|'font'|'image'|'root'|'script'|'style'|'video'|Type  ...$scan
     *
     * @return $this
     */
    final public function discover( string | Type ...$scan ) : self
    {
        foreach ( $this->scan( ...$scan ) as $reference ) {
            $this->manifest->registerReference( $reference );
        }

        if ( $this->manifest instanceof AssetManifest ) {
            $this->manifest->updatePhpStormMeta( $this->pathfinder->get( 'dir.root' ) );
        }

        return $this;
    }

    /**
     * Retrieve one or more root asset directories by {@see Type}
     *
     * @param 'document'|'font'|'image'|'root'|'script'|'style'|'video'|Type  ...$scan
     *
     * @return AssetReference[]
     */
    final public function scan( string | Type ...$scan ) : array
    {
        $found = [];

        foreach ( $this->getAssetDirectories( ...$scan ) as $key => $directory ) {
            $type = Type::from( $key ) ?? null;

            // Skip `./assets/~` for now
            if ( !$type ) {
                continue;
            }

            if ( !$directory ) {
                $this->logger?->error( 'No path found for ' . $directory );

                continue;
            }

            if ( !$directory->exists() ) {
                $directory->mkdir();
            }

            // dump( [$key => $type?->name] );

            $scannedAssetFiles = match ( $type ) {
                Type::STYLE  => $this->locateStylesheetAssets( $directory, $type ),
                Type::SCRIPT => $this->locateJavascriptAssets( $directory ),
                Type::IMAGE  => $this->scanImageAssets( $directory, $type ),
                default      => [],
            };

            $found = \array_merge( $found, $scannedAssetFiles );
        }

        // dump( $found );
        return $found;
    }

    /**
     * @param FileInfo  $directory
     *
     * @return AssetReference[]
     */
    public function locateJavascriptAssets( FileInfo $directory ) : array
    {
        $type    = Type::SCRIPT;
        $results = [];

        foreach ( $directory->glob( '/*.js', asFileInfo : true ) as $fileInfo ) {
            $reference                   = new AssetReference(
                    $type,
                    $this->generateAssetName( $fileInfo, $type ),
                    $this->relativePublicUrl( $fileInfo ),
                    $fileInfo->getPathname(),
            );
            $results[ $reference->name ] = $reference;
        }

        return $results;
    }

    /**
     * @param FileInfo  $directory
     * @param Type      $type
     *
     * @return AssetReference[]
     */
    public function locateStylesheetAssets( FileInfo $directory, Type $type ) : array
    {
        $ext = match ( $type ) {
            Type::STYLE  => 'css',
            Type::SCRIPT => 'js',
            default      => throw new InvalidAssetTypeException( $type ),
        };

        $results = [];

        /** @var FileInfo[] $parse */
        $parse = [
                ...$directory->glob( "/*.{$ext}", asFileInfo : true ),
                ...$directory->glob( '/**/', asFileInfo : true ),
        ];
        // dump($directory);

        foreach ( $parse as $fileInfo ) {
            $reference                   = new AssetReference(
                    $type,
                    $this->generateAssetName( $fileInfo, $type ),
                    $this->relativePublicUrl( $fileInfo, $ext ),
                    $fileInfo->getPathname(),
            );
            $results[ $reference->name ] = $reference;
        }

        return $results;
    }

    /**
     * @param FileInfo  $directory
     * @param Type      $type
     *
     * @return AssetReference[]
     */
    public function scanImageAssets( FileInfo $directory, Type $type ) : array
    {
        $results = [];

        $finder = new Finder();

        $finder->files()->in( (string) $directory );

        if ( $finder->hasResults() ) {
            foreach ( $finder as $splFileInfo ) {
                $ext = $splFileInfo->getExtension();

                if ( !$ext || !Type::from( $ext ) ) {
                    $this->logger?->error( 'Invalid asset type when scanning images: ' . $splFileInfo->getExtension() );

                    continue;
                }

                $reference                   = new AssetReference(
                        $type,
                        $this->generateAssetName( $splFileInfo, $type ),
                        $this->relativePublicUrl( $splFileInfo, $ext ),
                        $splFileInfo->getPathname(),
                );
                $results[ $reference->name ] = $reference;
            }
        }

        return $results;
    }

    /**
     * # ✅
     *
     * Retrieve one or more root asset directories by {@see Type}
     *
     * @param 'document'|'font'|'image'|'root'|'script'|'style'|'video'|Type  ...$get
     *
     * @return array<string, FileInfo>
     */
    final public function getAssetDirectories( string | Type ...$get ) : array
    {
        $directories = [];

        // Get by arguments
        if ( $get ) {
            foreach ( $get as $directory ) {
                $key = \strtolower( $directory instanceof Type ? $directory->name : $directory );

                if ( !\array_key_exists( $key, $this->assetDirectories ) ) {
                    $message = __METHOD__ . ": Directory '{$key}' is not a valid asset directory.";
                    throw new InvalidArgumentException( $message );
                }

                $directories[ $key ] = $this->pathfinder->getFileInfo(
                        $this->assetDirectories[ $key ],
                );
            }
        }
        // Get all
        else {
            foreach ( $this->assetDirectories as $key => $parameter ) {
                $directories[ $key ] = $this->pathfinder->getFileInfo( $parameter );
            }
        }

        return $directories;
    }

    protected function generateAssetName( string | SplFileInfo $from, Type $type ) : string
    {
        $assetType = \strtolower( $type->name );

        if ( $from instanceof SplFileInfo ) {
            $from = $this->pathfinder->get( $from, 'dir.assets' );
        }

        $normalize = \str_replace( [ '/', '\\' ], DIRECTORY_SEPARATOR, $from );

        // Remove potential .extension
        $normalize = \strrchr( $normalize, '.', true ) ?: $normalize;

        // If this is a relative path
        if ( DIRECTORY_SEPARATOR === $normalize[ 0 ] ) {
            // Remove leading separator
            $path = \ltrim( $normalize, DIRECTORY_SEPARATOR );

            // If the asset directory matches the $assetType, trim it to improve consistency
            if ( \str_starts_with( $path, $assetType ) && \str_contains( $path, DIRECTORY_SEPARATOR ) ) {
                $path = \substr( $path, \strpos( $path, DIRECTORY_SEPARATOR ) + 1 );
            }

            // Treat each subsequent directory as a deliminator
            $from = \str_replace( DIRECTORY_SEPARATOR, '.', $path );
        }

        // Prepend the type
        $name = "{$assetType}.{$from}";

        // Replace whitespace and underscores with hyphens to improve consistency
        return (string) \preg_replace( '#[ _]+#', '-', $name );
    }

    /**
     * # ✅
     *
     * Generate a relative path to an asset file.
     *
     * @internal
     *
     * @param FileInfo|string  $path
     *
     * @param ?string          $ext
     *
     * @return string
     */
    protected function relativePublicUrl( string | SplFileInfo $path, ?string $ext = null ) : string
    {
        if ( \is_string( $path ) ) {
            $path = new SplFileInfo( $path );
        }
        $ext ??= $path->getExtension();

        $relativePath = $this->pathfinder->get( $path, 'dir.assets' );

        if ( !$relativePath ) {
            $message = 'Invalid asset directory: ' . $path;
            throw new InvalidArgumentException( $message );
        }

        return Normalize::url( Str::end( $relativePath, '.' . \trim( $ext, '.' ) ) );
    }

    /**
     * Generate a relative path to an asset file.
     *
     * @internal
     *
     * @param FileInfo|string  $path
     * @param ?string          $ext
     * @param bool             $deferDiscovery
     *
     * @return string|string[]
     */
    protected function resolveAssetSource(
            string | FileInfo $path,
            ?string           $ext = null,
            bool              $deferDiscovery = true,
    ) : string | array
    {
        if ( $deferDiscovery ) {
            return (string) $path;
        }

        if ( $path instanceof FileInfo ) {
            if ( $path->isDir() ) {
                /** @var string[] $files */
                $files = [];
                $ext   ??= '*';

                foreach ( $path->glob( "/*.{$ext}" ) as $file ) {
                    $file = $this->resolveAssetSource( (string) $file );

                    \assert( \is_string( $file ), 'The $path should be a string at this point.' );

                    $files[] = $file;
                }

                if ( \count( $files ) === 1 ) {
                    $file = \array_shift( $files );

                    \assert( \is_string( $file ), 'The $path should be a string at this point.' );

                    \assert( empty( $files ), 'The $files array should be empty.' );

                    return $file;
                }

                return $files;
            }

            $path = (string) $path;
        }

        $relativePath = $this->pathfinder->get( (string) $path, 'dir.assets' );

        if ( !$relativePath ) {
            $message = 'Invalid asset directory: ' . $path;
            throw new InvalidArgumentException( $message );
        }

        return $relativePath;
    }
}
