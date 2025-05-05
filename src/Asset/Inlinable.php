<?php

declare(strict_types=1);

namespace Core\Asset;

/**
 * @phpstan-require-extends \Core\AssetManager\AssetDefinition
 */
trait Inlinable
{
    final public function prefersInline( bool $set = true ) : self
    {
        $this->meta->set( 'prefersInline', $set );
        return $this;
    }
}
