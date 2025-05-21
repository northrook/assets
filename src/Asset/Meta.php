<?php

/** @noinspection DuplicatedCode */

namespace Core\Asset;

use Core\AssetManager\AbstractAsset;
use Core\Exception\AssetException;
use InvalidArgumentException;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\VarExporter;
use LogicException;
use Throwable;
use function Support\{datetime, normalize_newline, normalize_path};

/**
 * @internal
 *
 * @property-read class-string<AbstractAsset> $class
 * @property-read ?string                     $id
 * @property-read Type                        $type
 * @property-read Origin                      $origin
 * @property-read string                      $url
 * @property-read string                      $source
 * @property-read array<array-key, string>    $sources
 * @property-read string                      $version
 */
final class Meta
{
    public const string               EXTENSION = 'meta';

    protected bool $hasChanges = false;

    /**
     * @param array<string, mixed> $meta
     * @param ?string              $hash
     * @param ?string              $filePath
     */
    private function __construct(
        protected array          $meta = [
            'id'      => null,
            'type'    => null,
            'class'   => null,
            'origin'  => null,
            'sources' => [],
            'public'  => [],
        ],
        private readonly ?string $hash = null,
        protected ?string        $filePath = null,
    ) {}

    public function __destruct()
    {
        if ( $this->filePath && $this->hasChanges ) {
            $this->commit();
        }
    }

    /**
     * @param class-string<AbstractAsset> $class
     */
    public static function new(
        string $class,
    ) : self {
        $meta = new self();

        return $meta->assetClass( $class );
    }

    /**
     * @param class-string<AbstractAsset> $class
     * @param string|string[]             $source
     */
    public static function create(
        string       $class,
        array|string $source,
    ) : self {
        $meta = new self();

        $meta
            ->assetClass( $class )
            ->addSource( $source )
            ->validateSources();

        return $meta->set( id : $meta->id() );
    }

    public function __get( string $name ) : mixed
    {
        return $this->meta[$name] ??= match ( $name ) {
            'class'   => $this->meta['class'],
            'id'      => $this->id(),
            'type'    => $this->type(),
            'origin'  => $this->origin(),
            'url'     => $this->url(),
            'version' => $this->version(),
            default   => throw new InvalidArgumentException(
                $this::class." has no meta '{$name}'.",
            ),
        };
    }

    /**
     * @param class-string<AbstractAsset>     $asset
     * @param array<array-key, string>|string $source
     *
     * @return string
     */
    final public static function getAssetId(
        string       $asset,
        array|string $source,
    ) : string {
        $data = [$asset];

        foreach ( (array) $source as $key => $path ) {
            $data[] = $key.$path;
        }

        return \hash( 'xxh64', \implode( '', $data ) );
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
     * @param string                         $filePath
     * @param class-string<AbstractAsset>    $class
     * @param array<array-key,string>|string $source
     * @param ?string                        $id
     *
     * @return self
     */
    public static function retrieve(
        string       $filePath,
        string       $class,
        array|string $source,
        ?string      $id = null,
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
                \is_string( $value ) || \is_array( $value ) || $value instanceof Stringable,
                $this::class.'[source] only accpets array or string, '.\gettype( $value )."' given.",
            );
            /** @var array<array-key,string|Stringable>|string|Stringable $value */
            return $this->addSource( $value );
        }

        $this->meta[$key] = $value;
        $this->hasChanges = true;

        return $this;
    }

    /**
     * @param array<array-key,string|Stringable>|string|Stringable $source
     *
     * @return $this
     */
    public function addSource( Stringable|string|array $source ) : self
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
            $this->sources,
        );
    }

    protected function type() : Type
    {
        return $this->meta['type'] ??= Type::from( $this->url() );
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
        return __METHOD__;
    }

    protected function url() : string
    {
        return __METHOD__;
    }

    protected function version() : string
    {
        return (string) \time();
    }

    /**
     * @param bool $nullable
     * @param bool $throwOnEmpty
     * @param bool $throwOnMultiple
     *
     * @return ($nullable is true ? null|string : string)
     */
    public function source(
        bool $nullable = false,
        bool $throwOnEmpty = true,
        bool $throwOnMultiple = true,
    ) : ?string {
        \assert( $this->validateSources( $throwOnEmpty ) );

        if ( $throwOnMultiple && \count( $this->meta['sources'] ) > 1 ) {
            throw new InvalidArgumentException(
                $this::class." for {$this->type->name} has multiple sources. Single source expected.",
            );
        }

        if ( $nullable ) {
            return \end( $this->sources ) ?: null;
        }

        return \end( $this->sources )
                ?: throw new AssetException(
                    $this::class." for {$this->type->name} has no source.",
                );
    }

    /**
     * @param bool $throwOnEmpty
     *
     * @return array<array-key,string>
     */
    public function sources( bool $throwOnEmpty = true ) : array
    {
        \assert( $this->validateSources( $throwOnEmpty ) );
        return $this->meta['sources'];
    }

    private function assetClass( string $class ) : self
    {
        \assert( \class_exists( $class ) && \is_subclass_of( $class, AbstractAsset::class ) );
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
            $type = Type::from( $source );

            $this->meta['type'] ??= $type;

            if ( $this->type !== $type ) {
                throw new AssetException();
            }
        }

        return true;
    }
}
