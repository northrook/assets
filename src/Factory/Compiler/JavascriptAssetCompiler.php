<?php

declare(strict_types=1);

namespace Core\Assets\Factory\Compiler;

use Core\Pathfinder\Path;
use Northrook\JavaScriptMinifier;
use Stringable;

final class JavascriptAssetCompiler
{
    private const string NEWLINE = "\n";

    private readonly Path $source;

    protected string $content;

    public function __construct( string|Stringable $source )
    {
        $this->source  = $source instanceof Path ? $source : new Path( $source );
        $this->content = $this->normalizeNewline(
            (string) $this->source->getContents( true ),
        );
    }

    public function minify() : string
    {
        return ( new JavaScriptMinifier( $this->content ) )->minify();
    }

    public function compile( bool $minify = false ) : string
    {
        $this->bundleImportStatements();

        return $minify ? $this->minify() : $this->content;
    }

    final public function bundleImportStatements() : self
    {
        $importCount = \substr_count( $this->content, 'import ' ) + 1;
        $parseLines  = \explode( self::NEWLINE, $this->content, $importCount );

        foreach ( $parseLines as $line => $string ) {
            if ( ! \str_starts_with( $string, 'import ' ) ) {
                continue;
            }

            $importPath = $this->importStatement( $string );

            if ( $importPath->isFile() && $importPath->isReadable() ) {
                $parseLines[$line] = $importPath->getContents();
            }
        }

        $this->content = \implode( self::NEWLINE, $parseLines );

        return $this;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    final protected function normalizeNewline( string $string ) : string
    {
        return \str_replace( [PHP_EOL, "\r\n", "\r"], $this::NEWLINE, $string );
    }

    /**
     * @TODO Handle URL imports
     *
     * @param string $string
     *
     * @return Path
     */
    private function importStatement( string $string ) : Path
    {
        // Trim import statement, quotes and whitespace, and slashes
        $fileName = \trim( \substr( $string, \strlen( 'import ' ) ), " \n\r\t\v\0'\"/\\" );

        if ( ! \str_ends_with( $fileName, '.js' ) ) {
            $fileName .= '.js';
        }

        return new Path( $this->source->getPath()."/{$fileName}" );
    }
}
