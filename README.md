# PunktDe.Analytics.MatomoElasticsearchTransfer

## Installation

    composer require punktde/analytics-matomo-elasticsearch-transfer

## Configuration

Add the needed information via environment varibales or adjust the settings via yaml. See the `Configuration/Settings.yaml`

    ELASTICSEARCH_HOST=
    ELASTICSEARCH_PORT=
    ELASTICSEARCH_SCHEME=
    ELASTICSEARCH_USERNAME=
    ELASTICSEARCH_PASSWORD=
    MATOMO_MYSQL_DATABASE=
    MATOMO_MYSQL_USERNAME=
    MATOMO_MYSQL_PASSWORD=
    MATOMO_MYSQL_HOST=

### Table prefix

By default the Matomo tables are expected to be prefixed with `matomo_` (e.g. `matomo_log_visit`).
If your Matomo database uses unprefixed tables (or a custom prefix), adjust the `tablePrefix` setting:

```yaml
PunktDe:
  Analytics:
    MatomoElasticsearchTransfer:
      tablePrefix: ''
```
