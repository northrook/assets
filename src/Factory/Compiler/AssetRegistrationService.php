<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Stringable;

/**
 * Can be extended to register assets that require access to the service container.
 */
abstract class AssetRegistrationService
{
    /** @var array<string, bool> */
    protected array $sources = [];

    final public function addSource( null|string|Stringable ...$add ) : void
    {
        foreach ( $add as $source ) {
            $source = (string) $source;
            if ( $source ) {
                $this->sources[$source] = true;
            }
        }
    }

    /**
     * @return string[]
     */
    final public function getSources() : array
    {
        return \array_keys( \array_filter( $this->sources ) );
    }
}
