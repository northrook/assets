<?php

declare(strict_types=1);

namespace Core\AssetManager\Config;

use Core\{AssetManager\AbstractAsset, AssetManager\Asset, AssetManager};
use Core\AssetManager\Config\Asset as AssetAttribute;
use Core\Symfony\Console\ListReport;
use Core\Symfony\DependencyInjection\CompilerPass;
use Support\Reflect;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Reference};

final class RegisterAssetsPass extends CompilerPass
{
    public function compile( ContainerBuilder $container ) : void
    {
        $this
            ->invokableServices()
            ->registerAssetServices();
    }

    protected function invokableServices() : self
    {
        $registeredServices = new ListReport( __METHOD__ );

        foreach ( $this->getDeclaredClasses( AbstractAsset::class ) as $class ) {
            $registeredServices->item( $class );
            // $this->container->getDefinition( $class )
            //     ->addMethodCall(
            //         'setServiceLocator',
            //         [$this->serviceLocator],
            //     );

            dump( $class );
        }

        $registeredServices->output();
        return $this;
    }

    protected function registerAssetServices() : void
    {
        $report = new ListReport( __METHOD__ );

        $assetLocator = $this->getDefinition( AssetManager::LOCATOR_ID );

        $serviceLocatorArguments = [];

        foreach ( $this->taggedViewComponents() as $serviceId ) {
            //
            $report->item( $serviceId );

            $serviceDefinition = $this->getDefinition( $serviceId, nullable : true );

            if ( $serviceDefinition === null ) {
                $report->remove(
                    $this::class." missing required '{$serviceId}' definition.",
                );

                continue;
            }

            $registeredAsset = $this->definitionViewComponentAttribute( $serviceDefinition );

            if ( $registeredAsset === null ) {
                $report->error( $serviceId );

                continue;
            }

            $serviceLocatorArguments[$serviceId] = new Reference( $serviceId );
        }

        $assetLocator->setArguments( [$serviceLocatorArguments] );

        $report->output();
    }

    /**
     * @param Definition $definition
     *
     * @return null|AssetAttribute<Asset>
     */
    private function definitionViewComponentAttribute( Definition $definition ) : ?AssetAttribute
    {
        $className = $definition->getClass() ?: 'invalid';

        if ( $className === 'invalid' || ! \class_exists( $className ) ) {
            $this->console->error(
                $this::class." class '{$className}' does not exist.",
            );
            return null;
        }

        if ( ! \is_subclass_of( $className, Asset::class ) ) {
            $this->console->error(
                "{$className} must extend the '".Asset::class."' class.",
            );
            return null;
        }

        $attribute = Reflect::getAttribute(
            $className,
            AssetAttribute::class,
        );

        return $attribute?->configure( $className );
    }

    /**
     * @return string[]
     */
    private function taggedViewComponents() : array
    {
        return \array_keys( $this->container->findTaggedServiceIds( AssetManager::LOCATOR_ID ) );
    }
}
