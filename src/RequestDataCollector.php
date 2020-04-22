<?php
declare(strict_types=1);

namespace Miquido\RequestDataCollector;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Miquido\RequestDataCollector\Collectors\Contracts\ConfigurableInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ModifiesContainerInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\ThinkOfBetterNameInterface;
use Miquido\RequestDataCollector\Collectors\Contracts\UsesResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class RequestDataCollector
{
    public const LOGGING_FORMAT_SINGLE = 'single';

    public const LOGGING_FORMAT_SEPARATE = 'separate';

    /**
     * @var float
     */
    private static $startedAt = -1.0;

    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    private $container;

    /**
     * @var \Illuminate\Log\LogManager
     */
    private $logger;

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var \Miquido\RequestDataCollector\Collectors\Contracts\DataCollectorInterface[]
     */
    private $collectors = [];

    public function __construct(Container $container, LogManager $logger, Request $request, array $config)
    {
        if (self::$startedAt < 0.0) {
            self::$startedAt = \microtime(true);
        }

        $this->container = $container;
        $this->logger = $logger;
        $this->request = $request;
        $this->config = $config;
        $this->requestId = $this->generateRequestId();

        $this->configureCollectors();
    }

    /**
     * Returns timestamp when Request Data Collector was initialized.
     * The value of -1.0 means Request Data Collector has never been initialized.
     */
    public static function getStartedAt(): float
    {
        return self::$startedAt;
    }

    /**
     * Returns given collector.
     */
    public function getCollector(string $name): DataCollectorInterface
    {
        return $this->collectors[$name];
    }

    /**
     * Checks if Request Data Collector has given collector enabled.
     */
    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    /**
     * Collects all information about given request.
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

            $collected = $collector->collect();

            if (
                !empty($collected) &&
                $collector instanceof ThinkOfBetterNameInterface &&
                (
                    self::LOGGING_FORMAT_SEPARATE === ($this->config['logging_format'] ?? self::LOGGING_FORMAT_SINGLE) ||
                    (
                        $collector instanceof ConfigurableInterface &&
                        self::LOGGING_FORMAT_SEPARATE === $collector->getConfig('logging_format', self::LOGGING_FORMAT_SINGLE)
                    )
                )
            ) {
                foreach ($collector->getThinkOfBetterName($collected) as $index => $entry) {
                    $channel->debug(\sprintf('request-data-collector.%s.%s.%s', $collectorName, $index, $this->requestId), $entry);
                }
            } else {
                $channel->debug(\sprintf('request-data-collector.%s.%s', $collectorName, $this->requestId), $collected);
            }
        }
    }

    /**
     * Returns current Request ID.
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Allows to set custom Request ID. Provided Request ID has to be in format:
     * <code>X[0-9a-fA-F]{32}</code>.
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
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Checks if Request is excluded from being collected.
     */
    public function isRequestExcluded(Request $request): bool
    {
        foreach ($this->config['exclude'] as ['filter' => $filter, 'with' => $options]) {
            /**
             * @var \Miquido\RequestDataCollector\Filters\Contracts\FilterInterface $filterInstance
             */
            $filterInstance = $this->container->make($filter, $options);

            if ($filterInstance->accept($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates new Request ID or uses one provided in request's headers.
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

            $collectorInstance = $this->container->make($this->config['options'][$collectorName]['driver']);

            if ($collectorInstance instanceof ConfigurableInterface) {
                $collectorInstance->setConfig($this->config['options'][$collectorName]);
            }

            if ($collectorInstance instanceof ModifiesContainerInterface) {
                $collectorInstance->register($this->container);
            }

            $this->collectors[$collectorName] = $collectorInstance;
        }
    }

    /**
     * Checks if given Request ID has valid format.
     */
    private function isValidRequestIdFormat(string $requestId): bool
    {
        return 1 === \preg_match('/^X[[:xdigit:]]{32}$/', $requestId);
    }
}
