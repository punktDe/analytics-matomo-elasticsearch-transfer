<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Transfer;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use PunktDe\Analytics\Elasticsearch\ElasticsearchService;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\MatomoFormIndex;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\MatomoLogIndex;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\MatomoVisitIndex;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence\MatomoFormRepository;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence\MatomoLogRepository;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence\MatomoVisitRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

class JobRunner
{
    #[Flow\Inject]
    protected ElasticsearchService $elasticSearchService;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\Inject]
    protected MatomoLogIndex $matomoLogIndex;

    #[Flow\Inject]
    protected MatomoFormIndex $matomoFormIndex;

    #[Flow\Inject]
    protected MatomoVisitIndex $matomoVisitIndex;

    #[Flow\Inject]
    protected MatomoLogRepository $matomoLogRepository;

    #[Flow\Inject]
    protected MatomoFormRepository $matomoFormRepository;

    #[Flow\Inject]
    protected MatomoVisitRepository $matomoVisitRepository;

    /**
     * @var string[]
     */
    #[Flow\InjectConfiguration(path: "elasticsearch.server", package: "PunktDe.Analytics")]
    protected array $clientConfiguration;

    protected ?Client $guzzleClient = null;

    public function run(string $interval = '', string $jobs = '', string $sites = ''): void
    {
        if ($this->guzzleClient === null) {
            $this->guzzleClient = new Client([
                'base_uri' => sprintf('%s://%s:%s', $this->clientConfiguration['scheme'], $this->clientConfiguration['host'], $this->clientConfiguration['port']),
                'auth' => [
                    $this->clientConfiguration['user'],
                    $this->clientConfiguration['pass'],
                ],
            ]);
        }

        $startDate = null;

        if ($jobs === '') {
            $runLogJob = true;
            $runFormJob = true;
            $runVisitJob = true;
        } else {
            $jobNames = explode(',', $jobs);
            $runLogJob = in_array('log', $jobNames, true);
            $runFormJob = in_array('form', $jobNames, true);
            $runVisitJob = in_array('visit', $jobNames, true);
        }

        $siteIds = [];
        if ($sites !== '') {
            $siteIds = explode(',', $sites);
        } else {
            $siteIds = $this->matomoLogRepository->findSiteIds();
        }

        if ($interval !== '') {
            $dateTimeInterval = new \DateInterval($interval);
            $startDate = (new \DateTime('now'))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->sub($dateTimeInterval)
                ->sub(new \DateInterval('PT1H'));
            $this->logger->info(sprintf('Transferring data from %s until now', $startDate->format(\DateTime::ATOM)), LogEnvironment::fromMethodName(__METHOD__));
        }

        $runLogJob && $this->setupGeneralIndexTemplate($this->matomoLogIndex->getName());
        $runFormJob && $this->setupGeneralIndexTemplate($this->matomoFormIndex->getName());
        $runVisitJob && $this->setupGeneralIndexTemplate($this->matomoVisitIndex->getName());

        foreach ($siteIds as $siteId) {
            if ($runLogJob) {
                try {
                    if (!$this->aliasExists(sprintf("matomo_log-site_%s-ok", $siteId))) {
                        $this->logger->info(sprintf('Task deleting all indices for site %s and updating template', $siteId), LogEnvironment::fromMethodName(__METHOD__));
                        $this->deleteIndizes(sprintf("matomo_log-site_%s-", $siteId));
                        $this->setupSiteIndexTemplate($this->matomoLogIndex->getName(), (string)$siteId);
                        $this->createIndexWithAlias(sprintf("matomo_log-site_%s-ok", $siteId));
                    } else {
                        $this->logger->debug(sprintf('Alias for site id %s exists, skipping template update', $siteId), LogEnvironment::fromMethodName(__METHOD__));
                    }
                } catch (\Exception $exception) {
                    $this->logger->warning(sprintf('Could not recreate matomo_log indizes for site ID %s , error message: %s', $siteId, $exception->getMessage()));
                }

            }
            if ($runFormJob) {
                try {
                    if (!$this->aliasExists(sprintf("matomo_form-site_%s-ok", $siteId))) {
                        $this->deleteIndizes(sprintf("matomo_form-site_%s-", $siteId));
                        $this->setupSiteIndexTemplate($this->matomoFormIndex->getName(), (string)$siteId);
                        $this->createIndexWithAlias(sprintf("matomo_form-site_%s-ok", $siteId));
                    }
                } catch (\Exception $exception) {
                    $this->logger->warning(sprintf('Could not recreate matomo_form indizes for site ID %s , error message: %s', $siteId, $exception->getMessage()));
                }
            }

            if ($runVisitJob) {
                try {
                    if (!$this->aliasExists(sprintf("matomo_visit-site_%s-ok", $siteId))) {
                        $this->deleteIndizes(sprintf("matomo_visit-site_%s-", $siteId));
                        $this->setupSiteIndexTemplate($this->matomoVisitIndex->getName(), (string)$siteId);
                        $this->createIndexWithAlias(sprintf("matomo_visit-site_%s-ok", $siteId));
                    }
                } catch (\Exception $exception) {
                    $this->logger->warning(sprintf('Could not recreate matomo_visit indizes for site ID %s , error message: %s', $siteId, $exception->getMessage()));
                }
            }


            $runLogJob && (new MatomoLogTransferJob('matomo_log'))->transferGeneric($this->matomoLogRepository->findAll($startDate, [$siteId]), true);
            $runFormJob && (new MatomoFormTransferJob('matomo_form'))->transferGeneric($this->matomoFormRepository->findAll($startDate, [$siteId]), true);
            $runVisitJob && (new MatomoVisitTransferJob('matomo_visit'))->transferGeneric($this->matomoVisitRepository->findAll($startDate, [$siteId]), true);
        }

    }

    /**
     * @param string $indexName
     * @return void
     * @throws GuzzleException
     * @throws \Exception
     */
    private function setupGeneralIndexTemplate(string $indexName): void
    {
        $template = [
            "template" => [
                "mappings" => $this->elasticSearchService->getIndexConfiguration($indexName)['mappings']
            ]
        ];

        $templateName =  sprintf('/_component_template/%s_properties', $indexName);

        $request = new Request('PUT', $templateName, ['Content-Type' => 'application/json'], json_encode($template));
        $response = $this->guzzleClient->send($request);

        if ($response->getStatusCode() > 300) {
            throw new \Exception($response->getReasonPhrase(), $response->getStatusCode());
        }

        $this->logger->info(sprintf('Updated component template %s', $templateName), LogEnvironment::fromMethodName(__METHOD__));
    }

    /**
     * @param string $indexName
     * @param string $siteId
     * @return void
     * @throws GuzzleException
     * @throws \Exception
     */
    private function setupSiteIndexTemplate(string $indexName, string $siteId): void
    {
        $template = '
            {
                "template": {
                    "settings": {
                        "index": {
                            "lifecycle": {
                                "name": "size-10g_age-4w",
                                "rollover_alias": "%s-site_%s-ok"
                            },
                            "mode": "standard"
                        }
                    },
                    "lifecycle": {
                        "enabled": true,
                        "data_retention": "365d"
                    }
                },
                "index_patterns": ["%s-site_%s-*"],
                "data_stream": {},
                "composed_of": ["%s_properties"],
                "ignore_missing_component_templates": []
            }
        ';

        $request = new Request('PUT', sprintf('/_index_template/%s-site_%s-ok', $indexName, $siteId), ['Content-Type' => 'application/json'], sprintf($template, $indexName, $siteId, $indexName, $siteId, $indexName));
        $response = $this->guzzleClient->send($request);
        if ($response->getStatusCode() > 300) {
            throw new \Exception($response->getReasonPhrase(), $response->getStatusCode());
        }
    }

    private function aliasExists(string $indexPattern): bool
    {
        return $this->elasticSearchService->getClient()->indices()->existsAlias(['name' => $indexPattern]);
    }

    private function createIndexWithAlias(string $indexPattern): void
    {
        $this->elasticSearchService->getClient()->indices()->create(['index' => sprintf("%s-000001", $indexPattern)]);
        $this->elasticSearchService->getClient()->indices()->delete(['index' => $indexPattern, 'ignore_unavailable' => true]);
        $this->elasticSearchService->getClient()->indices()->putAlias(
            [
                'index' => sprintf("%s-000001", $indexPattern),
                'name' => $indexPattern,
                'body' => ['is_write_index' => true]
            ]
        );
    }

    /**
     * @param string $indexName
     * @return void
     */
    private function deleteIndizes(string $indexName): void
    {
        $indexPattern = $indexName . '*';

        try {
            $this->elasticSearchService->getClient()->indices()->delete(['index' => $indexPattern]);
            $this->logger->info(sprintf('Successfully removed indices with pattern %s', $indexPattern), LogEnvironment::fromMethodName(__METHOD__));
        } catch (Missing404Exception $exception) {
            $this->logger->info(sprintf('Index with pattern %s could not be removed as it is not found', $indexPattern), LogEnvironment::fromMethodName(__METHOD__));
        }
    }
}
