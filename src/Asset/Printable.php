<?php

namespace Core\Asset;

use Core\AssetManager\AbstractAsset;
use Core\Compiler\Hook\OnBuild;
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

    #[OnBuild]
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
