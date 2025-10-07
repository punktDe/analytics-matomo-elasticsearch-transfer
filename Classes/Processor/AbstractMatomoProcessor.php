<?php
declare(strict_types=1);

namespace PunktDe\Analytics\MatomoElasticsearchTransfer\Processor;

/*
 *  (c) 2025 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Neos\Flow\I18n\Cldr\CldrRepository;
use Neos\Flow\I18n\Service;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Files;
use PunktDe\Analytics\MatomoElasticsearchTransfer\Segment\SegmentProcessorCodeGenerator;
use PunktDe\Analytics\Processor\ElasticsearchProcessorInterface;

abstract class AbstractMatomoProcessor implements ElasticsearchProcessorInterface
{

    #[Flow\InjectConfiguration(path: "customActionDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customActionDimensions;

    #[Flow\InjectConfiguration(path: "customVisitDimensions", package: "PunktDe.Analytics.MatomoElasticsearchTransfer")]
    protected int $customVisitDimensions;

    #[Flow\Inject]
    protected CldrRepository $cldrRepository;

    #[Flow\inject]
    protected Service $i18nService;

    #[Flow\Inject]
    protected PackageManager $packageManager;

    #[Flow\Inject]
    protected SegmentProcessorCodeGenerator $segmentProcessorCodeGenerator;

    protected array $deviceTypes = [
        AbstractDeviceParser::DEVICE_TYPE_DESKTOP => 'Desktop',
        AbstractDeviceParser::DEVICE_TYPE_SMARTPHONE => 'Smartphone',
        AbstractDeviceParser::DEVICE_TYPE_TABLET => 'Tablet',
        AbstractDeviceParser::DEVICE_TYPE_FEATURE_PHONE => 'Feature Phone',
        AbstractDeviceParser::DEVICE_TYPE_CONSOLE => 'Console',
        AbstractDeviceParser::DEVICE_TYPE_TV => 'TV',
        AbstractDeviceParser::DEVICE_TYPE_CAR_BROWSER => 'Car Browser',
        AbstractDeviceParser::DEVICE_TYPE_SMART_DISPLAY => 'Smart Display',
        AbstractDeviceParser::DEVICE_TYPE_CAMERA => 'Camera',
        AbstractDeviceParser::DEVICE_TYPE_PORTABLE_MEDIA_PAYER => 'Portable Media Player',
        AbstractDeviceParser::DEVICE_TYPE_PHABLET => 'Phablet',
        AbstractDeviceParser::DEVICE_TYPE_SMART_SPEAKER => 'Smart Speaker',
        AbstractDeviceParser::DEVICE_TYPE_WEARABLE => 'Wearable',
        AbstractDeviceParser::DEVICE_TYPE_PERIPHERAL => 'Peripheral',
    ];

    protected array $refererType = [
        1 => 'Other',
        2 => 'Search Engine',
        3 => 'Website',
        6 => 'Campaign',
    ];

    protected array $browserNames = [];

    protected array $languageCodeToLanguageName = [];

    protected array $countryCodeToCountryName = [];

    protected array $countryCodeToContinentName = [];

    public function initializeObject(): void
    {
        $this->browserNames = Browser::getAvailableBrowsers();
        $this->loadLanguageCodeMap();
        $this->loadCountryCodeMap();
        $this->loadCountryCodeToContinentMap();

        require_once($this->segmentProcessorCodeGenerator->compileSegmentProcessorCode());
    }

    protected function loadLanguageCodeMap(): void
    {
        $locale = $this->i18nService->getConfiguration()->getCurrentLocale();
        $model = $this->cldrRepository->getModelForLocale($locale);

        $rawLanguages = $model->getRawData('localeDisplayNames/languages');

        foreach ($rawLanguages as $languageKey => $languageName) {
            preg_match('/language\[\@type\=\"(\S*)\"\]/', $languageKey, $matches);
            $this->languageCodeToLanguageName[$matches[1]] = $languageName;
        }
    }

    protected function loadCountryCodeMap(): void
    {
        $locale = $this->i18nService->getConfiguration()->getCurrentLocale();
        $model = $this->cldrRepository->getModelForLocale($locale);

        $rawCountryNames = $model->getRawData('localeDisplayNames/territories');

        foreach ($rawCountryNames as $countryKey => $countryName) {
            preg_match('/territory\[\@type\=\"(\S*)\"\]/', $countryKey, $matches);
            $this->countryCodeToCountryName[$matches[1]] = $countryName;
        }
    }

    protected function loadCountryCodeToContinentMap(): void
    {
        $dataFilePath = Files::concatenatePaths([$this->packageManager->getPackage('PunktDe.Analytics.MatomoElasticsearchTransfer')->getPackagePath(), 'Resources/Private/CountryAndContinentCodes.json']);
        $json = Files::getFileContents($dataFilePath);
        $countries = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        foreach ($countries as $country) {
            $this->countryCodeToContinentName[$country['Two_Letter_Country_Code']] = $country['Continent_Name'];
        }
    }

    protected function parseUrl(string $actionUrl): array
    {
        $actionUrl = str_starts_with($actionUrl, 'http') ? $actionUrl : 'https://' . $actionUrl;
        $analyzedUrl = parse_url($actionUrl);

        if ($analyzedUrl === false) {
            return [
                'host' => 'unknown',
                'pathSegments' => [],
                'urlQueryParameter' => [],
            ];
        }

        $analyzedUrl['pathSegments'] = explode('/', $analyzedUrl['path'] ?? '');
        $analyzedUrl['urlQueryParameter'] = explode('&', $analyzedUrl['query'] ?? '');

        return $analyzedUrl;
    }
}
