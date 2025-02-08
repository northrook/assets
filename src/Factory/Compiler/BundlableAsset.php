<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Core\Pathfinder\Path;
use Stringable;

trait BundlableAsset
{
    /** @var array{before: Path[]|string[], source: Path[]|string[], after: Path[]|string[]} */
    protected array $sources = [
        'before' => [],
        'source' => [],
        'after'  => [],
    ];

    final public function getSources() : array
    {
        $this->sources['source'] = $this->getReference()->getSources( true );

        return [
            ...$this->sources['before'],
            ...$this->sources['source'],
            ...$this->sources['after'],
        ];
    }

    final public function addSource( string|Stringable $source, bool $before = false ) : self
    {
        if ( $before ) {
            $this->sources['before'][] = $source instanceof Path ? $source : new Path( $source );
        }
        else {
            $this->sources['after'][] = $source instanceof Path ? $source : new Path( $source );
        }
        return $this;
    }
}
