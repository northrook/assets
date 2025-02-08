<?php

declare(strict_types=1);

namespace Core\Assets\Exception;

use ValueError, Throwable;

final class EmptyAssetException extends ValueError
{
    public function __construct( string $name, ?string $assetID = null, ?Throwable $previous = null )
    {
        if ( $assetID ) {
            $name .= "#{$assetID}";
        }
        $message = "The asset '{$name}' source is empty after compilation.";

        parent::__construct( $message, 500, $previous );
    }
}
