<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use PunktDe\Analytics\Persistence\AbstractRepository;

class MatomoSegmentRepository extends AbstractRepository
{
    use MatomoTablePrefixTrait;

    protected function getDataSourceName(): string
    {
        return 'matomo';
    }

    public function findSegmentDefinitions(): array
    {
        $queryBuilder = $this->getEntityManager()->getConnection()->createQueryBuilder();

        return $queryBuilder->select('name', 'definition', 'enable_only_idsite')
            ->from($this->tablePrefix . 'segment')
            ->where('deleted = 0')
            ->execute()->fetchAllAssociative();
    }
}
