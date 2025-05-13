<?php

declare(strict_types=1);

namespace Core\AssetManager;

use Attribute;
use Core\Asset\Type;
use Core\AssetManager;
use Core\Exception\{AssetException, TypeException};
use Core\Symfony\DependencyInjection\Autodiscover;
use InvalidArgumentException;
use LogicException;
use Override;
use Stringable;
use function Support\{is_url, slug, normalize_path, normalize_url};
use const Support\AUTO;

/**
 * Reference this asset using:
 * - {@see Asset::$name}
 * - {@see Asset::$className}
 * - {@see Asset::$ppublicPath}
 *
 * @extends Autodiscover<\Core\Asset> registered as a {@see service}.
 * @used-by \Core\AssetManager
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class Asset extends Autodiscover implements Stringable
{
    /** @var non-empty-string */
    public readonly string $name;

    /** @var non-empty-string */
    public readonly string $publicPath;

    /** @var array<array-key, string> */
    public readonly array $source;

    public readonly Type $type;

    /**
     * `$source` Provide one or more source paths.
     * - Relative to `./assets/`:  `/style/stylesheet.css`
     * - Glob patterns: `/style/core/*.css`, `/image/*`
     * - Full, static paths allowed
     * - Accepts remote sources
     *
     * @param array<array-key,string>|string $source
     * @param ?string                        $publicPath
     * @param ?Type                          $type       [AUTO] from `source`
     * @param ?string                        $name       [AUTO] from `className`
     * @param ?string                        $serviceId  [AUTO] from `$name`
     */
    public function __construct(
        string|array $source,
        ?string      $publicPath = AUTO,
        ?Type        $type = AUTO,
        ?string      $name = AUTO,
        ?string      $serviceId = AUTO,
    ) {
        if ( $name !== AUTO ) {
            $this->name = $this->resolveName( $name );
        }

        if ( $publicPath !== AUTO ) {
            $this->publicPath = $this->resolvePublicPath( $publicPath );
        }

        [$this->source, $this->type] = $this->resolveSources( $source, $type );

        parent::__construct(
            serviceId : $serviceId,
            tag       : [
                AssetManager::LOCATOR_ID,
                'monolog.logger' => ['channel' => 'assets'],
            ],
            lazy      : false,
            public    : false,
            autowire  : true,
        );
    }

    public function __toString() : string
    {
        return $this->name;
    }

    #[Override]
    protected function serviceId() : string
    {
        if ( ! isset( $this->className ) ) {
            $message = "Could not generate RegisteredAsset->name: RegisteredAsset->className is not defined.\n";
            $message .= 'Call RegisteredAsset->registerService( .. ) when registering the asset.';
            throw new LogicException( $message );
        }

        return slug( $this->className );
    }

    #[Override]
    protected function register() : void
    {
        if ( ! isset( $this->name ) ) {
            $namespaced = \explode( '\\', $this->className );
            $className  = \strtolower( \end( $namespaced ) );

            if ( \str_ends_with( $className, 'asset' ) ) {
                $className = \substr( $className, 0, -\strlen( 'asset' ) );
            }
            $this->name = $this->resolveName( $className );
        }

        if ( ! isset( $this->publicPath ) ) {
            $extension = $this->type->extensions();

            if ( empty( $extension ) || \count( $extension ) !== 1 ) {
                throw new LogicException(
                    "Unable to autogenerate `publicPath` {$this->className}."
                        .'The extension could not be derived.',
                );
            }

            $type = $this->type->name();

            $this->publicPath = $this->resolvePublicPath(
                "/{$type}/{$this->name}.{$extension[0]}",
            );
        }
    }

    /**
     * @param string $name
     *
     * @return non-empty-string
     */
    private function resolveName( string $name ) : string
    {
        return \trim( $name, " \n\r\t\v\0." )
                ?: throw new InvalidArgumentException( 'AbstractAsset name cannot be empty.' );
    }

    /**
     * @param string|string[] $sources
     * @param ?Type           $type
     *
     * @return array<array-key, string>
     */
    private function resolveSources(
        string|array $sources,
        ?Type        $type = AUTO,
    ) : array {
        if ( ! $sources ) {
            throw new AssetException( 'Could not resolve asset sources.' );
        }
        $sources = \is_array( $sources ) ? $sources : [$sources];

        foreach ( $sources as $key => $value ) {
            if ( ! \is_string( $value ) ) {
                throw new TypeException( 'string', $value );
            }

            $source = normalize_path( $value, true );
            $type ??= Type::from( $source );

            $sources[$key] = $source;
        }

        return [$sources, $type];
    }

    /**
     * @param string $publicPath
     *
     * @return non-empty-string
     */
    private function resolvePublicPath( string $publicPath ) : string
    {
        if ( empty( $publicPath ) ) {
            throw new InvalidArgumentException(
                $this::class.'$publicPath cannot be empty.',
            );
        }

        if ( $publicPath[0] !== '/' ) {
            throw new InvalidArgumentException(
                $this::class."{$publicPath} must be relative to '/'.",
            );
        }

        if ( ! is_url( $publicPath ) ) {
            throw new InvalidArgumentException( 'Public path must be URL.' );
        }

        return normalize_url( $publicPath )
                ?: throw new InvalidArgumentException(
                    $this::class.'$publicPath cannot be empty.',
                );
    }
}
