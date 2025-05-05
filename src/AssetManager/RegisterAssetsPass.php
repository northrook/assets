<?php

namespace Core\AssetManager;

use Core\{AssetManager, Pathfinder, Symfony\Console\ListReport};
use Core\Symfony\DependencyInjection\CompilerPass;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Parameter, Reference};
use Symfony\Component\Config\Loader\ParamConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use ReflectionClass;

final class RegisterAssetsPass extends CompilerPass
{
    private readonly string $pathfinderId;

    private readonly string $locatorId;

    private readonly string $factoryId;

    private readonly ?string $cacheId;

    protected readonly Definition $pathfinderDefinition;

    protected readonly Definition $locatorDefinition;

    protected readonly Definition $factoryDefinition;

    protected readonly Definition $cacheDefinition;

    /**
     * @param ParamConfigurator          $manifestDirectory
     * @param ReferenceConfigurator      $pathfinder        {@see Pathfinder}
     * @param null|ReferenceConfigurator $cache             {@see CacheItemPoolInterface}
     */
    public function __construct(
        protected ParamConfigurator      $manifestDirectory,
        protected ReferenceConfigurator  $pathfinder,
        protected ?ReferenceConfigurator $cache = null,
    ) {
        $this->cache?->nullOnInvalid();

        $this->locatorId    = RegisteredAsset::LOCATOR_ID;
        $this->factoryId    = AssetManager::class;
        $this->pathfinderId = $this->pathfinder->__toString();
        $this->cacheId      = $this->cache?->__toString();
    }

    public function compile( ContainerBuilder $container ) : void
    {
        if ( $this->validateRequiredServices( $container ) ) {
            return;
        }
        $this->registerAnnotatedAssets();
    }

    protected function registerAnnotatedAssets() : void
    {
        $registeredServices = new ListReport( __METHOD__ );

        $serviceLocatorArguments = [];

        foreach ( $this->taggedViewComponents() as $serviceId ) {
            //
            $registeredServices->item( $serviceId );
            if ( ! $this->container->hasDefinition( $serviceId ) ) {
                $message = $this::class." missing required '{$serviceId}' definition.";

                $registeredServices->remove( $message );

                continue;
            }

            $serviceDefinition = $this->container->getDefinition( $serviceId );

            $registeredAsset = $this->definitionViewComponentAttribute( $serviceDefinition );

            if ( $registeredAsset === null ) {
                $registeredServices->error( $serviceId );

                continue;
            }

            $assetMeta = $registeredAsset->getAssetMeta(
                new Parameter( (string) $this->manifestDirectory ),
            );

            $serviceDefinition->addMethodCall(
                'setDependencies',
                [
                    $assetMeta,
                    new Reference( $this->pathfinderId ),
                    $this->cacheId ? new Reference( $this->cacheId ) : null,
                ],
            );

            $serviceLocatorArguments[$serviceId] = new Reference( $serviceId );
        }

        $this->locatorDefinition->setArguments( [$serviceLocatorArguments] );

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

    private function definitionViewComponentAttribute( Definition $definition ) : ?RegisteredAsset
    {
        $className = $definition->getClass() ?: 'invalid';

        if ( ! \class_exists( $className ) ) {
            $message = $this::class." class '{$className}' does not exist.";
            $this->console->error( $message );
            // continue
            return null;
        }

        $reflectionClass = new ReflectionClass( $className );

        $viewComponentAttributes = $reflectionClass->getAttributes( RegisteredAsset::class );

        /** @var RegisteredAsset $registeredAsset */
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
        return \array_keys( $this->container->findTaggedServiceIds( $this->locatorId ) );
    }

    private function validateRequiredServices( ContainerBuilder $container ) : bool
    {
        if ( $container->hasDefinition( $this->locatorId ) ) {
            $this->locatorDefinition = $container->getDefinition( $this->locatorId );
        }
        else {
            $message = $this::class." cannot find required '{$this->locatorId}' definition.";
            $this->console->error( $message );
            return true;
        }

        if ( $container->hasDefinition( $this->factoryId ) ) {
            $this->factoryDefinition = $container->getDefinition( $this->factoryId );
        }
        else {
            $message = $this::class." cannot find required '{$this->factoryId}' definition.";
            $this->console->error( $message );
            return true;
        }

        if ( $container->hasDefinition( $this->pathfinderId ) ) {
            $this->pathfinderDefinition = $container->getDefinition( $this->pathfinderId );
        }
        else {
            $message = $this::class." cannot find required '{$this->pathfinderId}' definition.";
            $this->console->error( $message );
            return true;
        }

        if ( $this->cacheId && $container->hasDefinition( $this->cacheId ) ) {
            $this->cacheDefinition = $container->getDefinition( $this->cacheId );
        }
        else {
            $this->cacheDefinition = new Definition();
        }

        return false;
    }
}
