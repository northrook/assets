<?php

namespace Core\Asset;

use Core\Asset;
use Stringable;

/**
 * @internal
 *
 * @phpstan-require-extends Asset
 * @phpstan-require-implements Stringable
 */
trait Printable
{
    /**
     * @return string
     * @final
     */
    final public function getHtml() : string
    {
        return $this->render()->__toString();
    }

    abstract protected function render() : self;

    abstract public function __toString() : string;
}
