<?php

declare(strict_types=1);

namespace Core\Assets;

use Core\Asset;
use Core\View\Element;
use Core\Asset\{Inlinable, Printable};
use Stringable;

class StyleAsset extends Asset implements Stringable
{
    use Printable, Inlinable;

    protected function build() : void
    {
        $this->element->attributes->set( 'asset-id', $this->meta->id );
    }

    protected function render() : void
    {
        if ( $this->meta->get( 'prefersInline', true ) ) {
            $this->getInlineHtml();
        }
        else {
            $this->getStyleHtml();
        }
    }

    public function getInlineHtml() : Element
    {
        $this->element->tag->set( 'style' );
        $this->element->content( ['inline' => 'CSS'] );

        return $this->element;
    }

    public function getStyleHtml() : Element
    {
        $this->element->tag->set( 'link' );
        $this->element->attributes(
            href : 'CSS',
            rel  : 'stylesheet',
        );

        return $this->element;
    }
}
