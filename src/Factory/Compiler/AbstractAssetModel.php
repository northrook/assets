<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Core\Assets\Factory\Asset\Type;
use Core\Assets\Factory\AssetReference;
use Core\Assets\Interface\AssetModelInterface;
use Core\Interface\PathfinderInterface;
use const Support\AUTO;

abstract class AbstractAssetModel implements AssetModelInterface
{
    /** @var string `16` character alphanumeric */
    private readonly string $assetID;

    protected readonly string $publicRootKey;

    protected readonly string $publicAssetsKey;

    final private function __construct(
        private readonly AssetReference     $reference,
        public readonly PathfinderInterface $pathfinder,
    ) {}

    abstract protected function construct() : void;

    public function version() : string
    {
        return '?v='.\hash( 'crc32', $this->assetID );
    }

    final public function build(
        ?string $assetID = null,
        string  $publicRootKey = 'dir.public',
        string  $publicAssetsKey = 'dir.assets.public',
    ) : AssetModelInterface {
        $this->publicRootKey   = $publicRootKey;
        $this->publicAssetsKey = $publicAssetsKey;
        $this->setAssetID( $assetID );
        $this->construct();
        return $this;
    }

    final public static function fromReference(
        AssetReference      $reference,
        PathfinderInterface $pathfinder,
    ) : self {
        return new static( $reference, $pathfinder );
    }

    /**
     * @return string `lower-case.dot.notated`
     */
    final public function getName() : string
    {
        return $this->reference->name;
    }

    final public function getType() : Type
    {
        return $this->reference->type;
    }

    // final public function getPublicUrl() : string
    // {
    //     return $this->publicUrl;
    // }

    // final public function getPublicPath( bool $relative = false ) : string
    // {
    //     return $relative
    //             ? $this->pathfinder->get( (string) $this->publicPath, 'dir.public' )
    //             : (string) $this->publicPath;
    // }

    final public function getReference() : AssetReference
    {
        return $this->reference;
    }

    public function getSources() : array
    {
        return $this->reference->getSources();
    }

    final protected function assetID() : string
    {
        return $this->setAssetID( AUTO );
    }

    /**
     * @param null|string $assetID
     *
     * @return string `16` character alphanumeric
     */
    final protected function setAssetID( ?string $assetID ) : string
    {
        $this->assetID ??= $assetID ?? \hash(
            algo : 'xxh3',
            data : \implode(
                ':',
                [
                    $this::class,
                    $this->reference->name,
                    $this->reference->type->name,
                    ...\array_keys( $this->reference->getSources() ),
                ],
            ),
        );

        \assert(
            \strlen( $this->assetID ) === 16 && \ctype_alnum( $this->assetID ),
            'Asset ID must be 16 alphanumeric characters; ['.\strlen(
                $this->assetID,
            )."] `{$this->assetID}` given",
        );

        return $this->assetID;
    }
}
