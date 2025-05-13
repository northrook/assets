<?php

namespace Core\Asset;

use Core\Asset;
use Core\Compiler\{Hook};
use Core\View\Element;
use Stringable;

/**
 * @phpstan-require-extends Asset
 * @phpstan-require-implements Stringable
 */
trait Printable
{
    public readonly Element $element;

    abstract protected function render() : void;

    #[Hook]
    final public function getElement() : Element
    {
        return $this->element ??= new Element();
    }

    final public function __toString() : string
    {
        $this->render();
        return $this->element->render();
    }
}
