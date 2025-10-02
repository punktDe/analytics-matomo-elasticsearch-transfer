<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use PunktDe\Analytics\Elasticsearch\AbstractIndex;

/**
 * @method  delete(array $array)
 * @method  index(array $params)
 * @method  search(array $params)
 * @method  get(array $params)
 * @method  reindex(array $params)
 * @method  bulk(array $params)
 */
class MatomoLogIndex extends AbstractIndex
{
    protected $indexName = 'matomo_log';
}
