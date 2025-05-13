<?php

declare(strict_types=1);

namespace Core\AssetManager;

use Core\AssetManager;
use Core\Symfony\Console\ListReport;
use Core\Symfony\DependencyInjection\CompilerPass;
use ReflectionClass;
use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Parameter, Reference};
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

final class RegisterAssetsPass extends CompilerPass
{
    protected readonly Parameter $metaDirectory;

    /**
     * @param ParamConfigurator     $metaDirectory
     * @param ReferenceConfigurator $manifest      {@see AssetManifest}
     */
    public function __construct(
        ParamConfigurator               $metaDirectory,
        protected ReferenceConfigurator $manifest,
    ) {
        $this->metaDirectory = new Parameter( (string) $metaDirectory );
    }

    public function compile( ContainerBuilder $container ) : void
    {
        $registeredServices = new ListReport( __METHOD__ );

        $assetLocator  = $this->getDefinition( AssetManager::LOCATOR_ID );
        $assetManifest = $this->getDefinition( AssetManifest::class );

        $serviceLocatorArguments = [];

        foreach ( $this->taggedViewComponents() as $serviceId ) {
            //
            $registeredServices->item( $serviceId );

            $serviceDefinition = $this->getDefinition( $serviceId, true );
            if ( $serviceDefinition === null ) {
                $registeredServices->remove(
                    $this::class." missing required '{$serviceId}' definition.",
                );

                continue;
            }

            $registeredAsset = $this->definitionViewComponentAttribute( $serviceDefinition );

            if ( $registeredAsset === null ) {
                $registeredServices->error( $serviceId );

                continue;
            }

            // $meta = $registeredAsset->getAssetMeta( $this->metaDirectory );
            // $assetManifest->addMethodCall( 'set', [$registeredAsset->className, $meta] );

            $serviceLocatorArguments[$serviceId] = new Reference( $serviceId );
        }

        $assetLocator->setArguments( [$serviceLocatorArguments] );

        // $meta = new PhpStormMeta( $this->projectDirectory );
        //
        // $meta->registerArgumentsSet(
        //     'view_component_keys',
        //     ...\array_keys( $componentProperties ),
        // );
        //
        // $generateReferences = \array_merge(
        //     [
        //         [ComponentBag::class, 'has'],
        //         [ComponentBag::class, 'get'],
        //         [ComponentFactory::class, 'render'],
        //         [ComponentFactory::class, 'has'],
        //     ],
        // );
        //
        // foreach ( $generateReferences as $generateReference ) {
        //     $meta->expectedArguments( $generateReference, [0 => 'view_component_keys'] );
        // }
        //
        // $meta->save( 'view_components' );

        $registeredServices->output();
    }

    /**
     * @param Definition $definition
     *
     * @return null|Asset<\Core\Asset>
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

        if ( ! \is_subclass_of( $className, Asset::class ) ) {
            $this->console->error(
                "{$className} must extend the '".Asset::class."' class.",
            );
            return null;
        }

        $reflectionClass = new ReflectionClass( $className );

        $viewComponentAttributes = $reflectionClass->getAttributes( Asset::class );

        /** @var Asset<\Core\Asset> $registeredAsset */
        $registeredAsset = $viewComponentAttributes[0]->newInstance();
        /** @noinspection PhpInternalEntityUsedInspection */
        $registeredAsset->registerService( $className );

        return $registeredAsset;
    }

    /**
     * @return string[]
     */
    private function taggedViewComponents() : array
    {
        return \array_keys( $this->container->findTaggedServiceIds( AssetManager::LOCATOR_ID ) );
    }
}
