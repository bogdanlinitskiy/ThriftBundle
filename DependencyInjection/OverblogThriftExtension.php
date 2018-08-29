<?php

/*
 * This file is part of the OverblogThriftBundle package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\ThriftBundle\DependencyInjection;

use Overblog\ThriftBundle\CacheWarmer\ThriftCompileCacheWarmer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class OverblogThriftExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('thrift.config.compiler.path', $config['compiler']['path']);
        $container->setParameter('thrift.config.services', $config['services']);
        $container->setParameter('thrift.config.servers', $config['servers']);

        // Register clients
        foreach ($config['clients'] as $name => $client) {
            $this->loadClient($name, $client, $container, $config['testMode']);
        }


        $cacheDir = $container->getParameter('kernel.cache_dir');

        $warmer = new ThriftCompileCacheWarmer(
            $cacheDir,
            $container->getParameter('kernel.root_dir'),
            $container->getParameter('thrift.config.compiler.path'),
            $container->getParameter('thrift.config.services')
        );

        $warmer->compile();

    }

    /**
     * Create client service.
     *
     * @param string           $name
     * @param array            $client
     * @param ContainerBuilder $container
     * @param bool             $testMode
     */
    protected function loadClient($name, array $client, ContainerBuilder $container, $testMode = false)
    {
        $clientDef = new Definition(
            $container->getParameter(
                $testMode ? 'thrift.client.test.class' : 'thrift.client.class'
            )
        );

        $clientDef->addArgument(new Reference('thrift.factory'));
        $clientDef->addArgument($client);

        $container->setDefinition(
            sprintf('thrift.client.%s', $name),
            $clientDef
        );
    }
}
