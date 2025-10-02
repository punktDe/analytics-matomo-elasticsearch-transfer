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

class SegmentProcessorCodeGenerator
{
    #[Flow\Inject]
    protected MatomoSegmentRepository $matomoSegmentRespoitory;

    /**
     * @var string[]
     */
    protected array $matomoToAnalyticsFieldMapping = [
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
        'dimension1' => 'action_dimension_1',
        'dimension2' => 'action_dimension_2',
        'dimension3' => 'action_dimension_3',
        'dimension4' => 'action_dimension_4',
        'dimension5' => 'action_dimension_5',
        'actionUrl' => 'action_url',
    ];

    #[Flow\Inject]
    protected Environment $environment;

    #[Flow\InjectConfiguration(path: "customDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customDimensions;

    public function initializeObject(): void
    {
        for ($i = 1; $i <= $this->customDimensions; $i++) {
            $this->matomoToAnalyticsFieldMapping['dimension' . $i] = 'visit_dimension_' . $i;
        }
    }

    /**
     * @return string
     * @throws \Neos\Flow\Utility\Exception
     * @throws \Neos\Utility\Exception\FilesException
     */
    public function compileSegmentProcessorCode(): string
    {
        $segmentConditionCode = '';

        foreach ($this->matomoSegmentRespoitory->findSegmentDefinitions() as $segmentDefinition) {
            $segmentExpression = $this->buildSegmentExpression($segmentDefinition['definition']);

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
    protected function buildSegmentExpression(string $segmentDefinition): string
    {
        $segmentExpression = new SegmentExpression($segmentDefinition, $this->matomoToAnalyticsFieldMapping);
        $segmentExpression->parseSubExpressions();
        $segmentExpression->parseSubExpressionsIntoSqlExpressions();
        return $segmentExpression->getExpression();
    }
}
