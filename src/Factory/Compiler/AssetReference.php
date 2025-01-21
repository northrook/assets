<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Core\Assets\Factory\Asset\Type;
use Stringable;
use InvalidArgumentException;
use const PATHINFO_EXTENSION;
use SplFileInfo;
use Support\{FileInfo, Normalize, Str};

/**
 * Created by the {@see \Core\Assets\AssetFactory}, stored in the {@see AssetManifestInterface}.
 *
 * Can be used retrieve detailed information about the asset, or recreate it from source.
 *
 * @internal
 *
 * @author Martin Nielsen
 */
final class AssetReference implements Stringable
{
    /** @var string `lower-case.dot.notated` */
    public readonly string $name;

    /** @var string `relative` */
    public readonly string $publicUrl;

    /** @var array<string, string> */
    protected array $sources = [];

    public function __construct(
        public Type                $type,
        string                     $name,
        string|Stringable          $publicUrl,
        string|Stringable|FileInfo $sources,
    ) {
        \assert(
            \ctype_alpha( \str_replace( ['.', '-'], '', $name ) ),
            "Asset names must only contain ASCII characters, underscores and dashes. {$name} provided.",
        );

        $type = \strtolower( $this->type->name );
        $name = \strtolower( \trim( $name, '.' ) );

        $fragments = \explode( '.', $name );

        if ( ! ( $fragments[0] === $type || $fragments[0] === "{$type}s" ) ) {
            \array_unshift( $fragments, $type );
        }

        $this->name = \implode( '.', \array_filter( $fragments ) );

        $publicUrl = (string) $publicUrl;

        $publicUrl = match ( $this->type ) {
            Type::STYLE, Type::SCRIPT => Str::end( $publicUrl, '.'.\trim( $this->type->extensions()[0], '.' ) ),
            default => $publicUrl,
        };

        $this->publicUrl = Normalize::url( $publicUrl );

        \assert(
            '/' === $this->publicUrl[0],
            'The public url must be relative.',
        );

        $this->addSource( $sources );
    }

    public function __toString() : string
    {
        return $this->name;
    }

    /**
     * @param FileInfo|string|Stringable $path
     * @param ?string                    $key
     * @param bool                       $override
     *
     * @return void
     */
    public function addSource(
        string|Stringable|FileInfo $path,
        ?string                    $key = null,
        bool                       $override = false,
    ) : void {
        if ( ! $path instanceof FileInfo ) {
            $path = new FileInfo( $path );
        }

        if ( $path->isDir() ) {
            foreach ( $path->glob( '/*'.$path->getExtension() ) as $glob ) {
                $this->addSource( $glob );
            }
        }

        if ( ! $path->isReadable() ) {
            throw new InvalidArgumentException(
                "AssetReference: {$this->name} was provided a non-readable asset reference: {$path->getPathname()}",
            );
        }

        if ( ! $path->isFile() ) {
            return;
        }

        $key ??= $path->getFilename();

        if ( $override ) {
            $this->sources[$key] = $path->getRealPath();
        }
        else {
            $this->sources[$key] ??= $path->getRealPath();
        }
    }

    /**
     * @return array<string, string>
     */
    public function getSources() : array
    {
        \ksort( $this->sources );
        return $this->sources;
    }

    /**
     * @param array{type: string, name: string, publicUrl: string, sources: array<string, string>} $data
     *
     * @return void
     */
    public function __unserialize( array $data ) : void
    {
        $this->type      = Type::from( $data['type'], true );
        $this->name      = $data['name'];
        $this->publicUrl = $data['publicUrl'];
        $this->sources   = $data['sources'];
    }

    /**
     * @return array{type: string, name: string, publicUrl: string, sources: array<string, string>}
     */
    public function __serialize() : array
    {
        \ksort( $this->sources );
        return [
            'type'      => $this->type->name,
            'name'      => $this->name,
            'publicUrl' => $this->publicUrl,
            'sources'   => $this->sources,
        ];
    }

    public static function generateName(
        string|SplFileInfo $from,
        ?Type              $type = null,
    ) : string {
        $path = \str_replace( ['/', '\\'], DIRECTORY_SEPARATOR, (string) $from );

        // Assign Type
        if ( ! $type ) {
            if ( ! $extension = \pathinfo( $path, PATHINFO_EXTENSION ) ?: null ) {
                $message = "Could not determine asset type for {$from}.";
                $message .= ' No extension or Type provided.';
                throw new InvalidArgumentException( $message );
            }
            $type = Type::from( $extension );
        }

        $assetType = \strtolower( $type->name );

        // dump()

        // If the asset directory matches the $assetType, trim it to improve consistency
        // if ( \str_starts_with( $path, $assetType ) && \str_contains( $path, DIRECTORY_SEPARATOR ) ) {
        //     $path = \substr( $path, \strpos( $path, DIRECTORY_SEPARATOR ) + 1 );
        // }

        // Treat each subsequent directory as a deliminator
        // $from = \str_replace( DIRECTORY_SEPARATOR, '.', $path );

        // Remove potential .extension
        $path              = \strrchr( $path, '.', true ) ?: $path;
        $assetString       = DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR;
        $hasAssetDirectory = \strrpos( $path, $assetString );

        if ( $hasAssetDirectory ) {
            $path = \substr( $path, $hasAssetDirectory + \strlen( $assetString ) );
        }
        else {
            $path = \strrchr( $path, DIRECTORY_SEPARATOR ) ?: $path;
        }

        // Remove leading separator
        $path = \ltrim( $path, DIRECTORY_SEPARATOR );

        // If the asset directory matches the $assetType, trim it to improve consistency
        if ( \str_starts_with( $path, $assetType ) && \str_contains( $path, DIRECTORY_SEPARATOR ) ) {
            $path = \substr( $path, \strpos( $path, DIRECTORY_SEPARATOR ) + 1 );
        }

        $path = \strchr( $path, '.', true ) ?: $path;

        $path = \str_replace( DIRECTORY_SEPARATOR, '.', $path );

        // Prepend the type
        $name = "{$assetType}.{$path}";

        // dump(
        //         $name,
        //         // $assetType,
        //         // $segment,
        //         // $hasAssetDirectory,
        //         $path,
        //  strs( $path, DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR ),
        //  $extension,
        //  get_defined_vars(),
        // );

        return (string) \preg_replace( '#[ _]+#', '-', $name );
    }
}
