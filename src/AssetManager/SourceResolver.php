<?php

declare(strict_types=1);

namespace Core\AssetManager;

use Core\Asset\Type;
use Core\Interface\DataInterface;
use SplFileObject;
use Stringable;
use Support\Curl;
use Support\Curl\CurlException;
use ValueError;
use InvalidArgumentException;
use function Support\{is_path, is_url, normalize_path, normalize_url, slug};
use const Support\AUTO;

/**
 * @internal
 */
final class SourceResolver implements DataInterface, Stringable
{
    private SplFileObject $splFileObject;

    private Type $type;

    private bool $isRemote;

    protected ?string $localPath = null;

    protected ?string $remoteUrl = null;

    private string $tempDirectory;

    private ?string $assetsDirectory;

    public function __construct(
        string|Stringable $source,
        ?string           $assetsDirectory = null,
        ?string           $tempDirectory = null,
        protected bool    $preferRemote = false,
    ) {
        $this->setDirectories(
            $assetsDirectory,
            $tempDirectory,
        );

        if ( is_url( $source ) ) {
            $this->remoteUrl = normalize_url( $source );
        }
        elseif ( is_path( $source ) ) {
            $this->localPath = normalize_path( $source );
        }
        else {
            throw new InvalidArgumentException(
                $this::class." '{$source}' is not a valid local or remote path",
            );
        }

        $this->isRemote = $this->remoteUrl !== null;

        \assert( $this->localPath || $this->remoteUrl );
        \assert( is_path( $this->localPath ) );
        \assert( ! $this->isRemote || is_url( $this->remoteUrl ) );
    }

    public function setDirectories(
        ?string $assetsDirectory,
        ?string $tempDirectory = AUTO,
    ) : self {
        if ( $assetsDirectory ) {
            if ( ! \is_dir( $assetsDirectory ) ) {
                \mkdir( $assetsDirectory, 0777, true );
            }
            $this->assetsDirectory = normalize_path( $assetsDirectory );
        }

        $tempDirectory ??= \sys_get_temp_dir().DIR_SEP.slug( $this::class );

        if ( ! \is_dir( $tempDirectory ) ) {
            \mkdir( $tempDirectory, 0777, true );
        }

        $this->tempDirectory = normalize_path( $tempDirectory );

        return $this;
    }

    public function type() : Type
    {
        return $this->type ??= Type::from( $this->remoteUrl ?? $this->localPath );
    }

    public function prefersRemote( bool $set = null ) : bool
    {
        if ( $set !== null ) {
            $this->preferRemote = $set;
        }

        return $this->preferRemote;
    }

    public function isRemote() : bool
    {
        return $this->isRemote;
    }

    public function localExists() : bool
    {
        return \file_exists( $this->localPath );
    }

    /**
     * Returns either the {@see self::$localPath} or {@see self::$remoteUrl}.
     *
     * @return string
     */
    public function getPath() : string
    {
        if ( $this->preferRemote && $this->remoteUrl ) {
            return $this->remoteUrl;
        }

        return $this->localPath;
    }

    /**
     * @param bool $fetchRemote
     *
     * @return string {@see self::$localPath}
     */
    public function getLocalPath( bool $fetchRemote = true ) : string
    {
        if ( ! $this->localPath ) {
            \assert(
                \is_dir( $this->assetsDirectory ),
                'Please run SourceResolver->setDirectories() first.',
            );
            $this->localPath = normalize_path(
                [$this->assetsDirectory, $this->type()->name(), \basename( $this->remoteUrl )],
            );
        }

        if ( $fetchRemote && ! \file_exists( $this->localPath ) && $this->isRemote ) {
            $this->fetchRemoteSource();
        }

        return $this->localPath;
    }

    /**
     * @param int                $timeout   Session timeout in seconds
     * @param ?non-empty-string  $userAgent
     * @param null|CurlException $hasError
     *
     * @return bool
     */
    public function remoteExists(
        int            $timeout = 5,
        ?string        $userAgent = AUTO,
        ?CurlException & $hasError = null,
    ) : bool {
        if ( ! $this->remoteUrl ) {
            return false;
        }

        try {
            return Curl::probe(
                url               : $this->remoteUrl,
                throwOnError      : true,
                CURLOPT_TIMEOUT   : $timeout,
                CURLOPT_USERAGENT : $userAgent,
            );
        }
        catch ( CurlException $exception ) {
            $hasError = $exception;
        }

        return false;
    }

    /**
     * @param int               $timeout   Session timeout in seconds
     * @param ?non-empty-string $userAgent
     *
     * @return bool
     */
    public function fetchRemoteSource(
        int     $timeout = 32,
        ?string $userAgent = AUTO,
    ) : bool {
        if ( ! $this->remoteUrl ) {
            return false;
        }

        if ( ! $this->assetsDirectory || ! \is_dir( $this->assetsDirectory ) ) {
            throw new ValueError( __METHOD__.' requires a valid directory.' );
        }

        $curl = new Curl(
            options       : [CURLOPT_TIMEOUT => $timeout],
            tempDirectory : $this->tempDirectory,
        );

        if ( $userAgent ) {
            $curl->setUserAgent( $userAgent );
        }

        return $curl->download(
            $this->remoteUrl,
            $this->localPath,
        );
    }

    /**
     * @return null|string {@see self::$remoteUrl}
     */
    public function getRemoteUrl() : ?string
    {
        return $this->remoteUrl;
    }

    public function getSplFileObject() : SplFileObject
    {
        return $this->splFileObject ??= new SplFileObject( $this->localPath );
    }

    /**
     * Returns the canonicalized absolute path if the file exists, else {@see self::$localPath}.
     *
     * @return string
     */
    public function getRealPath() : string
    {
        return $this->getSplFileObject()->getRealPath() ?: $this->localPath;
    }

    public function __toString() : string
    {
        return $this->getPath();
    }
}
