<?php

declare(strict_types=1);

namespace Core\Asset;

use Core\AssetManager\AbstractAsset;
use Core\Compiler\Hook\OnBuild;

/**
 * @phpstan-require-extends AbstractAsset
 */
trait Inlinable
{
    #[OnBuild]
    final public function prefersInline( bool $set = true ) : self
    {
        $this->meta->set( prefersInline : $set );
        return $this;
    }
}
