<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Stringable;

interface BundlableAssetInterface
{
    public function addSource( string|Stringable $source, bool $before = false ) : self;
}
