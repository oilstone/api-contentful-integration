<?php

namespace Oilstone\ApiContentfulIntegration\Integrations\Laravel\Integrations\SystemStatus;

use Contentful\Core\Api\BaseClient;
use Contentful\Delivery\ClientOptions;
use Illuminate\Contracts\Config\Repository;
use Oilstone\ApiContentfulIntegration\Clients\Delivery;
use Oilstone\ApiContentfulIntegration\Clients\Management;
use Oilstone\ApiContentfulIntegration\Clients\Preview;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Factory;
use Oilstone\ApiContentfulIntegration\Integrations\Laravel\Integration;
use Oilstone\SystemStatus\Concerns\MakesHttpRequests;
use Oilstone\SystemStatus\Exceptions\InvalidMonitorConfiguration;
use Oilstone\SystemStatus\Monitors\Monitor;
use Oilstone\SystemStatus\Probe;
use Throwable;

/**
 * Checks a configured Contentful space can be reached with its credentials. Clients are built
 * directly rather than through the Factory, so responses are never cached and the request's
 * locale, debug logging and untimed HTTP client don't apply.
 *
 * Options:
 * - api: delivery (default), preview or management
 * - context: a key of contentful.environments, checked with that context's credentials or the
 *   default space's where it has no token for the API (default space when omitted)
 * - name: overrides "Contentful {Api} ({context})"
 * - timeout, connect_timeout, options: see MakesHttpRequests
 */
class ContentfulMonitor extends Monitor
{
    use MakesHttpRequests;

    protected array $thresholds = [1000 => 'optimal', 3000 => 'acceptable'];

    protected string $token;

    protected string $space;

    protected string $environment;

    public function __construct(array $config, protected Repository $settings)
    {
        parent::__construct($config);
    }

    public function name(): string
    {
        if (isset($this->config['name'])) {
            return (string) $this->config['name'];
        }

        $context = $this->context();

        return 'Contentful ' . ucfirst($this->api()) . ($context !== null ? ' (' . $context . ')' : '');
    }

    public function check(Probe $probe): void
    {
        $probe->meta('space', $this->space)->meta('environment', $this->environment);

        try {
            match ($this->api()) {
                'delivery', 'preview' => $this->checkDelivery($probe),
                'management' => $this->checkManagement(),
            };
        } catch (Throwable $e) {
            $this->fail(sprintf('%s API request failed: %s', ucfirst($this->api()), $this->redactUrls($e->getMessage())), $e);
        }
    }

    protected function configure(): void
    {
        if (! in_array($this->api(), Factory::APIS, true)) {
            throw InvalidMonitorConfiguration::invalid($this->name(), 'api must be one of ' . implode(', ', Factory::APIS));
        }

        $context = $this->context();

        if ($context !== null && ! array_key_exists($context, (array) $this->settings->get('contentful.environments'))) {
            throw InvalidMonitorConfiguration::invalid($this->name(), sprintf('context "%s" is not defined in contentful.environments', $context));
        }

        $credentials = Factory::credentials($this->api(), $context);
        $missing = [];

        if ($this->blank($credentials['token'])) {
            $missing[] = $context !== null
                ? sprintf('contentful.environments.%s.%s.token or contentful.%s.token', $context, $this->api(), $this->api())
                : sprintf('contentful.%s.token', $this->api());
        }

        if ($this->blank($credentials['space'])) {
            $missing[] = $this->usesContext() ? sprintf('contentful.environments.%s.space', $context) : 'contentful.space';
        }

        if ($missing) {
            throw InvalidMonitorConfiguration::missing($this->name(), $missing);
        }

        $this->token = (string) $credentials['token'];
        $this->space = (string) $credentials['space'];
        $this->environment = $this->blank($credentials['environment']) ? 'master' : (string) $credentials['environment'];
    }

    protected function checkDelivery(Probe $probe): void
    {
        $options = (new ClientOptions())->withHttpClient($this->sdkHttpClient());

        $client = $this->api() === 'preview'
            ? new Preview($this->token, $this->space, $this->environment, $options->usingPreviewApi())
            : new Delivery($this->token, $this->space, $this->environment, $options);

        $locales = $this->withIntegration($client)->getEnvironment()->getLocales();

        $probe->meta('locales', count($locales));
    }

    protected function checkManagement(): void
    {
        $client = new Management($this->token, ['guzzle' => $this->sdkHttpClient()]);

        $this->withIntegration($client)->getEnvironment($this->space, $this->environment);
    }

    /**
     * @template T of BaseClient
     *
     * @param  T  $client
     * @return T
     */
    protected function withIntegration(BaseClient $client): BaseClient
    {
        $client->useIntegration(new Integration);

        return $client;
    }

    protected function api(): string
    {
        $api = $this->config['api'] ?? 'delivery';

        return is_string($api) ? $api : '';
    }

    protected function context(): ?string
    {
        $context = $this->config['context'] ?? null;

        return is_scalar($context) && $context !== '' ? (string) $context : null;
    }

    /**
     * Whether the context has its own token for the API, so its space is the one checked.
     */
    protected function usesContext(): bool
    {
        $context = $this->context();

        return $context !== null && ! $this->blank($this->settings->get('contentful.environments.' . $context . '.' . $this->api() . '.token'));
    }
}
