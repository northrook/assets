<?php

/** @noinspection DuplicatedCode */

namespace Core\Asset;

use Core\Asset;
use Core\Exception\AssetException;
use InvalidArgumentException;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\VarExporter;
use LogicException;
use Throwable;
use function Support\{arr_has_keys,
    datetime,
    is_stringable,
    is_url,
    normalize_newline,
    normalize_path,
    normalize_url,
    slug
};

/**
 * @internal
 *
 * @property-read class-string<Asset>      $class
 * @property-read ?string                  $id
 * @property-read Type                     $type
 * @property-read Origin                   $origin
 * @property-read string                   $fileName
 * @property-read string                   $baseName
 * @property-read string                   $url
 * @property-read string                   $source
 * @property-read array<array-key, string> $sources
 * @property-read string                   $version
 */
final class Meta
{
    public const string EXTENSION = 'meta';

    protected bool $hasChanges = false;

    /**
     * @param array<string, mixed> $meta
     * @param ?string              $hash
     * @param ?string              $filePath
     */
    private function __construct(
        protected array          $meta = [
            'id'       => null,
            'type'     => null,
            'class'    => null,
            'origin'   => null,
            'fileName' => null,
            'source'   => null, // A single 'real' source file - can be relative to ~/assets/type
            'sources'  => [],
            'public'   => [],
        ],
        private readonly ?string $hash = null,
        protected ?string        $filePath = null,
    ) {}

    public function __get( string $name ) : mixed
    {
        return $this->meta[$name] ??= match ( $name ) {
            'class'    => $this->meta['class'],
            'id'       => $this->id(),
            'type'     => $this->type(),
            'origin'   => $this->origin(),
            'url'      => $this->url(),
            'fileName' => $this->fileName(),
            'baseName' => $this->baseName(),
            'version'  => $this->version(),
            default    => throw new InvalidArgumentException(
                $this::class." has no meta '{$name}'.",
            ),
        };
    }

    public function __destruct()
    {
        if ( $this->filePath && $this->hasChanges ) {
            $this->commit();
        }
    }

    public static function load( string $filePath ) : self
    {
        if ( ! \file_exists( $filePath ) ) {
            throw new AssetException( "File '{$filePath}' does not exist." );
        }

        [$data, $hash] = require_once $filePath;
        return new self( $data, $hash, $filePath );
    }

    /**
     * @param class-string<Asset> $class
     */
    public static function new(
        string $class,
    ) : self {
        return ( new self() )->assetClass( $class );
    }

    /**
     * @param class-string<Asset> $class
     * @param string|Stringable   $source
     * @param mixed[]             $arguments
     *
     * @return Meta
     */
    public static function provide(
        string            $class,
        string|Stringable $source,
        mixed          ...$arguments,
    ) : self {
        $meta = new self();

        $meta
            ->assetClass( $class )
            ->setSource( $source )
            ->add( ...$arguments );

        dump( $meta->id(), $meta );

        return $meta
            ->set( id : $meta->id() );
    }

    /**
     * @param class-string<Asset> $class
     * @param string|Stringable   $source
     * @param array<string,mixed> $arguments
     *
     * @return Meta
     */
    public static function create(
        string            $class,
        string|Stringable $source,
        array             $arguments = [],
    ) : self {
        $meta = new self();

        $meta
            ->assetClass( $class )
            ->setSource( $source )
            ->add( ...$arguments );

        return $meta
            ->set( id : $meta->id() );
    }

    /**
     * @param class-string<Asset> $asset
     * @param string|Stringable   $source
     *
     * @return string
     */
    final public static function getAssetId(
        string            $asset,
        string|Stringable $source,
    ) : string {
        return \hash( 'xxh64', $asset.$source );
    }

    /**
     * @param string              $filePath
     * @param class-string<Asset> $class
     * @param string|Stringable   $source
     * @param ?string             $id
     *
     * @return self
     */
    public static function retrieve(
        string            $filePath,
        string            $class,
        Stringable|string $source,
        ?string           $id = null,
    ) : self {
        $fileName = \strrchr( \strtr( $filePath, '\\', '/' ), '/' )
                ?: throw new InvalidArgumentException( "Invalid filePath '{$filePath}'" );

        $ext = Meta::EXTENSION;
        $id ??= Meta::getAssetId( $class, $source );

        // If passed a directory, create Meta::id
        if ( ! \str_ends_with( $fileName, ".{$ext}" ) ) {
            $fileName = "{$id}.{$ext}";
            $filePath = normalize_path( "{$filePath}/{$fileName}" );
        }

        \assert(
            \str_ends_with( $filePath, $fileName ),
            'FileName and Asset::id mismatch.',
        );

        if ( \file_exists( $filePath ) ) {
            [$data, $hash] = require_once $filePath;
            $meta          = new self( $data, $hash, $filePath );
        }
        else {
            $meta = Meta::create( $class, $source );

            $meta->filePath = $filePath;
        }

        return $meta;
    }

    public function resolve( string $key, callable $onMissing ) : mixed
    {
        $key = $this->validateKey( $key );

        return $this->meta[$key] ?? $onMissing();
    }

    /**
     * @param mixed ...$meta
     *
     * @return $this
     */
    public function add( mixed ...$meta ) : self
    {
        foreach ( $meta as $key => $value ) {
            \assert( \is_string( $key ), $this::class.' keys must be strings.' );
            $this->assign( $key, $value, false );
        }

        return $this;
    }

    /**
     * @param mixed ...$meta
     *
     * @return $this
     */
    public function set( mixed ...$meta ) : self
    {
        foreach ( $meta as $key => $value ) {
            \assert( \is_string( $key ), $this::class.' keys must be strings.' );
            $this->assign( $key, $value, true );
        }
        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $override
     *
     * @return $this
     */
    public function assign(
        string $key,
        mixed  $value,
        bool   $override,
    ) : self {
        $key = $this->validateKey( $key );

        if ( $override === false && \array_key_exists( $key, $this->meta ) && $this->meta[$key] === $value ) {
            return $this;
        }

        if ( $key === 'sources' ) {
            \assert(
                is_stringable( $value ),
                $this::class.'[sources] only accpets stringable values, '.\gettype( $value )."' given.",
            );
            return $this->addSource( $value );
        }

        $this->meta[$key] = $value;
        $this->hasChanges = true;

        return $this;
    }

    /**
     * @template T
     * @param string $key
     * @param T      $default
     *
     * @return T
     */
    public function get(
        string $key,
        mixed  $default,
    ) : mixed {
        return $this->meta[$this->validateKey( $key )] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function all() : array
    {
        return $this->meta;
    }

    public function has( string $key ) : bool
    {
        return isset( $this->meta[$key] );
    }

    public function import( self $from ) : self
    {
        $this->meta = $from->meta;
        return $this;
    }

    public function export( bool $JSON = false ) : string
    {
        try {
            return $JSON
                    ? \json_encode( $this->meta, JSON_THROW_ON_ERROR )
                    : normalize_newline( VarExporter::export( $this->meta ) );
        }
        catch ( Throwable $e ) {
            throw new LogicException( $e->getMessage(), $e->getCode(), $e );
        }
    }

    public function commit(
        ?string $generator = null,
        bool    $force = false,
    ) : bool {
        if ( ! $this->filePath ) {
            throw new LogicException( 'Cannot commit '.$this::class.' without a file path.' );
        }

        if ( $force ) {
            $this->hasChanges = true;
        }

        // Do not attempt to commit anything if nothing has changed
        if ( ! $force && ! $this->hasChanges ) {
            return false;
        }

        $dataExport      = $this->export();
        $storageDataHash = \hash( algo : 'xxh3', data : $dataExport );

        if ( ! $force && $storageDataHash === ( $this->hash ?? null ) ) {
            return false;
        }

        $dateTime = datetime();

        $timestamp          = $dateTime->getTimestamp();
        $formattedTimestamp = $dateTime->format( 'Y-m-d H:i:s e' );
        $generator ??= $this->class;

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
            
               Asset ID  : {$this->id()}
               Generated : {$formattedTimestamp}
               Generator : {$generator}
            
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

    protected function id() : string
    {
        return $this->meta['id'] ??= $this::getAssetId(
            $this->class,
            $this->source,
        );
    }

    protected function type() : Type
    {
        return $this->meta['type'] ??= Type::resolve( $this->url() );
    }

    protected function origin() : Origin
    {
        \assert( $this->meta['origin'] instanceof Origin );
        return $this->meta['origin'];
    }

    /**
     * Get the public path for this asset.
     *
     * If no {@see meta}[path] key exists, one will be derived from {@see sources}
     *
     * @return string
     */
    protected function path() : string
    {
        // ~{dir.public}/assets/{type}/{basename}.{ext}
        return $this->meta['public'];
    }

    protected function url() : string
    {
        return __METHOD__;
    }

    protected function fileName() : string
    {
        return (string) \pathinfo( $this->meta['source'], PATHINFO_FILENAME );
    }

    protected function baseName() : string
    {
        return (string) \pathinfo( $this->meta['source'], PATHINFO_BASENAME );
    }

    protected function version() : string
    {
        return (string) \time();
    }

    public function addSource( string|Stringable $source, ?string $key = null ) : self
    {
        if ( ! $path = (string) $source ) {
            throw new AssetException( 'Empty source provided: '.\var_export( $source, true ) );
        }

        $key ??= slug( $source );

        $origin = Origin::fromPath( $path );
        $type   = Type::resolve( $path );

        dump( \get_defined_vars() );

        return $this;
    }

    public function setSource( Stringable|string $source ) : self
    {
        \assert( arr_has_keys( $this->meta, 'source', 'origin', 'type' ) );

        if ( ! $path = (string) $source ) {
            throw new AssetException( 'Empty source provided: '.\var_export( $source, true ) );
        }

        $this->meta['origin'] = Origin::fromPath( $path );
        $this->meta['type']   = Type::resolve( $path );
        $this->meta['source'] = is_url( $path )
                ? normalize_url( $path )
                : normalize_path( $path );

        return $this;
    }

    /**
     * @param string|Stringable ...$source
     *
     * @return $this
     */
    public function __addSource( Stringable|string ...$source ) : self
    {
        if ( ! isset( $this->meta['sources'] ) ) {
            $this->meta['sources'] = [];
        }

        \assert( \is_array( $this->meta['sources'] ) );

        $sources = \is_array( $source ) ? $source : [$source];

        $origin = null;

        foreach ( $sources as $key => $path ) {
            \assert( \is_string( $path ) || $path instanceof Stringable );

            $path = normalize_path( $path );

            $from = Origin::fromPath( $path );

            $origin ??= $from;

            if ( $origin !== $from ) {
                $origin = Origin::MIXED;
            }

            $this->meta['sources'][$key] = $path;
        }

        $this->meta['origin'] = $origin;

        $this->hasChanges = true;
        return $this;
    }

    /**
     * @param bool $throwOnEmpty
     *
     * @return array<array-key,string>
     */
    public function sources( bool $throwOnEmpty = true ) : array
    {
        // \assert( $this->validateSources( $throwOnEmpty ) );
        return $this->meta['sources'];
    }

    private function assetClass( string $class ) : self
    {
        \assert( \class_exists( $class ) && \is_subclass_of( $class, Asset::class ) );
        $this->meta['class'] = $class;
        return $this;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function validateKey( string $value ) : string
    {
        $value = \strtolower( $value );
        if ( \in_array( $value, ['assetId', 'type', 'class', 'name', 'source', 'origin', 'registration'] ) ) {
            throw new InvalidArgumentException( "The '{$value}' key is read-only." );
        }

        \assert(
            \ctype_alnum( \str_replace( ['.', '-'], '', $value ) ),
            $this::class." keys must only contain ASCII characters, underscores and dashes. '".$value."' provided.",
        );

        return $value;
    }

    /**
     * @phpstan-assert-if-true array{source:array<array-key,string>} $this->meta
     *
     * @param bool $throwOnEmpty
     *
     * @return true
     */
    private function validateSources( bool $throwOnEmpty = true ) : bool
    {
        if ( $throwOnEmpty && empty( $this->meta['sources'] ) ) {
            throw new AssetException( "Meta for {$this->class} has no source." );
        }

        if ( ! isset( $this->meta['sources'] ) ) {
            $this->meta['sources'] = [];
        }

        \assert( \is_array( $this->meta['sources'] ) );

        foreach ( $this->meta['sources'] as $source ) {
            $type = Type::resolve( $source );

            $this->meta['type'] ??= $type;

            if ( $this->type !== $type ) {
                throw new AssetException(
                    "The asset source '{$source}' is not of type '{$this->type}'.",
                );
            }
        }

        return true;
    }
}
