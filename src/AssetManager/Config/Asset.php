<?php

declare(strict_types=1);

namespace Core\AssetManager\Config;

use Attribute;
use Core\AssetManager\AbstractAsset;
use Core\AssetManager\Asset\Type;
use Core\AssetManager;
use Core\Exception\{AssetException, TypeException};
use Core\Symfony\DependencyInjection\Autodiscover;
use InvalidArgumentException;
use LogicException;
use Override;
use Stringable;
use function Support\{normalize_path, normalize_url};
use const Support\AUTO;

/**
 * Reference this asset using:
 * - {@see Asset::$name}
 * - {@see Asset::$className}
 * - {@see Asset::$publicPath}
 *
 * @extends Autodiscover<AbstractAsset> registered as a {@see service}.
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

    public string $baseDirectory = 'assets';

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
            $this->name = $this->validateName( $name );
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
    protected function register() : void
    {
        if ( ! isset( $this->name ) ) {
            // $namespaced = \explode( '\\', $this->className );
            // $className  = \strtolower( \end( $namespaced ) );
            // $typeName   = $this->type->name();
            //
            // if ( \str_ends_with( $className, $typeName ) ) {
            //     $className = \substr( $className, 0, -\strlen( $typeName ) );
            // }

            $this->name = $this->validateName( $this->serviceId );
        }

        if ( ! isset( $this->publicPath ) ) {
            $extension = $this->type->extensions();

            if ( \count( $extension ) === 1 ) {
                $extension = $extension[0];
            }
            else {
                $extension = null;

                foreach ( $this->source as $source ) {
                    $sourceExt = \pathinfo( $source, PATHINFO_EXTENSION );
                    if ( $sourceExt ) {
                        $extension = $sourceExt;

                        break;
                    }
                }
            }

            if ( ! $extension ) {
                throw new LogicException(
                    "Unable to autogenerate `publicPath` {$this->className}."
                        .'The extension could not be derived.',
                );
            }

            $path = "/{$this->baseDirectory}/{$this->type->name()}/{$this->name}.{$extension}";

            $this->publicPath = $this->resolvePublicPath( $path );
        }
    }

    /**
     * @param string $name
     *
     * @return non-empty-string
     */
    private function validateName( string $name ) : string
    {
        return \trim( $name, " \n\r\t\v\0." )
                ?: throw new InvalidArgumentException( 'AbstractAsset name cannot be empty.' );
    }

    /**
     * @param string|string[] $sources
     * @param ?Type           $type
     *
     * @return array{0:array<array-key, string>, 1: Type}
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

        return normalize_url( $publicPath )
                ?: throw new InvalidArgumentException(
                    $this::class.'$publicPath cannot be empty.',
                );
    }
}
