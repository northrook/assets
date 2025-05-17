<?php

declare(strict_types=1);

namespace Core\AssetManager\Asset;

use Core\AssetManager\AbstractAsset;
use Core\Compiler\Hook\SetDependencies;

/**
 * @phpstan-require-extends AbstractAsset
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
