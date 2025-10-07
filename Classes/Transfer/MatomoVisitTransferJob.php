<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Transfer;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\MatomoVisitIndex;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Processor\MatomoVisitProcessor;
use PunktDe\Analytics\Transfer\AbstractTransferJob;

class MatomoVisitTransferJob extends AbstractTransferJob
{
    /**
     * @var MatomoVisitProcessor
     */
    #[Flow\Inject]
    protected $processor;

    /**
     * @var MatomoVisitIndex
     */
    #[Flow\Inject]
    protected $index;
}
