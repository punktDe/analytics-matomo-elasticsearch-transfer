<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Command;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Transfer\JobRunner;

class TransferCommandController extends CommandController
{

    #[Flow\Inject]
    protected JobRunner $jobRunner;

    /**
     * @param string $interval Only transfer data, that is added within the last interval. Interval given as ISO 8601 duration format. https://en.m.wikipedia.org/wiki/ISO_8601
     * @param string $jobs Specify the jobs that should run comma separated. Available: log, form
     * @param string $sites Specify the Site IDs that should be trandsfered, comma separated.
     * @throws \Exception
     */
    public function matomoCommand(string $interval = '', string $jobs = '', string $sites = ''): void
    {
        $this->jobRunner->run($interval, $jobs, $sites);
    }
}
