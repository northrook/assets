<?php

declare(strict_types=1);

namespace Core\Asset;

use InvalidArgumentException, ReflectionEnum, ReflectionException;

/**
 * - {@see Type::$name}  `UPPERCASE`
 * - {@see Type::$value} `lowercase`
 */
enum Type : string
{
    private const array MAP = [
        // Core Asset Types
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

        // Document Asset Types
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

        // Archive Asset Types
        'zip' => self::ARCHIVE,
        'rar' => self::ARCHIVE,
        'tar' => self::ARCHIVE,
        'gz'  => self::ARCHIVE,

        // Executable Asset Types
        'exe' => self::EXECUTABLE,
        'bat' => self::EXECUTABLE,
        'sh'  => self::EXECUTABLE,
        'deb' => self::PACKAGE,
        'rpm' => self::PACKAGE,

        // Code Asset Types
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

        // Design and Media Asset Types
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

    // Undefined Type
    case NULL = 'null';

    // Core Asset Types
    case STYLE  = 'style';
    case SCRIPT = 'script';
    case IMAGE  = 'image';
    case VIDEO  = 'video';
    case AUDIO  = 'audio';
    case FONT   = 'font';

    // Document Asset Types
    case DOCUMENT     = 'document';
    case DATA         = 'data';
    case TEXT         = 'text';
    case SPREADSHEET  = 'spreadsheet';
    case PRESENTATION = 'presentation';

    // Archive Asset Types
    case ARCHIVE = 'archive';

    // Executable Asset Types
    case EXECUTABLE = 'executable';
    case PACKAGE    = 'package';

    // Code Asset Types
    case SOURCE   = 'source';
    case CONFIG   = 'config';
    case TEMPLATE = 'template';

    // Design and Media Asset Types
    case MODEL   = 'model';
    case DESIGN  = 'design';
    case VECTOR  = 'vector';
    case LAYOUT  = 'layout';
    case TEXTURE = 'texture';

    // Miscellaneous Asset Types
    case LOG         = 'log';
    case BACKUP      = 'backup';
    case CERTIFICATE = 'certificate';
    case CHECKSUM    = 'checksum';
    case ICON        = 'icon';

    /**
     * Returns a `dot.notated` key.
     *
     * @param ?string $append
     *
     * @return non-empty-lowercase-string
     */
    final public function key( ?string $append = null ) : string
    {
        static $className = null;
        $className ??= \strtolower( \strtr( $this::class, '\\', '.' ) );
        $key = [$className, $this->value];
        if ( $append ) {
            \assert(
                \ctype_alnum( \str_replace( ['.', '_'], '', $append ) ),
                'Keys only allow alphanumeric characters, underscores, and periods.',
            );
            $key[] = \trim( $append, '.' );
        }
        return \strtolower( \implode( '.', $key ) );
    }

    /**
     * @param bool $pluralize
     *
     * @return lowercase-string
     */
    final public function name( bool $pluralize = false ) : string
    {
        return $pluralize ? \rtrim( $this->value, 's' ).'s' : $this->value;
    }

    final public function extension( ?string $string = null ) : string
    {
        $extensions = $this->extensions();

        if ( $string ) {
            $dot = $string[0] === '.' ? '.' : '';
            $get = \strtolower( \trim( $string, '.' ) );
            $ext = $extensions[$get] ?? null;

            if ( ! $ext ) {
                \assert(
                    \ctype_alnum( $get ),
                    'File extensions may only contain alphanumeric characters.',
                );
                throw new InvalidArgumentException(
                    "'{$string}' is not a valid extension for the '{$this->name}' ".$this::class.'.',
                );
            }

            return $dot.$ext;
        }

        return \reset( $extensions )
                ?: throw new InvalidArgumentException(
                    "No extension for '{$this->name}' ".$this::class.'.',
                );
    }

    /**
     * @param false|string $implode
     *
     * @return ($implode is string ? string : string[])
     */
    final public function extensions( false|string $implode = false ) : array|string
    {
        /** @var array<string,array<string,string>> $extensions */
        static $extensions = [];

        if ( empty( $extensions ) ) {
            foreach ( Type::MAP as $extension => $type ) {
                $extensions[$type->name][$extension] = $extension;
            }
        }

        if ( $implode ) {
            return \implode( $implode, $extensions[$this->name] );
        }

        return $extensions[$this->name];
    }

    /**
     * @param string|Type $string
     * @param bool        $nullable
     *
     * @return ($nullable is true ? null|static : static)
     */
    final public static function resolve( string|Type $string, bool $nullable = false ) : ?Type
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
