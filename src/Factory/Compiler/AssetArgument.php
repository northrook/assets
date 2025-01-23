<?php

namespace Core\Assets\Factory\Compiler;

use Core\Assets\Interface\AssetModelInterface;
use Core\Symfony\Interface\ArgumentInterface;
use Core\Assets\Factory\Asset\{Type};

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
        return [
            $method,
            [
                $reference,
                [self::class, 'filter'],
            ],
        ];
    }

    abstract public static function filter( AssetModelInterface $asset ) : AssetModelInterface;
}

/*
 array{string, array{string, callable(): mixed}}
array{'addAssetReferenceCa…'|'addAssetTypeCallback', array{Core\Assets\Factory\Asset\Type|string, array{'Core\\Assets\\Factory\\Compiler\\AssetArgument', 'filter'}}}.
 */
