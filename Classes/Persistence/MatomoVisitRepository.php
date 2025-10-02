<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class MatomoVisitRepository extends AbstractMatomoVisitorLogRepository
{

    protected function getDataSourceName(): string
    {
        return 'matomo';
    }

    public function findAll(?\DateTime $startDate, ?array $sites): IterableResult
    {
        $query = '
SELECT
site.name as site_name,
site.idsite as site_id,

lv.`idvisit` as visit_id,
lv.`idvisitor` as visitor_id,
lv.`referer_type` as visit_referer_type,
lv.`referer_url` as visit_referer_url,
lv.`referer_name` as visit_referer_name,
lv.`visitor_returning` as visitor_returning,
lv.`visit_first_action_time` as visit_first_action_time,
lv.`visit_last_action_time` as visit_last_action_time,
lv.`visit_total_actions` as visit_total_actions,
lv.`visit_total_time` as visit_total_time,

lv.`location_latitude` as visit_location_latitude,
lv.`location_longitude` as visit_location_longitude,
lv.`location_city` as visit_location_city,
lv.`location_country` as visit_location_country,

lv.`config_browser_name` as visit_browser_name,
lv.`config_browser_version` as visit_browser_version,
lv.`config_device_brand` as visit_device_brand,
lv.`config_device_model` as visit_device_model,
lv.`config_device_type` as visit_device_type,
lv.`config_os` as visit_os,
lv.`config_os_version` as visit_os_version,
lv.`config_resolution` as visit_resolution,

lv.`campaign_id` as visit_campaign_id,
lv.`campaign_name` as visit_campaign_name,
lv.`campaign_keyword` as visit_campaign_keyword,
lv.`campaign_medium` as visit_campaign_medium,
lv.`campaign_source` as visit_campaign_source,

{customDimensions}

goal.`name` as goal_name,
goal.`description` as goal_description,
goal.`revenue` as goal_revenue

FROM matomo_log_visit lv
INNER JOIN matomo_site site ON lv.`idsite` = site.`idsite`

LEFT OUTER JOIN matomo_log_conversion lc ON lc.`idvisit` = lv.`idvisit`
LEFT OUTER JOIN matomo_goal goal ON goal.`idgoal` = lc.`idgoal` AND goal.`idsite` = lv.`idsite`
';


        if ($startDate instanceof \DateTime) {
            $query .= sprintf('WHERE lv.`visit_last_action_time` > "%s" AND ', $startDate->format('Y-m-d H:i:s'));
        } else {
            $query .= 'WHERE ';
        }

        if (!empty($sites)) {
            $query .= sprintf("site.idsite in ('%s')", implode("','", $sites));
        } else {
            $query .= 'site.transfer_kibana = 1';
        }


        $rsm = new ResultSetMappingBuilder($this->dataSource->getEntityManager());
        $fields = [
            'site_name',
            'site_id',
            'visit_id',
            'visit_referer_type',
            'visit_referer_url',
            'visit_referer_name',
            'visitor_id',
            'visitor_returning',
            'visit_first_action_time',
            'visit_last_action_time',
            'visit_last_action_time',
            'visit_total_time',
            'visit_total_actions',
            'visit_location_latitude',
            'visit_location_longitude',
            'visit_location_city',
            'visit_location_country',
            'visit_browser_name',
            'visit_browser_version',
            'visit_device_model',
            'visit_device_brand',
            'visit_device_type',
            'visit_os',
            'visit_os_version',
            'visit_resolution',
            'visit_campaign_id',
            'visit_campaign_name',
            'visit_campaign_keyword',
            'visit_campaign_medium',
            'visit_campaign_source',
            'visit_entry_url',
            'visit_entry_name',
            'visit_exit_url',

            'goal_name',
            'goal_description',
            'goal_revenue',

            'form_name',
            'form_submissions',
        ];

        $query = $this->addCustomVisitDimensionsToQuery($query);
        $fields = $this->addCustomVisitDimensionsToFields($fields);

        foreach ($fields as $field) {
            $rsm->addScalarResult($field, $field);
        }

        $query = $this->dataSource->getEntityManager()->createNativeQuery($query, $rsm);
        return $query->iterate();
    }
}
