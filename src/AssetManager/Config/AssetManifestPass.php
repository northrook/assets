<?php

declare(strict_types=1);

namespace Core\AssetManager\Config;

use Core\{AssetManager, AssetManager\AssetManifest};
use Core\Symfony\Console\ListReport;
use Core\Symfony\DependencyInjection\CompilerPass;
use Symfony\Component\DependencyInjection\{ContainerBuilder};
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

final class AssetManifestPass extends CompilerPass
{
    /**
     * @param ReferenceConfigurator $manifest {@see AssetManifest}
     */
    public function __construct( protected ReferenceConfigurator $manifest ) {}

    public function compile( ContainerBuilder $container ) : void
    {
        // $registeredServices = new ListReport( __METHOD__ );

        $assetLocator  = $this->getDefinition( AssetManager::LOCATOR_ID, true );
        $assetManifest = new AssetManifest(
            ...$this->getDefinition( $this->manifest, true )->getArguments(),
        );

        dump( $assetLocator, $assetManifest );
        // $registeredServices->output();
    }

    /**
     * @return string[]
     */
    private function taggedViewComponents() : array
    {
        return \array_keys( $this->container->findTaggedServiceIds( AssetManager::LOCATOR_ID ) );
    }
}
