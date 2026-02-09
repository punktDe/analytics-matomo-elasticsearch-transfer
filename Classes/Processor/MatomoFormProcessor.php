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
class MatomoFormProcessor extends AbstractMatomoProcessor
{

    public function convertRecordToDocument(array $record, string $indexPrefix): ?array
    {
        $siteName = $record['site_name'] ?? 'unknown_site';

        $analyzedUrl = $this->parseUrl((string)$record['action_url']);

        $analyzedEntryUrl = $this->parseUrl((string)$record['visit_entry_url']);
        $indexName = sprintf('matomo_form-site_%s-ok', $record['site_id'] ?? 'unknown');

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

            'visitor_returning' => $record['visitor_returning'],

            'visit_id' => $record['visit_id'],
            'visitor_id' => md5($record['visitor_id']),

            'visit_referer_type' => $this->refererType[$record['visit_referer_type']] ?? 'Unknown',
            'visit_referer_url' => $record['visit_referer_url'] ?? '',
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
            'visit_device_model' => $record['visit_device_model'],
            'visit_device_brand' => AbstractDeviceParser::$deviceBrands[$record['visit_device_brand']] ?? $record['visit_device_brand'],
            'visit_device_type' => $this->deviceTypes[$record['visit_device_type']] ?? $record['visit_device_type'],
            'visit_os' => OperatingSystem::getNameFromId($record['visit_os']),
            'visit_os_version' => $record['visit_os_version'],
            'visit_resolution' => $record['visit_resolution'],

            'visit_campaign_id' => $record['visit_campaign_id'] ?? '',
            'visit_campaign_name' => $record['visit_campaign_name'] ?? '',
            'visit_campaign_keyword' => $record['visit_campaign_keyword'] ?? '',
            'visit_campaign_medium' => $record['visit_campaign_medium'] ?? '',
            'visit_campaign_source' => $record['visit_campaign_source'] ?? '',

            'visit_entry_url' => $record['visit_entry_url'] ?? '',
            'visit_entry_name' => $record['visit_entry_name'] ?? '',

            'action_url_keyword' => $record['action_url'],
            'action_url_searchable' => $record['action_url'],
            'action_url_host' => $analyzedUrl['host'] ?? '',
            'action_url_path_segment_1' => $analyzedUrl['pathSegments'][1] ?? '',
            'action_url_path_segment_2' => $analyzedUrl['pathSegments'][2] ?? '',
            'action_url_path_segment_3' => $analyzedUrl['pathSegments'][3] ?? '',
            'action_url_path_segment_4' => $analyzedUrl['pathSegments'][4] ?? '',
            'action_url_path_segment_5' => $analyzedUrl['pathSegments'][5] ?? '',
            'action_url_query_parameter' => is_array($analyzedUrl['urlQueryParameter']) ? $analyzedUrl['urlQueryParameter'] : [],

            'form_name' => $record['form_name'],
            'form_description' => $record['form_description'],
            'form_num_views' => $record['form_num_views'],
            'form_num_starts' => $record['form_num_starts'],
            'form_num_submissions' => $record['form_num_submissions'],
            'form_converted' => $record['form_converted'],
            'form_time_hesitation' => $record['form_time_hesitation'],
            'form_time_spent' => $record['form_time_spent'],
            'form_time_to_first_submission' => $record['form_time_to_first_submission'],

            // This properties cannot (yet) be determined for form records
            'action_name_keyword' => 'undefined',
        ];

        $document['segment'] = \PunktDe\Analytics\MatomoElasticsearchTransfer\Segment\calculateSegment($document);
        $id = $record['log_form_id'];

        for ($i = 1; $i <= $this->customActionDimensions; $i++) {
            $fieldName = 'action_dimension_' . $i;
            $document[$fieldName] = !empty($record[$fieldName]) ? Arrays::trimExplode(',', $record[$fieldName]) : [];
        }

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $fieldName = 'visit_dimension_' . $i;
            $document[$fieldName] = !empty($record[$fieldName]) ? Arrays::trimExplode(',', $record[$fieldName]) : [];
        }

        return [
            'index' => $indexName,
            'id' => $id,
            'body' => $document
        ];
    }
}
