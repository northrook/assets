<?php

declare(strict_types=1);

namespace Core\Assets\Interface;

use Core\Assets\Factory\Asset\Type;
use Core\Assets\Factory\AssetReference;
use Core\PathfinderInterface;
use RuntimeException;

interface AssetModelInterface
{
    public static function fromReference(
        AssetReference      $reference,
        PathfinderInterface $pathfinder,
    ) : self;

    /**
     * @param ?string $assetID
     *
     * @return self
     *
     * @throws RuntimeException
     */
    public function build( ?string $assetID = null ) : self;

    public function getName() : string; // {type}.{name}.{dir|variant}

    // public function getPublicPath() : string;

    /**
     * @return string[]
     */
    public function getSources() : array;

    public function getType() : Type;

    public function getReference() : AssetReference;

    /**
     * @param null|array<string, null|bool|float|int|string> $attributes
     *
     * @return AssetHtmlInterface
     */
    public function render( ?array $attributes = null ) : AssetHtmlInterface;

    /**
     * Get the asset version.
     *
     * @return string
     */
    public function version() : string;
}
