<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Transfer;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\MatomoLogIndex;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Processor\MatomoLogProcessorInterface;
use PunktDe\Analytics\Transfer\AbstractTransferJob;

class MatomoLogTransferJob extends AbstractTransferJob
{
    #[Flow\Inject]
    protected MatomoLogProcessorInterface $processor;

    #[Flow\Inject]
    protected MatomoLogIndex $index;
}
