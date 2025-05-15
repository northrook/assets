<?php

declare(strict_types=1);

namespace Core\AssetManager\Asset;

use Core\AssetManager\Asset;
use Core\Compiler\Hook\SetDependencies;

/**
 * @phpstan-require-extends Asset
 */
trait Inlinable
{
    #[SetDependencies]
    final public function prefersInline( bool $set = true ) : self
    {
        $this->meta->set( prefersInline : $set );
        return $this;
    }
}
