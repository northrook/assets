<?php

namespace Core\Asset;

use Core\Compiler\Hook\OnBuild;

/**
 * @phpstan-require-extends \Core\Asset
 */
interface Minifiable
{
    /**
     * @param ?bool $set
     *
     * @return bool
     */
    #[OnBuild]
    public function mergeImports( ?bool $set = null ) : bool;
}
