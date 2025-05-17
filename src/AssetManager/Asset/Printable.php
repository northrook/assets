<?php

namespace Core\AssetManager\Asset;

use Core\AssetManager\AbstractAsset;
use Core\Compiler\Hook\SetDependencies;
use Core\Compiler\Hook;
use Core\View\Element;
use Stringable;

/**
 * @phpstan-require-extends AbstractAsset
 * @phpstan-require-implements Stringable
 */
trait Printable
{
    public readonly Element $element;

    abstract protected function render() : void;

    #[SetDependencies]
    final public function getElement() : Element
    {
        return $this->element ??= new Element();
    }

    final public function __toString() : string
    {
        $this->render();
        Hook::fire( $this );
        return $this->element->render();
    }
}
