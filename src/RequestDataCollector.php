<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class RequestDataCollector
{
    /**
     * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface[]
     */
    private $collectors = [];

    /**
     * @var \Illuminate\Log\LogManager
     */
    private $logger;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $application;

    /**
     * @var array
     */
    private $config;

    /**
     * @param \Illuminate\Contracts\Foundation\Application $application
     * @param \Illuminate\Log\LogManager                   $logger
     * @param array                                        $config
     *
     * @throws \Exception
     */
    public function __construct(Application $application, LogManager $logger, array $config)
    {
        $this->application = $application;
        $this->logger = $logger;
        $this->config = $config;
        $this->requestId = $this->generateRequestId();

        foreach ($this->config['collectors'] as $collectorName => $enabled) {
            if (!$enabled) {
                continue;
            }

            $collectorInstance = $this->application->make($this->config['options'][$collectorName]['driver']);

            if ($collectorInstance instanceof ConfigurableInterface) {
                $collectorInstance->setConfig($this->config['options'][$collectorName]);
            }

            if ($collectorInstance instanceof ModifiesContainerInterface) {
                $collectorInstance->register($this->application);
            }

            $this->collectors[$collectorName] = $collectorInstance;
        }
    }

    /**
     * @param string $name
     *
     * @return \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface
     */
    public function getCollector(string $name): DataCollectorInterface
    {
        return $this->collectors[$name];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    public function collect(Response $response): void
    {
        /**
         * @var \Psr\Log\LoggerInterface $channel
         */
        $channel = $this->logger->channel($this->config['channel']);

        foreach ($this->collectors as $collectorName => $collector) {
            if ($collector instanceof UsesResponseInterface) {
                $collector->setResponse($response);
            }

            $channel->debug(\sprintf('request-data-collector.%s.%s', $collectorName, $this->requestId), $collector->collect());
        }
    }

    /**
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    public function isRequestExcluded(Request $request): bool
    {
        foreach ($this->config['exclude'] as ['filter' => $filter, 'with' => $options]) {
            /**
             * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface $filterInstance
             */
            $filterInstance = $this->application->make($filter, $options);

            if ($filterInstance->accept($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \Exception
     *
     * @return string
     *
     * @codeCoverageIgnore
     */
    private function generateRequestId(): string
    {
        if (\function_exists('random_bytes')) {
            return 'X' . \bin2hex(\random_bytes(16));
        }

        return 'X' . \md5(\uniqid('', true));
    }
}
