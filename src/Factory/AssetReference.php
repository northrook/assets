<?php

declare(strict_types=1);

namespace Core\Assets\Factory;

use Core\Assets\Factory\Asset\Type;
use Stringable;
use function Support\isPath;

final class AssetReference implements Stringable
{
    /** @var string `lower-case.dot.notated` */
    public readonly string $name;

    /** @var string `relative` */
    public readonly string $publicUrl;

    /** @var array<string, string> */
    protected array $sources = [];

    public function __toString() : string
    {
        return $this->name;
    }

    /**
     * Generate an asset configuration reference.
     *
     * @param string            $name
     * @param Type              $type
     * @param string|Stringable ...$source
     *
     * @return array<string, string|string[]>
     */
    public static function config(
        string               $name,
        Type                 $type,
        string|Stringable ...$source,
    ) : array {
        // Validate and stringify sources
        foreach ( $source as $index => $parameter ) {
            $path = (string) $parameter;

            if ( isPath( $path ) ) {
                \assert( \file_exists( $path ), __METHOD__.' accepts only valid paths and paramter keys.' );
            }

            $source[$index] = (string) $path;
        }

        /** @var string[] $source */

        return [
            'name'   => $name,
            'type'   => $type->name,
            'source' => $source,
        ];
    }
}
