<?php

declare(strict_types=1);

namespace Core\Assets\Factory;

use Core\Assets\Interface\AssetHtmlInterface;
use Core\Assets\Factory\Asset\Type;
use Core\Interface\DataObject;
use Latte\Runtime as View;
use Stringable;

final readonly class AssetHtml extends DataObject implements AssetHtmlInterface
{
    /**
     * This class contains a fully resolved asset.
     *
     * @param string $name
     * @param string $assetID
     * @param Type   $type
     * @param string $html
     */
    public function __construct(
        private string $name,
        private string $assetID,
        private Type   $type,
        private string $html,
    ) {}

    /**
     * Returns fully resolved `HTML` of the asset.
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->html;
    }

    /**
     * Return the {@see Asset::$html} as {@see Stringable}.
     *
     * Will provide a {@see View\Html} object if possible.
     *
     * Pass `true` to return as `string`.
     *
     * @param bool $string [false]
     *
     * @return string|Stringable
     */
    public function getHtml( bool $string = false ) : string|Stringable
    {
        if ( \class_exists( View\Html::class ) ) {
            return new View\Html( $this->html );
        }
        return $string ? $this->html : $this;
    }

    /**
     * @return string `dot.separated` lowercase
     */
    public function name() : string
    {
        return $this->name;
    }

    /**
     * @return string `16` character alphanumeric hash
     */
    public function assetID() : string
    {
        return $this->assetID;
    }

    /**
     * Returns the asset `type` by default.
     *
     * @param null|string|Type $is
     *
     * @return bool|Type
     */
    public function type( string|Type|null $is = null ) : Type|bool
    {
        return $is ? Type::from( $is ) === $this->type : $this->type;
    }
}
