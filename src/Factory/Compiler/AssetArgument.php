<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Core\Symfony\Interface\ArgumentInterface;
use Core\Assets\Factory\Asset\Type;
use Core\Assets\Interface\AssetModelInterface;

/**
 * @method static AssetModelInterface filter( AssetModelInterface $model )
 */
abstract class AssetArgument implements ArgumentInterface
{
    /**
     * @param string|Type $reference
     *
     * @return array{'addAssetReferenceCallback'|'addAssetTypeCallback', array{string|Type, callable}}
     */
    public static function callback( string|Type $reference ) : array
    {
        $method = $reference instanceof Type ? 'addAssetTypeCallback' : 'addAssetReferenceCallback';
        return [$method, [$reference, [static::class, 'filter']]];
    }
}
