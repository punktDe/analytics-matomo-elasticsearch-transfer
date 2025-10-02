<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Transfer;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Elasticsearch\MatomoFormIndex;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Processor\MatomoFormProcessor;
use PunktDe\Analytics\Transfer\AbstractTransferJob;

class MatomoFormTransferJob extends AbstractTransferJob
{
    #[Flow\Inject]
    protected MatomoFormProcessor $processor;

    #[Flow\Inject]
    protected MatomoFormIndex $index;
}
