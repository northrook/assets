<?php

declare(strict_types=1);

namespace Core\AssetManager\Config;

use Core\{AssetManager\AbstractAsset, AssetManager};
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
            ->invokableServices();
        // ->registerAssetServices();
    }

    protected function invokableServices() : self
    {
        $registeredServices = new ListReport( __METHOD__ );

        $setDependencies = [
            '$cache' => new Reference( 'cache.asset_pool' ),
        ];

        foreach ( $this->getDeclaredClasses(
            inDirectory : $this->projectDirectory.'/vendor/northrook',
            subclassOf  : AbstractAsset::class,
        ) as $className ) {
            $registeredServices->item( $className );

            $asset = $this->getDefinition(
                id           : $className,
                newOnMissing : true,
            );

            // $asset->hasTag( )

            if ( ! $asset->hasTag( AssetManager::LOCATOR_ID ) ) {
                $asset->addTag( AssetManager::LOCATOR_ID );
            }
            // if ( ! $asset->hasTag( 'monolog.logger')) {
            //     $asset->addTag( 'monolog.logger', [ 'channel' => 'assets' ] );
            // }
            $asset->addTag( 'controller.service_arguments' );

            $asset->setPublic( true );
            $asset->setAutowired( true );
            $asset->addMethodCall( 'setDependencies', $setDependencies );

            $this->container->setDefinition( $className, $asset );
        }
        // dd();

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
     * @return null|AssetAttribute<AssetAttribute>
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

        if ( ! \is_subclass_of( $className, AssetAttribute::class ) ) {
            $this->console->error(
                "{$className} must extend the '".AssetAttribute::class."' class.",
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
