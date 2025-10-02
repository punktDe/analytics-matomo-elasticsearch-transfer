<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

class MatomoFormRepository extends AbstractMatomoVisitorLogRepository
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
site_form.`name` as form_name,
site_form.`description` as form_description,

log_form.`form_last_action_time` as action_date,
log_form.`num_views` as form_num_views,
log_form.`num_starts` as form_num_starts,
log_form.`num_submissions` as form_num_submissions,
log_form.`converted` as form_converted,
log_form.`time_hesitation` as form_time_hesitation,
log_form.`time_spent` as form_time_spent,
log_form.`time_to_first_submission` as form_time_to_first_submission,
log_form.idvisitor as visitor_id,
log_form.idlogform as log_form_id,

lv.`referer_type` as visit_referer_type,
lv.`referer_url` as visit_referer_url,
lv.`referer_name` as visit_referer_name,
lv.`visitor_returning` as visitor_returning,
lv.`visit_first_action_time` as visit_first_action_time,
lv.`visit_last_action_time` as visit_last_action_time,
lv.`visit_total_actions` as visit_total_actions,
lv.`visit_total_time` as visit_total_time,

lv.`idvisit` as visit_id,
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

la_entry_url.`name` as visit_entry_url,
la_entry_name.`name` as visit_entry_name,

la_url.`name` as action_url

FROM matomo_log_form log_form
INNER JOIN matomo_site_form site_form ON log_form.`idsiteform` = site_form.`idsiteform`
INNER JOIN matomo_site site ON log_form.`idsite` = site.`idsite`
INNER JOIN matomo_log_visit lv ON log_form.`idvisit` = lv.`idvisit`
INNER JOIN matomo_log_form_page form_page ON form_page.idlogform = log_form.`idlogform`

INNER JOIN matomo_log_action la_url ON form_page.`idaction_url` = la_url.`idaction`

LEFT OUTER JOIN matomo_log_action la_entry_url ON lv.`visit_entry_idaction_url` = la_entry_url.`idaction`
LEFT OUTER JOIN matomo_log_action la_entry_name ON lv.`visit_entry_idaction_name` = la_entry_name.`idaction`
';

        if ($startDate instanceof \DateTime) {
            $query .= sprintf('WHERE log_form.`form_last_action_time` > "%s" AND ', $startDate->format('Y-m-d H:i:s'));
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
            'visitor_returning',
            'visit_entry_url',

            'action_date',
            'action_url',

            'form_name',
            'form_description',
            'log_form_id',
            'form_num_views',
            'form_num_starts',
            'form_num_submissions',
            'form_converted',
            'form_time_hesitation',
            'form_time_spent',
            'form_time_to_first_submission',
        ];

        $query = $this->addCustomDimensionsToQuery($query);
        $fields = $this->addCustomDimensionsToFields($fields);

        foreach ($fields as $field) {
            $rsm->addScalarResult($field, $field);
        }

        $query = $this->dataSource->getEntityManager()->createNativeQuery($query, $rsm);
        return $query->iterate();
    }

    protected function addCustomDimensionsToQuery(string $query): string
    {
        $customDimensionQueryPart = '';

        for ($i = 1; $i <= $this->customActionDimensions; $i++) {
            $customDimensionQueryPart .= sprintf('(SELECT llva.`custom_dimension_%s` FROM matomo_log_link_visit_action llva WHERE llva.`idvisit` = lv.`idvisit` AND llva.`idaction_url` = form_page.`idaction_url` LIMIT 1) AS action_dimension_%s,', $i, $i) . PHP_EOL;
        }

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $customDimensionQueryPart .= sprintf('lv.`custom_dimension_%s` as visit_dimension_%s,', $i, $i) . PHP_EOL;
        }

        return str_replace('{customDimensions}', $customDimensionQueryPart, $query);
    }
}
