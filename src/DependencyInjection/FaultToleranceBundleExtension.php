<?php

declare(strict_types=1);

namespace MicroModule\FaultToleranceBundle\DependencyInjection;

use MicroModule\FaultTolerance\CircuitBreaker\CircuitBreakerInterface;
use MicroModule\FaultTolerance\RabbitEnqueue\QueueFaultTolerantConsumer;
use MicroModule\FaultTolerance\RabbitEnqueue\QueueFaultTolerantProducer;
use MicroModule\FaultTolerance\RabbitEnqueue\QueueFaultTolerantRouterProcessor;
use DeepCopy\DeepCopy;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * Class FaultToleranceBundleExtension.
 *
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class FaultToleranceBundleExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    /**
     * ContainerBuilder global.
     *
     * @var ContainerBuilder
     */
    private $container;

    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container): void
    {
        $this->container = $container;
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['EnqueueBundle'])) {
            $enqueueConfig = $container->getExtensionConfig('enqueue');
            $clients = ['enqueue' => ['clients' => array_keys($enqueueConfig[0])]];
            $container->prependExtensionConfig($this->getAlias(), $clients);
        }
    }

    /**
     * Returns the bundle configuration alias.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return 'micro_fault_tolerance';
    }

    /**
     * Configures the passed container according to the merged configuration.
     *
     * @param mixed[]          $mergedConfig
     * @param ContainerBuilder $container
     *
     * @throws Exception
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('fault_tolerance.yml');

        if (isset($mergedConfig['enqueue']) && $mergedConfig['enqueue']['enabled'] && isset($mergedConfig['enqueue']['clients'])) {
            foreach ($mergedConfig['enqueue']['clients'] as $enqueueClient) {
                $config = [
                    'client' => $enqueueClient,
                    'retryAttemps' => $mergedConfig['enqueue']['retryAttemps'],
                    'retryTimeout' => $mergedConfig['enqueue']['retryTimeout'],
                ];
                $this->buildQueueConsumer($container, $config);
                $this->buildQueueProducer($container, $config);
                $this->buildRouterProcessor($container, $config);
            }
        }
    }

    /**
     * Bind QueueConsumer service locator.
     *
     * @param ContainerBuilder $container
     * @param mixed[]          $config
     */
    private function buildQueueConsumer(ContainerBuilder $container, array $config): void
    {
        $originQueueConsumer = sprintf('enqueue.client.%s.queue_consumer', $config['client']);
        $faultTolerantConsumer = sprintf('fault_tolerance.enqueue.client.%s.queue_consumer', $config['client']);
        $container->register($faultTolerantConsumer, QueueFaultTolerantConsumer::class)
            ->addArgument(new Reference($originQueueConsumer))
            ->addArgument(new Reference(CircuitBreakerInterface::class))
            ->addArgument($config['retryTimeout']);
        $this->addServiceToLocator($container, $faultTolerantConsumer);
    }

    /**
     * Add service to enqueue service locator.
     *
     * @param ContainerBuilder $container
     * @param string           $serviceName
     */
    private function addServiceToLocator(ContainerBuilder $container, string $serviceName): void
    {
        $locatorId = 'enqueue.locator';

        if ($this->container->hasDefinition($locatorId)) {
            $locator = $this->container->getDefinition($locatorId);
            $map = $locator->getArgument(0);
            $map[$serviceName] = new Reference($serviceName);
            $locator->replaceArgument(0, $map);
        }
    }

    /**
     * Bind QueueConsumer service locator.
     *
     * @param ContainerBuilder $container
     * @param mixed[]          $config
     */
    private function buildQueueProducer(ContainerBuilder $container, array $config): void
    {
        $originQueueProducer = sprintf('enqueue.client.%s.producer', $config['client']);
        $faultTolerantProducer = sprintf('fault_tolerance.enqueue.client.%s.producer', $config['client']);
        $container->register($faultTolerantProducer, QueueFaultTolerantProducer::class)
            ->addArgument(new Reference($originQueueProducer))
            ->addArgument(new Reference(CircuitBreakerInterface::class))
            ->addArgument(new Reference(DeepCopy::class))
            ->addArgument($config['retryTimeout']);
        $this->addServiceToLocator($container, $faultTolerantProducer);
    }

    /**
     * Bind RouterProcessor service locator.
     *
     * @param ContainerBuilder $container
     * @param mixed[]          $config
     */
    private function buildRouterProcessor(ContainerBuilder $container, array $config): void
    {
        $originRouterProcessor = sprintf('enqueue.client.%s.router_processor', $config['client']);
        $faultTolerantRouterProcessor = sprintf('fault_tolerance.enqueue.client.%s.router_processor', $config['client']);
        $container->register($faultTolerantRouterProcessor, QueueFaultTolerantRouterProcessor::class)
            ->addArgument(new Reference($originRouterProcessor))
            ->addArgument(new Reference(CircuitBreakerInterface::class))
            ->addArgument($config['retryTimeout']);
        $this->addServiceToLocator($container, $faultTolerantRouterProcessor);
    }
}
