<?php

declare(strict_types=1);

namespace Core\AssetManager\Config;

use Core\AssetManager;
use Core\AssetManager\AbstractAsset;
use Core\Symfony\Console\ListReport;
use Core\Symfony\DependencyInjection\CompilerPass;
use Support\Reflect;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Reference};
final class RegisterAssetsPass extends CompilerPass
{
    private readonly ListReport $report;

    public function compile( ContainerBuilder $container ) : void
    {
        $this->report = new ListReport( __METHOD__ );

        $this
            ->invokableServices();
        // ->registerAssetServices();

        $this->report->output();
    }

    protected function invokableServices() : self
    {
        foreach ( $this->getDeclaredClasses(
            inDirectory : $this->projectDirectory.'/vendor/northrook',
            subclassOf  : AbstractAsset::class,
        ) as $className ) {
            $definition = $this->getDefinition(
                id           : $className,
                newOnMissing : true,
            );

            $definition
                ->addTag( AssetManager::LOCATOR_ID )
                ->addTag( 'controller.service_arguments' )
                ->addTag( 'monolog.logger', ['channel' => 'assets'] );

            $definition
                ->setPublic( true )
                ->setAutowired( true );

            $this->report->item( $className );

            $this->container->setDefinition( $className, $definition );
        }

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
     * @return null|Asset<AbstractAsset>
     */
    private function definitionViewComponentAttribute( Definition $definition ) : ?Asset
    {
        $className = $definition->getClass() ?: 'invalid';

        if ( $className === 'invalid' || ! \class_exists( $className ) ) {
            $this->console->error(
                $this::class." class '{$className}' does not exist.",
            );
            return null;
        }

        if ( ! \is_subclass_of( $className, AbstractAsset::class ) ) {
            $this->console->error(
                "{$className} must extend the '".AbstractAsset::class."' class.",
            );
            return null;
        }

        $attribute = Reflect::getAttribute(
            $className,
            Asset::class,
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
