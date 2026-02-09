<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Processor;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use DeviceDetector\Parser\Device\AbstractDeviceParser;
use DeviceDetector\Parser\OperatingSystem;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

#[Flow\Scope(value: "singleton")]
class MatomoLogProcessor extends AbstractMatomoProcessor implements MatomoLogProcessorInterface
{
    public function convertRecordToDocument(array $record, string $indexPrefix): ?array
    {
        $siteName = $record['site_name'] ?? 'unknown_site';
        $analyzedUrl = $this->parseUrl((string)$record['action_url']);

        $analyzedEntryUrl = $this->parseUrl((string)$record['visit_entry_url']);
        $indexName = sprintf('matomo_log-site_%s-ok', $record['site_id'] ?? 'unknown');
        # punkt.de convention, not matomo default

        if (substr((string)$record['action_name'], 2, 2) === ' -') {
            $languageCode = substr((string)$record['action_name'], 0, 2);
            $language = $this->languageCodeToLanguageName[$languageCode] ?? 'Unknown Language';
        } else {
            $language = 'Unknown Language';
        }

        $actionDate = strtotime($record['action_date']);

        $document = [
            '@timestamp' => date('c', $actionDate),
            'date' => date('c', $actionDate),
            'action_hour_of_day' => date('H', $actionDate),
            'action_day_of_week_name' => date('D', $actionDate),
            'action_day_of_week_position' => (int)date('w', $actionDate) === 0 ? 7 : date('w', $actionDate),

            'site_id' => (int)$record['site_id'],
            'site_name' => $siteName,
            'site_domain' => $analyzedEntryUrl['host'],

            'visit_id' => $record['visit_id'],
            'visitor_id' => md5($record['visitor_id']),

            'visit_referer_type' => $this->refererType[$record['visit_referer_type']] ?? 'Unknown',
            'visitor_returning' => (int)$record['visitor_returning'] === 1 ? 'returning' : 'new',
            'visit_referer_url' => $record['visit_referer_url'],
            'visit_referer_name' => $record['visit_referer_name'] ?? '',

            'visit_total_actions' => $record['visit_total_actions'],
            'visit_total_time' => $record['visit_total_time'],
            'visit_first_action_time' => date('c', strtotime($record['visit_first_action_time'])),
            'visit_last_action_time' => date('c', strtotime($record['visit_last_action_time'])),
            'visit_location_geopoint' => ((string)$record['visit_location_latitude'] === '' ? '0.0' : $record['visit_location_latitude']) . ',' . ((string)$record['visit_location_longitude'] === '' ? '0.0' : $record['visit_location_longitude']),
            'visit_location_city' => $record['visit_location_city'],
            'visit_location_continent' => $this->countryCodeToContinentName[strtoupper($record['visit_location_country'])] ?? 'Unknown',
            'visit_location_country' => $this->countryCodeToCountryName[strtoupper($record['visit_location_country'])] ?? strtoupper($record['visit_location_country']),
            'visit_location_country_iso3166' => strtoupper($record['visit_location_country']),

            'visit_browser_name' => $this->browserNames[$record['visit_browser_name']] ?? $record['visit_browser_name'],
            'visit_browser_version' => $record['visit_browser_version'],
            'visit_device_model' => $record['visit_device_model'] ?? '',
            'visit_device_brand' => AbstractDeviceParser::$deviceBrands[$record['visit_device_brand']] ?? $record['visit_device_brand'] ?? '',
            'visit_device_type' => $this->deviceTypes[$record['visit_device_type']] ?? $record['visit_device_type'] ?? '',
            'visit_os' => OperatingSystem::getNameFromId($record['visit_os']) ?? '',
            'visit_os_version' => $record['visit_os_version'] ?? '',
            'visit_resolution' => $record['visit_resolution'],

            'visit_campaign_id' => $record['visit_campaign_id'],
            'visit_campaign_name' => $record['visit_campaign_name'] ?? '',
            'visit_campaign_keyword' => $record['visit_campaign_keyword'] ?? '',
            'visit_campaign_medium' => $record['visit_campaign_medium'] ?? '',
            'visit_campaign_source' => $record['visit_campaign_source'] ?? '',
            'visit_entry_name' => $record['visit_entry_name'],
            'visit_entry_url' => $record['visit_entry_url'] ?? '',
            'visit_exit_url' => $record['visit_exit_url'],

            'action_pageview_position' => $record['action_pageview_position'],
            'action_time_server' => $record['action_time_server'],
            'action_time_transfer' => $record['action_time_transfer'],
            'action_time_dom_processing' => $record['action_time_dom_processing'],
            'action_time_spent' => $record['action_time_spent'],

            'action_name_type' => $record['action_name_type'],
            'action_url_type' => $record['action_url_type'],
            'action_url_keyword' => $record['action_url'] ?? '',
            'action_url_searchable' => $record['action_url'],
            'action_url_host' => $analyzedUrl['host'] ?? '',
            'action_url_path_segment_1' => $analyzedUrl['pathSegments'][1] ?? '',
            'action_url_path_segment_2' => $analyzedUrl['pathSegments'][2] ?? '',
            'action_url_path_segment_3' => $analyzedUrl['pathSegments'][3] ?? '',
            'action_url_path_segment_4' => $analyzedUrl['pathSegments'][4] ?? '',
            'action_url_path_segment_5' => $analyzedUrl['pathSegments'][5] ?? '',
            'action_url_query_parameter' => is_array($analyzedUrl['urlQueryParameter']) ? $analyzedUrl['urlQueryParameter'] : [],


            'action_name_keyword' => $record['action_name'] ?? '',
            'action_name_searchable' => $record['action_name'],
            'action_event_action_keyword' => $record['action_event_action'] ?? '',
            'action_event_action_searchable' => $record['action_event_action'],
            'action_event_category_keyword' => $record['action_event_category'] ?? '',
            'action_event_category_searchable' => $record['action_event_category'],
            'action_ref_url_keyword' => $record['action_ref_url'],
            'action_ref_url_searchable' => $record['action_ref_url'],

            'action_language' => $language,

            'goal_name' => $record['goal_name'],
            'goal_description' => $record['goal_description'],
            'goal_revenue' => $record['goal_revenue'],

            'flow_download' => (int)$record['action_url_type'] === 3 ? array_pop($analyzedUrl['pathSegments']) : null,
        ];

        $document['segment'] = \PunktDe\Analytics\MatomoElasticsearchTransfer\Segment\calculateSegment($record);

        for ($i = 1; $i <= $this->customActionDimensions; $i++) {
            $fieldName = 'action_dimension_' . $i;
            $document[$fieldName] = !empty($record[$fieldName]) ? Arrays::trimExplode(',', $record[$fieldName]) : [''];
        }

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $fieldName = 'visit_dimension_' . $i;
            $document[$fieldName] = !empty($record[$fieldName]) ? Arrays::trimExplode(',', $record[$fieldName]) : [''];
        }

        return [
            'index' => $indexName,
            'id' => $record['action_id'],
            'body' => $document
        ];
    }
}
