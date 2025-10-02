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
class MatomoVisitProcessor extends AbstractMatomoProcessor
{
    public function convertRecordToDocument(array $record, string $indexPrefix): ?array
    {
        $siteName = $record['site_name'] ?? 'unknown_site';

        $indexName = sprintf('matomo_visit-site_%s-ok', $record['site_id'] ?? 'unknown');

        $actionDate = strtotime($record['visit_last_action_time']);

        $document = [
            'date' => date('c', $actionDate),

            'site_id' => (int)$record['site_id'],
            'site_name' => $siteName,

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

            'goal_name' => $record['goal_name'],
            'goal_description' => $record['goal_description'],
            'goal_revenue' => $record['goal_revenue'],
        ];

        $document['segment'] = \PunktDe\Analytics\MatomoElasticsearchTransfer\Segment\calculateSegment($record);

        for ($i = 1; $i <= $this->customVisitDimensions; $i++) {
            $fieldName = 'visit_dimension_' . $i;
            $document[$fieldName] = !empty($record[$fieldName]) ? Arrays::trimExplode(',', $record[$fieldName]) : [];
        }

        return [
            'index' => $indexName,
            'id' => $record['visit_id'],
            'body' => $document
        ];
    }
}
