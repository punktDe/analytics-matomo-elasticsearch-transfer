<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class MatomoLogRepository extends AbstractMatomoVisitorLogRepository
{

    protected function getDataSourceName(): string
    {
        return 'matomo';
    }

    public function findSiteIds(): array
    {
        $query = $this->replaceTablePrefix('
            SELECT `idsite` FROM {prefix}site WHERE {prefix}site.`transfer_kibana` = 1;
        ');

        $rsm = new ResultSetMappingBuilder($this->dataSource->getEntityManager());
        $rsm->addScalarResult('idsite', 'idsite');

        $query = $this->dataSource->getEntityManager()->createNativeQuery($query, $rsm)->getArrayResult();
        return array_map(fn($el): int => $el['idsite'], $query);
    }

    public function findAll(?\DateTime $startDate, ?array $sites): IterableResult
    {
        $query = '
SELECT
site.name as site_name,
site.idsite as site_id,
llva.idlink_va as action_id,

lv.`idvisit` as visit_id,
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

la_entry_url.`name` as visit_entry_url,
la_entry_name.`name` as visit_entry_name,
la_exit_url.`name` as visit_exit_url,

llva.idvisitor as visitor_id,
llva.`server_time` as action_date,
llva.`pageview_position` as action_pageview_position,
llva.`time_server` as action_time_server,
llva.`time_transfer` as action_time_transfer,
llva.`time_dom_processing` as action_time_dom_processing,
llva.`time_spent` as action_time_spent,

{customDimensions}

la_url.`name` as action_url,
la_url.`type` as action_url_type,
la_name.`name` as action_name,
la_name.`type` as action_name_type,
la_event_action.`name` as action_event_action,
la_event_category.`name` as action_event_category,
la_url_ref.`name` as action_ref_url,

goal.`name` as goal_name,
goal.`description` as goal_description,
goal.`revenue` as goal_revenue

FROM {prefix}log_link_visit_action llva
INNER JOIN {prefix}log_visit lv ON llva.`idvisit` = lv.`idvisit`
INNER JOIN {prefix}site site ON llva.`idsite` = site.`idsite`
LEFT OUTER JOIN {prefix}log_action la_url ON llva.`idaction_url` = la_url.`idaction`
LEFT OUTER JOIN {prefix}log_action la_url_ref ON llva.`idaction_url_ref` = la_url_ref.`idaction`
LEFT OUTER JOIN {prefix}log_action la_name ON llva.`idaction_name` = la_name.`idaction`

LEFT OUTER JOIN {prefix}log_action la_event_action ON llva.`idaction_event_action` = la_event_action.`idaction`
LEFT OUTER JOIN {prefix}log_action la_event_category ON llva.`idaction_event_category` = la_event_category.`idaction`

LEFT OUTER JOIN {prefix}log_action la_entry_url ON lv.`visit_entry_idaction_url` = la_entry_url.`idaction`
LEFT OUTER JOIN {prefix}log_action la_entry_name ON lv.`visit_entry_idaction_name` = la_entry_name.`idaction`
LEFT OUTER JOIN {prefix}log_action la_exit_url ON lv.`visit_exit_idaction_url` = la_exit_url.`idaction`

LEFT OUTER JOIN {prefix}log_conversion lc ON lc.`idvisit` = llva.`idvisit`
LEFT OUTER JOIN {prefix}goal goal ON goal.`idgoal` = lc.`idgoal` AND goal.`idsite` = llva.`idsite`
';


        if ($startDate instanceof \DateTime) {
            $query .= sprintf('WHERE llva.`server_time` > "%s" AND ', $startDate->format('Y-m-d H:i:s'));
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

            'action_id',
            'action_date',
            'action_pageview_position',
            'action_time_server',
            'action_time_transfer',
            'action_time_dom_processing',
            'action_time_spent',
            'action_url',
            'action_url_type',
            'action_name',
            'action_name_type',
            'action_event_action',
            'action_event_category',
            'action_ref_url',

            'goal_name',
            'goal_description',
            'goal_revenue',

            'form_name',
            'form_submissions',
        ];

        $query = $this->addCustomDimensionsToQuery($query);
        $query = $this->replaceTablePrefix($query);
        $fields = $this->addCustomDimensionsToFields($fields);

        foreach ($fields as $field) {
            $rsm->addScalarResult($field, $field);
        }

        $query = $this->dataSource->getEntityManager()->createNativeQuery($query, $rsm);
        return $query->iterate();
    }
}
