<?php

declare(strict_types=1);

namespace Core\Assets\Factory;

use Core\Assets\Factory\Asset\Type;
use Core\Pathfinder\Path;
use Stringable, InvalidArgumentException;

final class AssetReference implements Stringable
{
    /** @var string `lower-case.dot.notated` */
    public readonly string $name;

    /** @var string `type.name` */
    public readonly string $reference;

    /**
     * @param Type     $type
     * @param string   $name    `lower-case.dot.notated`
     * @param string[] $sources `sourcePath[]`
     */
    public function __construct(
        public readonly Type $type,
        string               $name,
        protected array      $sources = [],
    ) {
        \assert(
            \ctype_alpha( \str_replace( ['.', '-'], '', $name ) ),
            "Asset names must only contain ASCII characters, underscores and dashes. {$name} provided.",
        );

        $type = \strtolower( $this->type->name );
        $name = \strtolower( \trim( $name, '.' ) );

        $fragments = \array_filter( \explode( '.', $name ) );

        $this->reference = \implode( '.', $fragments );

        if ( $fragments[0] === $type ) {
            \array_shift( $fragments );
        }

        $this->name = \implode( '.', $fragments );
    }

    /**
     * @return string `AssetReference->name`
     */
    public function __toString() : string
    {
        return $this->reference;
    }

    /**
     * @param string|Stringable $path
     * @param ?string           $key
     * @param bool              $override
     *
     * @return void
     */
    public function addSource(
        string|Stringable $path,
        ?string           $key = null,
        bool              $override = false,
    ) : void {
        if ( ! $path instanceof Path ) {
            $path = new Path( $path );
        }

        if ( $path->isDirectory() ) {
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
     * Sort the {@see self::$sources} array before serializing.
     *
     * @return array{type: Type, name: string, sources: string[]}
     */
    public function __serialize() : array
    {
        \ksort( $this->sources );
        return [
            'type'      => $this->type,
            'name'      => $this->name,
            'reference' => $this->reference,
            'sources'   => $this->sources,
        ];
    }

    /**
     * @param array{type: Type, name: string, sources: string[]} $data
     *
     * @return void
     */
    public function __unserialize( array $data ) : void
    {
        $this->type      = $data['type'];
        $this->name      = $data['name'];
        $this->reference = $data['reference'];
        $this->sources   = $data['sources'];
    }
}
