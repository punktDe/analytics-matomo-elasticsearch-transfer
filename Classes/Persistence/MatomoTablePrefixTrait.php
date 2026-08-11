<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;

trait MatomoTablePrefixTrait
{
    #[Flow\InjectConfiguration(path: "tablePrefix", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected string $tablePrefix;

    protected function replaceTablePrefix(string $query): string
    {
        return str_replace('{prefix}', $this->tablePrefix, $query);
    }
}
