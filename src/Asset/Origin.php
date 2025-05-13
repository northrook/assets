<?php

namespace Core\Asset;

use function Support\is_url;

enum Origin
{
    /** The source file lives on the local filesystem. `/path/to/file.ext` */
    case LOCAL;

    /** The source file lives on a remote server. `//domain.tdl/path/to/file.ext` */
    case REMOTE;

    /** The source file lives on a CDN. `//cdn..` */
    case CDN;

    /** The source contains both local and remote files. */
    case MIXED;

    public static function from( string $path ) : self
    {
        if ( \file_exists( $path ) ) {
            return self::LOCAL;
        }

        if ( is_url( $path ) ) {
            if ( \str_contains( $path, '//cdn.' ) ) {
                return self::CDN;
            }

            return self::REMOTE;
        }

        return self::MIXED;
    }
}
