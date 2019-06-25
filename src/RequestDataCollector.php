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
     * @var \Illuminate\Http\Request
     */
    private $request;

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
     * @param \Illuminate\Http\Request                     $request
     * @param array                                        $config
     */
    public function __construct(Application $application, LogManager $logger, Request $request, array $config)
    {
        $this->application = $application;
        $this->logger = $logger;
        $this->request = $request;
        $this->config = $config;
        $this->requestId = $this->generateRequestId();

        $this->configureCollectors();
    }

    /**
     * Returns given collector.
     *
     * @param string $name
     *
     * @return \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface
     */
    public function getCollector(string $name): DataCollectorInterface
    {
        return $this->collectors[$name];
    }

    /**
     * Checks if Request Data Collector has given collector enabled.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    /**
     * Collects all information about given request.
     *
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
     * Returns current Request ID.
     *
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Allows to set custom Request ID. Provided Request ID has to be in format:
     * <code>X[0-9a-fA-F]{32}</code>.
     *
     * @param string $requestId
     */
    public function setRequestId(string $requestId): void
    {
        if (!$this->isValidRequestIdFormat($requestId)) {
            throw new \InvalidArgumentException('The Request ID has invalid format');
        }

        $this->requestId = $requestId;
    }

    /**
     * Returns whether Request Data Collector is enabled or not.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Checks if Request is excluded from being collected.
     *
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
     * Generates new Request ID or uses one provided in request's headers.
     *
     * @return string
     */
    private function generateRequestId(): string
    {
        if (($this->config['tracking'] ?? false) && $this->isValidRequestIdFormat($xRequestId = $this->request->header('x-request-id', ''))) {
            return $xRequestId;
        }

        // @codeCoverageIgnoreStart
        try {
            if (\function_exists('random_bytes')) {
                return 'X' . \bin2hex(\random_bytes(16));
            }
        } catch (\Exception $e) {
            // random_bytes: If it was not possible to gather sufficient entropy
        }

        return 'X' . \md5(\uniqid('', true));
        // @codeCoverageIgnoreEnd
    }

    private function configureCollectors(): void
    {
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
     * Checks if given Request ID has valid format.
     *
     * @param string $requestId
     *
     * @return bool
     */
    private function isValidRequestIdFormat(string $requestId): bool
    {
        return 1 === \preg_match('/^X[[:xdigit:]]{32}$/', $requestId);
    }
}
