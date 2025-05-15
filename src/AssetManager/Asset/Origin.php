<?php

declare(strict_types=1);

namespace Core\AssetManager\Asset;

use function Support\is_url;
use Stringable;
use InvalidArgumentException;

enum Origin : string
{
    /** The source file lives on the local filesystem. `/path/to/file.ext` */
    case LOCAL = 'Local';

    /** The source file lives on a remote server. `//domain.tdl/path/to/file.ext` */
    case REMOTE = 'Remote';

    /** The source file lives on a CDN. `//cdn..` */
    case CDN = 'CDN';

    /** The source contains both local and remote files. */
    case MIXED = 'Mixed';

    /**
     * @param string|Stringable $value
     * @param bool              $throwOnInvalid
     *
     * @return self
     */
    public static function fromPath(
        string|Stringable $value,
        bool              $throwOnInvalid = false,
    ) : self {
        $path = (string) $value;

        if ( \file_exists( $path ) ) {
            return self::LOCAL;
        }

        if ( is_url( $path ) ) {
            if ( \str_contains( $path, '//cdn.' ) ) {
                return self::CDN;
            }

            return self::REMOTE;
        }

        if ( $throwOnInvalid ) {
            throw new InvalidArgumentException(
                __METHOD__." '{$path}' is not a valid origin",
            );
        }

        return self::MIXED;
    }
}
