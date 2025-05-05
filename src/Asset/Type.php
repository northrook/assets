<?php

declare(strict_types=1);

namespace Core\Asset;

use InvalidArgumentException;
use ReflectionEnum;
use ReflectionException;

enum Type
{
    private const array MAP = [
        // Core AssetService Types
        'css'   => self::STYLE,
        'scss'  => self::STYLE,
        'js'    => self::SCRIPT,
        'mjs'   => self::SCRIPT,
        'png'   => self::IMAGE,
        'jpg'   => self::IMAGE,
        'jpeg'  => self::IMAGE,
        'gif'   => self::IMAGE,
        'svg'   => self::IMAGE,
        'webp'  => self::IMAGE,
        'mp4'   => self::VIDEO,
        'mov'   => self::VIDEO,
        'webm'  => self::VIDEO,
        'mp3'   => self::AUDIO,
        'wav'   => self::AUDIO,
        'ogg'   => self::AUDIO,
        'woff'  => self::FONT,
        'woff2' => self::FONT,
        'ttf'   => self::FONT,
        'otf'   => self::FONT,

        // Document AssetService Types
        'doc'  => self::DOCUMENT,
        'docx' => self::DOCUMENT,
        'pdf'  => self::DOCUMENT,
        'csv'  => self::DATA,
        'json' => self::DATA,
        'xml'  => self::DATA,
        'yml'  => self::DATA,
        'sql'  => self::DATA,
        'txt'  => self::TEXT,
        'md'   => self::TEXT,
        'rtf'  => self::TEXT,
        'xls'  => self::SPREADSHEET,
        'xlsx' => self::SPREADSHEET,
        'ppt'  => self::PRESENTATION,
        'pptx' => self::PRESENTATION,

        // Archive AssetService Types
        'zip' => self::ARCHIVE,
        'rar' => self::ARCHIVE,
        'tar' => self::ARCHIVE,
        'gz'  => self::ARCHIVE,

        // Executable AssetService Types
        'exe' => self::EXECUTABLE,
        'bat' => self::EXECUTABLE,
        'sh'  => self::EXECUTABLE,
        'deb' => self::PACKAGE,
        'rpm' => self::PACKAGE,

        // Code AssetService Types
        'php'   => self::SOURCE,
        'html'  => self::SOURCE,
        'py'    => self::SOURCE,
        'cpp'   => self::SOURCE,
        'env'   => self::CONFIG,
        'ini'   => self::CONFIG,
        'yaml'  => self::CONFIG,
        'twig'  => self::TEMPLATE,
        'latte' => self::TEMPLATE,
        'view'  => self::TEMPLATE,
        'blade' => self::TEMPLATE,

        // Design and Media AssetService Types
        'obj'    => self::MODEL,
        'psd'    => self::DESIGN,
        'sketch' => self::DESIGN,
        'ai'     => self::VECTOR,
        'eps'    => self::VECTOR,
        'penpot' => self::LAYOUT,
        'tga'    => self::TEXTURE,
        'bmp'    => self::TEXTURE,

        // Miscellaneous
        'log' => self::LOG,
        'bak' => self::BACKUP,
        'pem' => self::CERTIFICATE,
        'crt' => self::CERTIFICATE,
        'md5' => self::CHECKSUM,
        'ico' => self::ICON,
    ];

    case ABSTRACT;

    // Core AssetService Types
    case STYLE;
    case SCRIPT;
    case IMAGE;
    case VIDEO;
    case AUDIO;
    case FONT;

    // Document AssetService Types
    case DOCUMENT;
    case DATA;
    case TEXT;
    case SPREADSHEET;
    case PRESENTATION;

    // Archive AssetService Types
    case ARCHIVE;

    // Executable AssetService Types
    case EXECUTABLE;
    case PACKAGE;

    // Code AssetService Types
    case SOURCE;
    case CONFIG;
    case TEMPLATE;

    // Design and Media AssetService Types
    case MODEL;
    case DESIGN;
    case VECTOR;
    case LAYOUT;
    case TEXTURE;

    // Miscellaneous AssetService Types
    case LOG;
    case BACKUP;
    case CERTIFICATE;
    case CHECKSUM;
    case ICON;

    /**
     * @return lowercase-string
     */
    public function name() : string
    {
        return \strtolower( $this->name );
    }

    /**
     * @param bool $string
     *
     * @return ($string is true ? string : string[])
     */
    public function extensions( bool $string = false ) : array|string
    {
        $extensions = [];

        foreach ( Type::MAP as $extension => $type ) {
            if ( $type === $this ) {
                $extensions[] = $extension;
            }
        }

        if ( $string ) {
            return \implode( ', ', $extensions );
        }

        return $extensions;
    }

    /**
     * @param string|Type $string
     * @param bool        $nullable
     *
     * @return ($nullable is true ? null|static : static)
     */
    public static function from( string|Type $string, bool $nullable = false ) : ?Type
    {
        if ( $string instanceof self ) {
            return $string;
        }
        $ext = \trim( \strrchr( $string, '.' ) ?: $string, '.' );

        $type = Type::MAP[$ext] ?? null;

        if ( ! $type ) {
            $prefix     = \strtoupper( \strstr( \str_replace( ['\\', '/'], '.', $string ), '.', true ) ?: $string );
            $reflection = new ReflectionEnum( self::class );

            if ( $reflection->hasCase( $prefix ) ) {
                try {
                    $type = $reflection->getCase( $prefix )->getValue();
                }
                catch ( ReflectionException ) {
                    $type = null;
                }
            }
        }

        if ( $type instanceof self ) {
            return $type;
        }

        if ( $nullable ) {
            return null;
        }

        $enum    = self::class;
        $message = "Could not derive {$enum} from string: '{$string}'.";

        throw new InvalidArgumentException( $message );
    }
}
