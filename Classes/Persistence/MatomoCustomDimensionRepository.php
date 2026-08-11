<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use PunktDe\Analytics\Persistence\AbstractRepository;

class MatomoCustomDimensionRepository extends AbstractRepository
{
    use MatomoTablePrefixTrait;

    protected function getDataSourceName(): string
    {
        return 'matomo';
    }

    public function findCustomDimensionDefinitions(): array
    {
        $queryBuilder = $this->getEntityManager()->getConnection()->createQueryBuilder();

        return $queryBuilder->select('mcd.idcustomdimension', 'mcd.idsite', 'mcd.name', 'mcd.index', 'mcd.scope', 'mcd.active')
            ->from($this->tablePrefix . 'custom_dimensions', 'mcd')
            ->execute()->fetchAllAssociative();
    }
}
