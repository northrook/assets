<?php

declare(strict_types=1);

namespace Core\Asset;

use Core\AssetManager\AbstractAsset;
use Core\Compiler\Hook\{SetDependencies};
/**
 * @phpstan-require-extends AbstractAsset
 */
trait Inlinable
{
    /**
     * @param ?bool $set
     *
     * @return bool
     */
    #[SetDependencies]
    final public function prefersInline( ?bool $set = null ) : bool
    {
        if ( $set !== null || ! $this->meta->has( 'prefersInline' ) ) {
            $this->meta->set(
                prefersInline : $set ?? $this->getSetting( 'prefersInline', true ),
            );
        }
            dump( \get_defined_vars(), $this->meta );
        return $this->meta->get( 'prefersInline', $set );
    }
}
