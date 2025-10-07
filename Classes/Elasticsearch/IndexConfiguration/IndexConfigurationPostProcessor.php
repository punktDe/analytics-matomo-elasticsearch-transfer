<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\IndexConfiguration;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use PunktDe\Analytics\Elasticsearch\IndexConfiguration\IndexConfigurationPostProcessorInterface;

class IndexConfigurationPostProcessor implements IndexConfigurationPostProcessorInterface
{

    #[Flow\InjectConfiguration(path: "customActionDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customActionDimensions;

    #[Flow\InjectConfiguration(path: "customVisitDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customVisitDimensions;


    public static function isSuitableFor(string $indexName): bool
    {
        return str_starts_with($indexName, 'matomo_');
    }

    public function process(array $indexConfiguration): array
    {
        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $indexConfiguration['mappings']['properties']['visit_dimension_' . $i] = [
                'type' => 'keyword',
            ];
        }

        for ($i = 1; $i <= $this->customActionDimensions; $i++) {
            $indexConfiguration['mappings']['properties']['action_dimension_' . $i] = [
                'type' => 'keyword',
            ];
        }

        return $indexConfiguration;
    }
}
