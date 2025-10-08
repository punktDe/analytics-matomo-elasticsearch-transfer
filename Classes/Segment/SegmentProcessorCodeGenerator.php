<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Segment;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence\MatomoSegmentRepository;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Persistence\MatomoCustomDimensionRepository;

class SegmentProcessorCodeGenerator
{
    #[Flow\Inject]
    protected MatomoSegmentRepository $matomoSegmentRespoitory;

    #[Flow\Inject]
    protected MatomoCustomDimensionRepository $matomoCustomDimensionRepository;

    #[Flow\Inject]
    protected Environment $environment;

    /**
     * @var string[]
     */
    protected array $matomoToAnalyticsFieldMappingDefaults = [
        'entryPageUrl' => 'visit_entry_url',
        'entryPageTitle' => 'visit_entry_name',
        'visitorType' => 'visitor_returning',
        'campaignName' => 'visit_campaign_name',
        'campaignMedium' => 'visit_campaign_medium',
        'campaignSource' => 'visit_campaign_source',
        'campaignId' => 'visit_campaign_id',
        'deviceType' => 'visit_device_type',
        'deviceModel' => 'visit_device_model',
        'referrerType' => 'visit_referer_type',
        'referrerName' => 'visit_referer_name',
        'pageTitle' => 'action_name',
        'pageUrl' => 'action_url',
        'operatingSystemName' => 'visit_os',
        'city' => 'visit_location_city',
        'actionUrl' => 'action_url',
    ];

    /**
     * @return string
     * @throws \Neos\Flow\Utility\Exception
     * @throws \Neos\Utility\Exception\FilesException
     */
    public function compileSegmentProcessorCode(): string
    {
        $segmentConditionCode = '';

        $customDimensionDefinitions = $this->matomoCustomDimensionRepository->findCustomDimensionDefinitions();
        $matomoToAnalyticsFieldMappingForSites = [];

        foreach($customDimensionDefinitions as $customDimensionDefinition) {
            if (!array_key_exists((string)$customDimensionDefinition['idsite'], $matomoToAnalyticsFieldMappingForSites)) {
                $matomoToAnalyticsFieldMappingForSites[(string)$customDimensionDefinition['idsite']] = $this->matomoToAnalyticsFieldMappingDefaults;
            }
            $matomoToAnalyticsFieldMappingForSites[(string)$customDimensionDefinition['idsite']]['dimension' . (string)$customDimensionDefinition['idcustomdimension']] = $customDimensionDefinition['scope'] . '_dimension_' . $customDimensionDefinition['index'];
        }

        foreach ($this->matomoSegmentRespoitory->findSegmentDefinitions() as $segmentDefinition) {
            if (array_key_exists($segmentDefinition['enable_only_idsite'], $matomoToAnalyticsFieldMappingForSites)) {
                $segmentExpression = $this->buildSegmentExpression($segmentDefinition['definition'], $matomoToAnalyticsFieldMappingForSites[$segmentDefinition['enable_only_idsite']]);
            } else {
                $segmentExpression = $this->buildSegmentExpression($segmentDefinition['definition'], $this->matomoToAnalyticsFieldMappingDefaults);
            }

            if ($segmentExpression === '') {
                continue;
            }

            $segmentConditionCode .= sprintf('if($data[\'site_id\'] === \'%s\' && %s) $segments[] = \'%s\';', $segmentDefinition['enable_only_idsite'], $segmentExpression, $segmentDefinition['name']) . PHP_EOL;
        }

        $segmentProcessorTemplate = '<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Segment;

/*
 *  (c) 2021 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

function calculateSegment(array $data): array
{
    $segments = [];

    {{segmentConditionCode}}

    return $segments;
}';


        $segmentProcessorCode = str_replace('{{segmentConditionCode}}', $segmentConditionCode, $segmentProcessorTemplate);

        $temporarySegmentProcessorCodeFile = Files::concatenatePaths([$this->environment->getPathToTemporaryDirectory(), 'SegmentProcessor.php']);
        if (file_exists($temporarySegmentProcessorCodeFile)) {
            unlink($temporarySegmentProcessorCodeFile);
        }

        file_put_contents($temporarySegmentProcessorCodeFile, $segmentProcessorCode);
        return $temporarySegmentProcessorCodeFile;
    }

    /**
     * @param string $segmentDefinition
     * @return string
     * @throws \Exception
     */
    protected function buildSegmentExpression(string $segmentDefinition, array $matomoToAnalyticsFieldMapping): string
    {
        $segmentExpression = new SegmentExpression($segmentDefinition, $matomoToAnalyticsFieldMapping);
        $segmentExpression->parseSubExpressions();
        $segmentExpression->parseSubExpressionsIntoSqlExpressions();
        return $segmentExpression->getExpression();
    }
}
