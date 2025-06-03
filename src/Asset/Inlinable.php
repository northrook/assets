<?php

namespace Core\Asset;

use Core\Asset;
use Core\Compiler\Hook\{OnBuild};

/**
 * @phpstan-require-extends Asset
 */
interface Inlinable
{
    /**
     * @param ?bool $set
     *
     * @return bool
     */
    #[OnBuild]
    public function prefersInline( ?bool $set = null ) : bool;
}
