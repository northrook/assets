<?php

declare(strict_types=1);

namespace Core\Asset;

use Core\Asset;
use Core\Compiler\Hook;

/**
 * @phpstan-require-extends Asset
 */
trait Inlinable
{
    #[Hook]
    final public function prefersInline( bool $set = true ) : self
    {
        $this->meta->set( prefersInline : $set );
        return $this;
    }
}
