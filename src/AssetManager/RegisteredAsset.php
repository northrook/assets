<?php

namespace Core\AssetManager;

use Core\Asset\{Meta, Type};
use Core\Symfony\DependencyInjection\Autodiscover;
use Attribute;
use function Support\{normalize_path, slug};
use LogicException;
use InvalidArgumentException;
use Override;
use ReflectionClass;
use Throwable;
use ValueError;
use const Support\{AUTO};

/**
 * {@see AssetDefinition} annotated with {@see RegisteredAsset} will be autoconfigured as a `service`.
 *
 * @used-by AssetManager
 *
 * @author  Martin Nielsen
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class RegisteredAsset extends Autodiscover
{
    public const string LOCATOR_ID = 'assets.service_locator';

    /** @var class-string<AssetDefinition> */
    public readonly string $className;

    /** @var non-empty-string */
    public readonly string $name;

    /** @var array<array-key, string> */
    public readonly array $sources;

    /**
     * `$source` Provide one or more source paths.
     * - Relative to `./assets/`:  `/styles/stylesheet.css`
     * - Glob patterns: `/styles/core/*.css`
     * - Full, static paths allowed
     * - Accepts remote sources
     *
     * @param Type                           $type
     * @param array<array-key,string>|string $source
     * @param ?string                        $name      [AUTO] from `className`
     * @param ?string                        $serviceId [AUTO] from `$name`
     */
    public function __construct(
        public readonly Type $type,
        string|array         $source,
        ?string              $name = AUTO,
        ?string              $serviceId = AUTO,
    ) {
        if ( $name !== AUTO ) {
            $this->name = $this->resolveName( $name );
        }

        $this->sources = $this->resolveSources( $source );

        parent::__construct(
            serviceId : $serviceId,
            tag       : [
                'assets.service_locator',
                'monolog.logger' => ['channel' => 'assets'],
            ],
            lazy      : false,
            public    : false,
            autowire  : true,
        );
    }

    public function getAssetKey() : string
    {
        $filePath = $this->getFilePath();
        $from     = \strpos( $filePath, DIR_SEP.'src'.DIR_SEP );
        $hash     = $from ? \substr( $filePath, $from + 5 ) : $filePath;
        $key      = $this->type->name();

        return \hash( 'xxh64', $this->name !== $key ? ".{$this->name}-{$hash}" : ".{$hash}" );
    }

    public function getAssetMeta( ?string $manifestDirectory ) : Meta
    {
        if ( $manifestDirectory === null ) {
            return new Meta( $this->type, $this::class, $this->name, $this->sources );
        }

        $path = $manifestDirectory.'/'.$this->getAssetKey().'.php';

        return Meta::storageBacked(
            $path,
            $this->type,
            $this::class,
            $this->name,
            $this->sources,
        );
    }

    #[Override]
    protected function serviceId() : string
    {
        if ( ! isset( $this->className ) ) {
            $message = "Could not generate RegisteredAsset->name: RegisteredAsset->className is not defined.\n";
            $message .= 'Call RegisteredAsset->registerService( .. ) when registering the asset.';
            throw new LogicException( $message );
        }

        if ( ! isset( $this->name ) ) {
            $namespaced = \explode( '\\', $this->className );
            $className  = \strtolower( \end( $namespaced ) );

            if ( \str_ends_with( $className, 'asset' ) ) {
                $className = \substr( $className, 0, -\strlen( 'asset' ) );
            }
            $this->name = $this->resolveName( $className );
        }
        return slug( $this->className );
    }

    /**
     * @param string $name
     *
     * @return non-empty-string
     */
    private function resolveName( string $name ) : string
    {
        return \trim( $name, " \n\r\t\v\0." ) ?: throw new InvalidArgumentException( 'Asset name cannot be empty.' );
    }

    /**
     * @param string|string[] $sources
     *
     * @return array<array-key, string>
     */
    private function resolveSources( string|array $sources ) : array
    {
        $sources = \is_array( $sources ) ? $sources : [$sources];

        foreach ( $sources as $key => $value ) {
            if ( ! \is_string( $value ) ) {
                throw new InvalidArgumentException( 'Invalid source: '.\gettype( $value ) );
            }

            $sources[$key] = \trim( $value, " \n\r\t\v\0." );
        }

        return $sources;
    }

    private function getFilePath() : string
    {
        try {
            $reflect  = ( new ReflectionClass( $this->className ) );
            $filePath = $reflect->getFileName() ?: throw new ValueError();
            return normalize_path( $filePath );
        }
        catch ( Throwable $exception ) {
            throw new InvalidArgumentException(
                message  : "Could not derive directory path from '{$this->className}'.\n {$exception->getMessage()}.",
                previous : $exception,
            );
        }
    }
}
