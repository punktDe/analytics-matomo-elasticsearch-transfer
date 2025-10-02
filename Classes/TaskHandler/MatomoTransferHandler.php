<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\TaskHandler;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Flowpack\Task\Domain\Task\WorkloadInterface;
use Neos\Flow\Annotations as Flow;
use Flowpack\Task\TaskHandler\TaskHandlerInterface;
use Neos\Flow\ObjectManagement\ObjectManager;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Transfer\JobRunner;

class MatomoTransferHandler implements TaskHandlerInterface
{
    #[Flow\Inject]
    protected ObjectManager $objectManager;

    public function handle(WorkloadInterface $workload): string
    {
        $jobRunner = $this->objectManager->get(JobRunner::class);

        $data = $workload->getData();
        $jobRunner->run(
            $data['interval'] ?? '',
            $data['jobs'] ?? '',
        );

        return 'success';
    }
}
