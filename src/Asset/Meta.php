<?php

declare(strict_types=1);

namespace Core\Asset;

use Core\AssetManager\{DetachedAsset, RegisteredAsset};
use Core\Exception\AssetException;
use Core\Interface\DataInterface;
use InvalidArgumentException;
use Northrook\Logger\Log;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\VarExporter;
use function Support\{datetime, normalize_newline};
use LogicException;
use Throwable;

final class Meta implements DataInterface
{
    /** @var array<string,mixed> */
    private array $data;

    private ?string $hash = null;

    private false|string $filePath = false;

    protected bool $hasChanges = false;

    /**
     * @param Type                                        $type
     * @param class-string<DetachedAsset|RegisteredAsset> $class
     * @param ?string                                     $name
     * @param array<array-key,string>                     $sources
     * @param mixed                                       ...$meta
     */
    public function __construct(
        public readonly Type $type,
        string               $class,
        ?string              $name,
        array                $sources,
        mixed             ...$meta,
    ) {
        $this->data = [
            'assetId' => $meta['assetId'] ?? null,
            'type'    => $type,
            'class'   => $class,
            'name'    => $name,
            'sources' => $sources,
        ];

        unset( $meta['assetId'] );

        foreach ( $meta as $key => $value ) {
            $key              = $this->validateKey( $key );
            $this->data[$key] = $value;
        }
    }

    public function __destruct()
    {
        if ( $this->filePath && $this->hasChanges ) {
            $this->commit();
        }
    }

    /**
     * @param string                                      $filePath
     * @param Type                                        $type
     * @param class-string<DetachedAsset|RegisteredAsset> $class
     * @param ?string                                     $name
     * @param array<array-key,string>                     $sources
     * @param mixed                                       ...$meta
     *
     * @return self
     */
    public static function storageBacked(
        string   $filePath,
        Type     $type,
        string   $class,
        ?string  $name,
        array    $sources,
        mixed ...$meta,
    ) : self {
        if ( \file_exists( $filePath ) ) {
            [$data, $hash] = require_once $filePath;
            $meta          = new Meta( ...$data );
            $meta->hash    = $hash;
        }
        else {
            $meta['assetId']  = \basename( $filePath, '.php' );
            $meta             = new Meta( $type, $class, $name, $sources, ...$meta );
            $meta->hasChanges = true;
        }
        $meta->filePath = $filePath;
        return $meta;
    }

    public function getName() : string
    {
        return $this->data['name'] ?? throw new AssetException(
            $this::class." for {$this->type->name} has no defined or generated name.",
        );
    }

    /**
     * @param bool $nullable
     * @param bool $throwOnEmpty
     * @param bool $throwOnMultiple
     *
     * @return ($nullable is true ? null|string : string)
     */
    public function getSource(
        bool $nullable = false,
        bool $throwOnEmpty = true,
        bool $throwOnMultiple = true,
    ) : mixed {
        \assert( $this->validateSources( $throwOnEmpty ) );

        if ( $throwOnMultiple && \count( $this->data['sources'] ) > 1 ) {
            throw new InvalidArgumentException(
                $this::class." for {$this->type->name} has multiple sources. Single source expected.",
            );
        }

        if ( $nullable ) {
            return $this->data['sources'][0] ?? null;
        }

        return $this->data['sources'][0] ?? throw new AssetException(
            $this::class." for {$this->type->name} has no source.",
        );
    }

    /**
     * @param bool $throwOnEmpty
     *
     * @return array<array-key,string>
     */
    public function getSources( bool $throwOnEmpty = true ) : array
    {
        \assert( $this->validateSources( $throwOnEmpty ) );
        return $this->data['sources'];
    }

    public function getVersion() : int
    {
        $version = null;

        foreach ( $this->getSources() as $source ) {
            if ( \file_exists( $source ) && ( $modTime = \filemtime( $source ) ) ) {
                $version = \max( $version, $modTime );
            }
        }

        return $version ?? throw new AssetException(
            $this::class." for {$this->type->name} has no version.",
        );
    }

    public function getAssetId() : string
    {
        return $this->data['assetId'] ?? \hash( 'xxh64', $this->getName().\implode( '.', $this->getSources() ) );
    }

    /**
     * @param array<array-key,string>|string $source
     *
     * @return $this
     */
    public function addSource( string|array $source ) : self
    {
        \assert( $this->validateSources( false ) );
        $sources = \is_array( $source ) ? $source : [$source];

        foreach ( $sources as $key => $path ) {
            $this->data['sources'][$key] = $path;
        }

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
        return $this->data[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function all() : array
    {
        return $this->data;
    }

    public function has( string $key ) : bool
    {
        return isset( $this->data[$key] );
    }

    /**
     * @param int|string $value
     *
     * @return string
     */
    private function validateKey( int|string $value ) : string
    {
        if ( ! \is_string( $value ) ) {
            throw new InvalidArgumentException( $this::class.' keys must be strings.' );
        }

        $value = \strtolower( $value );
        if ( \in_array( $value, ['assetId', 'type', 'class'] ) ) {
            throw new InvalidArgumentException( "The '{$value}' key is read-only." );
        }

        \assert(
            \ctype_alnum( \str_replace( ['.', '-'], '', $value ) ),
            $this::class." keys must only contain ASCII characters, underscores and dashes. '".$value."' provided.",
        );

        return $value;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function set( string $key, mixed $value ) : self
    {
        $key = $this->validateKey( $key );

        if ( \array_key_exists( $key, $this->data ) && $this->data[$key] === $value ) {
            return $this;
        }

        $this->data[$key] = $value;
        $this->hasChanges = true;
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
                    ? \json_encode( $this->data, JSON_THROW_ON_ERROR )
                    : normalize_newline( VarExporter::export( $this->data ) );
        }
        catch ( Throwable $e ) {
            throw new LogicException( $e->getMessage(), $e->getCode(), $e );
        }
    }

    public function commit(
        ?string $generator = null,
        bool    $force = false,
    ) : bool {
        if ( $this->filePath === false ) {
            throw new LogicException( 'Cannot commit '.$this::class.' without a file path.' );
        }

        if ( $force ) {
            $this->hasChanges = true;
        }

        // Do not attempt to commit anything if nothing has changed
        if ( ! $force && ( empty( $this->data ) || $this->hasChanges === false ) ) {
            return false;
        }

        $dataExport      = $this->export();
        $storageDataHash = \hash( algo : 'xxh3', data : $dataExport );

        if ( ! $force && $storageDataHash === ( $this->hash ?? null ) ) {
            Log::info( $this->getName().': Matches hashes, no changes to commit.' );
            return false;
        }

        $dateTime = datetime();

        $timestamp          = $dateTime->getTimestamp();
        $formattedTimestamp = $dateTime->format( 'Y-m-d H:i:s e' );
        $generator ??= $this::class;

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
            
               Name      : {$this->getName()}
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

    /**
     * @phpstan-assert-if-true array{sources:array} $this->data
     *
     * @param bool $throwOnEmpty
     *
     * @return true
     */
    private function validateSources( bool $throwOnEmpty = true ) : bool
    {
        $class = $this::class;
        $asset = $this->type->name;

        if ( ! \array_key_exists( 'sources', $this->data ) ) {
            throw new AssetException( "{$class} for {$asset} has no source." );
        }

        if ( ! \is_array( $this->data['sources'] ) ) {
            $type = \gettype( $this->data['sources'] );

            throw new AssetException(
                "{$class} for {$asset} has invalid source value. 'Array' expected, '{$type}' given.",
            );
        }

        if ( $throwOnEmpty && empty( $this->data['sources'] ) ) {
            throw new AssetException( "{$class} for {$asset} has no source." );
        }

        return true;
    }
}
