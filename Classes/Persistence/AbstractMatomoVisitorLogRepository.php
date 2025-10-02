<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use PunktDe\Analytics\Persistence\AbstractRepository;

abstract class AbstractMatomoVisitorLogRepository extends AbstractRepository
{
    #[Flow\InjectConfiguration(path: "customActionDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customActionDimensions;

    #[Flow\InjectConfiguration(path: "customVisitDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customVisitDimensions;

    protected function addCustomDimensionsToQuery(string $query): string
    {
        $customDimensionQueryPart = '';

        for ($i = 1; $i <= $this->customActionDimensions; $i++) {
            $customDimensionQueryPart .= sprintf('llva.`custom_dimension_%s` as action_dimension_%s,', $i, $i) . PHP_EOL;
        }

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $customDimensionQueryPart .= sprintf('lv.`custom_dimension_%s` as visit_dimension_%s,', $i, $i) . PHP_EOL;
        }

        return str_replace('{customDimensions}', $customDimensionQueryPart, $query);
    }

    protected function addCustomVisitDimensionsToQuery(string $query): string
    {
        $customDimensionQueryPart = '';

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $customDimensionQueryPart .= sprintf('lv.`custom_dimension_%s` as visit_dimension_%s,', $i, $i) . PHP_EOL;
        }

        return str_replace('{customDimensions}', $customDimensionQueryPart, $query);
    }

    protected function addCustomDimensionsToFields(array $fields): array
    {
        for ($i = 1; $i <= $this->customActionDimensions; $i++) {
            $fields[] = 'action_dimension_' . $i;
        }

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $fields[] = 'visit_dimension_' . $i;
        }

        return $fields;
    }

    protected function addCustomVisitDimensionsToFields(array $fields): array
    {
        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $fields[] = 'visit_dimension_' . $i;
        }

        return $fields;
    }
}
