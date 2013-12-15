<?php

namespace Ecommerce\Bundle\ElasticsearchBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

/**
 * @author Philipp Wahala <philipp.wahala@gmail.com>
 */
class EcommerceElasticsearchExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritDoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['FOSElasticaBundle'])) {
            $configs = $container->getExtensionConfig($this->getAlias());
            $parameterBag = $container->getParameterBag();
            $configs = $parameterBag->resolveValue($configs);
            $config = $this->processConfiguration(new Configuration(), $configs);

            $config = array(
                'indexes' => array(
                    $config['index'] => array(
                        'types' => array(
                            $config['type'] => array(
                                'mappings' => array(
                                    'name' => null, //array(),
                                ),
                            ),
                        ),
                    ),
                ),
            );

            $container->prependExtensionConfig('fos_elastica', $config);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['FOSElasticaBundle'])) {
            $container->setAlias('ecommerce_elasticsearch.elastica.index', 'fos_elastica.index.'.$config['index']);
            $container->setAlias('ecommerce_elasticsearch.elastica.type', 'fos_elastica.index.'.$config['index'].'.'.$config['type']);
        }
    }
}
