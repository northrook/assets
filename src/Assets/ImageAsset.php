<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\AssetManager\AssetDefinition;
use Core\Pathfinder\Path;
use Core\View\Element;
use Intervention\Image\Interfaces\ImageInterface;
use Support\Image\{Aspect, Blurhash, Orientation};
use Support\Image;
use function Support\{str_after, str_before};
use const Support\AUTO;
use const Time\HOUR_4;
use InvalidArgumentException;

/**
 * Extend this class to create an image asset.
 */
class ImageAsset extends AssetDefinition
{
    protected const array SIZES = [
        160,
        320,
        480,
        640,
        800,
        960,
        1_120,
        1_280,
        1_440,
    ];

    public readonly string $source;

    private readonly ImageInterface $image;

    public readonly Orientation $orientation;

    public readonly Aspect $aspect;

    protected ?string $blurHash = null;

    /** @var array<array-key, array{'filePath': string, 'assetUrl': string, 'width': int, 'height': int, 'position': string}> */
    protected array $srcset;

    public function __invoke(
        string $source,
    ) : self {
        $this->source      = $source;
        $this->aspect      = Aspect::from( $this->source );
        $this->orientation = $this->aspect->orientation;
        return $this;
    }

    public function getPicture( mixed ...$attributes ) : string
    {
        return '<picture>'
               .\implode( '', [...$this->getPictureSources(), $this->getElement( ...$attributes )] )
               .'</picture>';
    }

    public function getHtml(
        bool     $asPicture = true,
        mixed ...$attributes,
    ) : string {
        if ( $asPicture ) {
            return (string) new Element(
                'picture',
                [...$this->getPictureSources(), $this->getElement( ...$attributes )],
            );
        }
        return $this->getElement()->__toString();
    }

    public function getElement( mixed ...$attributes ) : Element
    {
        if ( isset( $this->element ) ) {
            $this->element->attributes->merge( $attributes );
            return $this->element;
        }

        $this->element = new Element( 'img', ...$attributes );

        $this->element->attributes->set( 'src', $this->getFallbackSource() );
        $this->element->attributes->set( 'asset-id', $this->meta->getAssetId() );
        $this->element->attributes->style->add( 'width: 100%; height: auto;' );

        return $this->element;
    }

    /**
     * @param null|int<4,128> $resolution [AUTO]
     *
     * @return string
     */
    public function getBlurHash( ?int $resolution = AUTO ) : string
    {
        if ( $this->blurHash ) {
            return $this->blurHash;
        }

        $resolution ??= $this->getSetting( 'asset.image.blurhash.resolution', 64 );

        return $this->blurHash = (string) $this->getCache(
            key        : "blurhash.{$this->meta->getName()}.{$resolution}",
            callback   : fn() => Blurhash::encode( $this->source, $resolution ),
            expiration : $this->cacheExpiration ?? $this->getSetting(
                'asset.image.blurhash.expiration',
                HOUR_4,
            ),
        );
    }

    /**
     * @param null|int<4,64> $resolution [AUTO]
     *
     * @return string
     */
    public function getBlurHashDataUri( ?int $resolution = AUTO ) : string
    {
        $resolution ??= $this->getSetting( 'asset.image.blurhash_uri.resolution', 12 );

        return $this->getCache(
            key        : "blurhash_data.{$this->meta->getName()}.{$resolution}",
            callback   : fn() => Blurhash::decodeToDataUri( $this->getBlurHash(), $resolution ),
            expiration : $this->cacheExpiration ?? $this->getSetting(
                'asset.image.blurhash_uri.expiration',
                HOUR_4,
            ),
        );
    }

    /**
     * Returns a set of CSS properties and values:
     *
     * ```
     *   background-image: url( .. blurhash );
     *   background-size:  cover;
     *   aspect-ratio:     aspect/ratio;
     * ```
     *
     * @param null|int<4,64> $resolution  [AUTO]
     * @param bool           $aspectRatio
     *
     * @return string
     */
    public function getBlurHashBackgroundStyle( ?int $resolution = AUTO, bool $aspectRatio = false ) : string
    {
        $resolution ??= $this->getSetting( 'asset.image.blurhash_uri.resolution', 8 );

        $data = Blurhash::decodeToDataUri( $this->getBlurHash(), $resolution );

        $style = "background-image: url({$data}); background-size: cover;";

        if ( $aspectRatio ) {
            $style .= " aspect-ratio: {$this->aspect->getFloat()};";
        }

        return $style;
    }

    public function getFallbackSource() : string
    {
        return $this->getSrcset()['fallback']['assetUrl'];
    }

    /**
     * @return string[]
     */
    public function getPictureSources() : array
    {
        $sources = [];

        foreach ( $this->getSrcset() as $image ) {
            $sources[] = Element::source(
                srcset : $image['assetUrl'],
                media  : "(min-width: {$image['width']}px)",
            );
        }

        return $sources;
    }

    public function generateImage( string $path ) : string
    {
        $savePath = $this->pathfinder->get( "dir.public/{$path}" );

        if ( ! \str_contains( $path, '~' ) ) {
            $path = $this->getSourceUrl();
        }

        $sizes = \trim( str_before( str_after( $path, '~' ), '.' ), '~' );

        [$width, $height] = \explode( 'x', $sizes );

        $this->createScaledFile(
            $savePath,
            (int) $width,
            (int) $height,
        );

        return $savePath;
    }

    /**
     * @param ?string $ext
     * @param bool    $generate
     *
     * @return array<array-key, array{'filePath': string, 'assetUrl': string, 'width': int, 'height': int, 'position': string}>
     */
    public function getSrcset(
        ?string $ext = AUTO,
        bool    $generate = false,
    ) : array {
        if ( isset( $this->srcset ) ) {
            return $this->srcset;
        }

        $fallback = null;

        $ext ??= $this->getSetting( 'asset.image.extension', 'png' );

        foreach ( $this->meta->get( 'sizes', $this::SIZES ) as $size ) {
            $fileName = $this->fileName();

            [$width, $height, $position] = $this->srcsetSize( $size );

            $fileName .= "~{$width}x{$height}";

            if ( $position !== 'center' ) {
                $fileName .= '-'.\trim( \str_replace( ['.', ' ', '--'], '-', $position ), '-' );
            }

            $fileName .= ".{$ext}";

            $filePath     = "dir.public.assets/{$fileName}";
            $relativePath = $this->pathfinder->getUrl( $filePath, 'dir.public' );

            $srcset = [
                'filePath' => $filePath,
                'assetUrl' => $relativePath,
                'width'    => $width,
                'height'   => $height,
                'position' => $position,
            ];

            $this->srcset[] = $srcset;

            if ( ! $fallback && ( $width + $height > $this->getSetting( 'asset.image.fallback_threshold', 1_980 ) ) ) {
                $fallback = $srcset;
            }

            if ( $generate ) {
                $this->createScaledFile(
                    $filePath,
                    $width,
                    $height,
                    $position,
                );
            }
        }

        if ( ! $this->srcset ) {
            throw new InvalidArgumentException( 'No image sizes found.' );
        }

        // Sort by width descending
        \usort( $this->srcset, fn( $a, $b ) => $b['width'] <=> $a['width'] );

        $this->srcset['fallback'] = $fallback ?? $this->srcset[0];

        return $this->srcset;
    }

    /**
     * @param Path|string                                   $path
     * @param int                                           $width
     * @param int                                           $height
     * @param string                                        $position
     * @param null|callable( ImageInterface):ImageInterface $callback
     * @param bool                                          $override
     * @param mixed                                         ...$options
     *
     * @return void
     */
    public function createScaledFile(
        Path|string $path,
        int         $width,
        int         $height,
        string      $position = 'center',
        ?callable   $callback = null,
        bool        $override = false,
        mixed    ...$options,
    ) : void {
        $path = $this->pathfinder->getPath( $path );

        if ( ! $override && $path->exists() ) {
            return;
        }

        if ( ! \is_dir( $path->getPath() ) ) {
            \mkdir( $path->getPath(), 0777, true );
        }

        $image = $this->createResizedImage( $width, $height, $position );

        if ( $callback ) {
            $image = $callback( $image );
        }

        $image->save( $path->getPathname(), ...$options );
    }

    public function createResizedImage(
        int    $width,
        ?int   $height = AUTO,
        string $position = 'center',
    ) : ImageInterface {
        $this->image ??= Image::from( $this->source );

        if ( ! $height ) {
            [$width, $height] = $this->aspect->scaleHeight( $width );
        }

        $image = ( clone $this->image );

        if ( \str_contains( $position, ' ' ) && \ctype_alnum( \str_replace( ['.', ' ', '%'], '', $position ) ) ) {
            $longEdge = $width > $height ? $width : $height;
            [$x, $y]  = $this->relativePosition( $position );

            [$resizeWidth, $resizeHeight] = $this->aspect->scaleShortest( $longEdge );

            // Resize while maintaining the aspect ratio
            $image->resize( $resizeWidth, $resizeHeight );

            // Calculate cropping position in pixels
            $cropX = (int) ( ( $resizeWidth - $width ) * $x );
            $cropY = (int) ( ( $resizeHeight - $height ) * $y );

            $image->crop( $width, $height, $cropX, $cropY );
        }
        else {
            $image->cover( $width, $height, $position );
        }

        return $image;
    }

    /**
     * @param string $string
     *
     * @return array{0: float, 1: float}
     */
    private function relativePosition( string $string ) : array
    {
        if ( ! \str_contains( $string, ' ' ) ) {
            throw new InvalidArgumentException( 'Invalid position string provided.' );
        }

        $position = [];

        foreach ( \explode( ' ', $string, 2 ) as $percentage ) {
            $value = \trim( $percentage, " \n\r\t\v\0%.,0" ) ?: '0';
            if ( ! \str_contains( $value, '.' ) ) {
                $value = (float) $value / 100;
            }
            $position[] = (float) \number_format( (float) $value, 3 );
        }

        \assert( \count( $position ) === 2, 'Invalid position string provided.' );

        return $position;
    }

    /**
     * @param array<int, int|string>|int $size
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function srcsetSize( int|array $size ) : array
    {
        if ( \is_int( $size ) ) {
            $size = [$size];
        }

        if ( ! \is_int( $size[0] ) ) {
            throw new InvalidArgumentException( 'Invalid size provided.' );
        }

        $width    = (int) \array_shift( $size );
        $height   = null;
        $position = 'center';

        if ( ! isset( $size[1] ) ) {
            [$width, $height] = $this->aspect->scaleWidth( $width );
        }

        if ( isset( $size[0] ) && \is_int( $size[0] ) ) {
            $height = (int) \array_shift( $size );
        }

        if ( isset( $size[0] ) && \is_string( $size[0] ) ) {
            $position = (string) \array_shift( $size );
        }

        return [
            $width,
            $height ?? $width,
            $position,
        ];
    }

    public function getSourcePath() : string
    {
        return $this->source;
    }

    public function getSourceUrl( bool $version = false ) : string
    {
        return $this->getFallbackSource().( $version ? "?v={$this->getVersion()}" : '' );
    }

}
