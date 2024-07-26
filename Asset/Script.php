<?php

namespace Northrook\Asset;

use Northrook\AssetGenerator\StaticAsset;
use Northrook\HTML\Element;
use Northrook\Minify;

class Script extends StaticAsset
{
    protected const FILETYPE = 'js';

    public function __construct(
        string  $source,
        array   $attributes = [],
        bool    $inline = false,
        ?string $prefix = null,
    ) {
        $this->element = new Element( 'script', $attributes );
        parent::__construct( 'script', $source, $attributes, $inline, $prefix );
    }

    protected function build() : Element {

        $this->element->id->add(
            "asset-{$this->type}-" . pathinfo( $this->getPublicURL(), PATHINFO_FILENAME ),
        );

        if ( $this->inline ) {
            $this->element->append(
                Minify::JS( $this->file->read ),
            );
        }
        else {
            $this->element->set( 'src', $this->getPublicURL() );
        }

        return $this->element;
    }
}