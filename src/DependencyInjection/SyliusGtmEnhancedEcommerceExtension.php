<?php

declare(strict_types=1);

namespace StefanDoorn\SyliusGtmEnhancedEcommercePlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SyliusGtmEnhancedEcommerceExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('services.yml');

        $container->setParameter('sylius_gtm_enhanced_ecommerce.product_identifier', $config['product_identifier']);

        foreach ($config['features'] as $feature => $setting) {
            $parameter = \sprintf('sylius_gtm_enhanced_ecommerce.features.%s', $feature);

            $container->setParameter($parameter, $setting);

            if ($setting === true || (\is_array($setting) && $setting['enabled'] === true)) {
                $loader->load(\sprintf('features/%s.yml', $feature));
            }
        }

        if ($config['cache_resolvers']['enabled'] === true) {
            $loader->load('cache_services.yml');

            foreach ($config['cache_resolvers']['ttl'] as $feature => $ttl) {
                $parameter = \sprintf('sylius_gtm_enhanced_ecommerce.cache_resolver.%s', $feature);
                $container->setParameter($parameter, $ttl);
            }
        }

        foreach (['ua', 'ga4'] as $implementation) {
            $parameter = \sprintf('sylius_gtm_enhanced_ecommerce.google.%s', $implementation);
            $container->setParameter($parameter, $config[$implementation]);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['TwigBundle'])) {
            return;
        }

        $config = $this->processConfiguration($this->getConfiguration([], $container), []);
        foreach (['ua', 'ga4'] as $implementation) {
            $parameter = \sprintf('sylius_gtm_enhanced_ecommerce.google.%s', $implementation);
            $container->setParameter($parameter, $config[$implementation]);
        }

        $twig = [
            'globals' => [
                'sylius_gtm_enhanced_ecommerce_google_ua' => '%sylius_gtm_enhanced_ecommerce.google.ua%',
                'sylius_gtm_enhanced_ecommerce_google_ga4' => '%sylius_gtm_enhanced_ecommerce.google.ga4%',
            ],
        ];

        $container->prependExtensionConfig('twig', $twig);
    }
}
