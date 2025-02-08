<?php

declare(strict_types=1);

namespace Core\Assets\Exception;

use JetBrains\PhpStorm\Pure;
use Throwable, Exception;
use const HTTP\FAILED_DEPENDENCY_424;

/**
 * @TODO Add `$didYouMean` support.
 */
final class UndefinedAssetReferenceException extends Exception
{
    /**
     * @param string         $key
     * @param string[]       $didYouMean
     * @param null|string    $message
     * @param null|Throwable $previous
     */
    #[Pure]
    public function __construct(
        public readonly string $key,
        array                  $didYouMean = [],
        ?string                $message = null,
        ?Throwable             $previous = null,
    ) {
        $message ??= $this->message( $didYouMean );
        parent::__construct( $message, FAILED_DEPENDENCY_424, $previous );
    }

    /**
     * @param string[] $alternatives
     *
     * @return string
     */
    private function message( array $alternatives ) : string
    {
        return "Missing expected asset: '{$this->key}'. Try running AssetLocator->discover() to update the Manifest.";
    }
}
