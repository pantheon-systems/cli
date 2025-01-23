<?php

namespace Pantheon\Terminus\Commands\Env;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\NumericCellRenderer;
use Consolidation\OutputFormatters\StructuredData\RowsOfFieldsWithMetadata;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class MetricsCommand
 *
 * @package Pantheon\Terminus\Commands\Env
 */
class MetricsCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    public const DAILY_PERIOD = 'day';

    public const WEEKLY_PERIOD = 'week';

    public const MONTHLY_PERIOD = 'month';

    public const DAILY_PERIOD_SHORT = 'd';

    public const WEEKLY_PERIOD_SHORT = 'w';

    public const MONTHLY_PERIOD_SHORT = 'm';

    public const DEFAULT_MONTHLY_DATAPOINTS = 12;

    public const DEFAULT_WEEKLY_DATAPOINTS = 12;

    public const DEFAULT_DAILY_DATAPOINTS = 28;

    /**
     * Displays the pages served and unique visit metrics for the specified
     * site environment. The most recent data up to the current day is
     * returned.
     *
     * @authorize
     *
     * @command env:metrics
     * @aliases metrics
     *
     * @field-labels
     *     datetime: Period
     *     visits: Visits
     *     pages_served: Pages Served
     *     cache_hits: Cache Hits
     *     cache_misses: Cache Misses
     *     cache_hit_ratio: Cache Hit Ratio
     *
     * @param string $site_env Site & environment in the format
     *     `site-name.env`.
     *   Defaults to the live environment if `.env` is not specified.
     * @param string[] $options
     *
     * @usage <site>.<env> Displays metrics for <site>'s <env> environment.
     * @usage <site> Displays the combined metrics for all of <site>'s
     *     environments.
     * @usage <site> --fields=datetime,pages_served Displays only the pages
     *     served for each date period.
     *
     * @option period The time period for each data point (month|week|day)
     * @option datapoints How much data to return in total, or 'auto' to select
     *   a resonable default based on the selected period.
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFieldsWithMetadata
     *
     * @throws \Pantheon\Terminus\Exceptions\TerminusException
     */
    public function metrics(
        $site_env,
        $options = [
            'period' => self::DAILY_PERIOD,
            'datapoints' => 'auto',
        ]
    ) {
        $env = $this->getOptionalEnv($site_env);
        if (null !== $env) {
            $metrics = $env->getEnvironmentMetrics()
                ->setDuration(
                    $this->selectDatapoints(
                        $options['datapoints'],
                        $options['period']
                    )
                )
                ->serialize();
        } else {
            $metrics = $this->getSiteById($site_env)
                ->getSiteMetrics()
                ->setDuration(
                    $this->selectDatapoints(
                        $options['datapoints'],
                        $options['period']
                    )
                )
                ->serialize();
        }

        return (new RowsOfFieldsWithMetadata($metrics))
            ->setDataKey('timeseries')
            ->addRenderer(
                new NumericCellRenderer($metrics['timeseries'], [
                    'visits' => 6,
                    'pages_served' => 12,
                    'cache_hits' => 12,
                    'cache_misses' => 12,
                ])
            )
            ->addRendererFunction(
                function ($key, $cellData) {
                    if ($key == 'datetime') {
                        $cellData = str_replace(
                            'T00:00:00',
                            '',
                            $cellData ?? ''
                        );
                    }
                    return $cellData;
                }
            );
    }

    /**
     * Determine the value we should use for 'datapoints' given a specific
     * period.
     *
     * @param string $datapoints
     * @param string $period
     *
     * @return string
     */
    protected function selectDatapoints($datapoints, $period)
    {
        if (!$datapoints || ($datapoints == 'auto')) {
            $datapoints = $this->defaultDatapoints($period);
        }

        return $datapoints . $this->shortPeriod($period);
    }

    /**
     * Ensure that the user did not supply an invalid value for 'period'.
     *
     * @hook validate env:metrics
     *
     * @param CommandData $commandData
     */
    public function validateOptions(CommandData $commandData)
    {
        $validGranularities = [
            self::DAILY_PERIOD,
            self::WEEKLY_PERIOD,
            self::MONTHLY_PERIOD,
        ];

        $input = $commandData->input();
        $this->validateOptionValue($input, 'period', $validGranularities);
        $this->validateItemWithinRange(
            $input,
            'datapoints',
            1,
            $this->datapointsMaximum($input->getOption('period')),
            ['auto']
        );
    }

    /**
     * Test to see if an option value is one of the provided values
     *
     * @param InputInterface $input
     * @param string $option_name
     * @param string[] $valid_values
     */
    protected function validateOptionValue(
        InputInterface $input,
        $option_name,
        array $valid_values
    ) {
        $value = $input->getOption($option_name);
        if (!in_array($value, $valid_values)) {
            throw new TerminusException(
                "'{value}' is an invalid value for {option_name}: must be one of {values}",
                [
                    'option_name' => $option_name,
                    'value' => $value,
                    'values' => implode(', ', $valid_values),
                ]
            );
        }
    }

    /**
     * Check to see if the specified item is within the specified
     * minimum/maximum range.
     *
     * @param InputInterface $input
     * @param string $option_name
     * @param string $minimum
     * @param string $maximum
     * @param string $exceptional_values
     */
    protected function validateItemWithinRange(
        InputInterface $input,
        $option_name,
        $minimum,
        $maximum,
        $exceptional_values = []
    ) {
        $value = $input->getOption($option_name);
        if (in_array($value, $exceptional_values)) {
            return;
        }
        $or_one_of = '';
        if (count($exceptional_values) != 0) {
            $or_one_of = (count(
                $exceptional_values
            ) == 1) ? 'or ' : 'or one of ';
        }
        if (($value < $minimum) || ($value > $maximum)) {
            throw new TerminusException(
                "'{value}' is an invalid value for {option_name}: "
                . 'it must be between {minimum} and {maximum} (inclusive) {or_one_of} {values}',
                [
                    'maximum' => $maximum,
                    'minimum' => $minimum,
                    'option_name' => $option_name,
                    'or_one_of' => $or_one_of,
                    'value' => $value,
                    'values' => implode(', ', $exceptional_values),
                ]
            );
        }
    }

    /**
     * Default data points to 12 / 28 if 'auto' is specified
     *
     * @param string $period
     *
     * @return string
     */
    public function defaultDatapoints($period)
    {
        // For now, out default values will just be the maximums for the period.
        // We should change this if we increase the maximums.
        return $this->datapointsMaximum($period);
    }

    /**
     * Return the maximum data point value for the provided period.
     *
     * @param string $period
     *
     * @return string
     */
    public function datapointsMaximum($period)
    {
        $defaultPeriodValues = [
            self::DAILY_PERIOD => self::DEFAULT_DAILY_DATAPOINTS,
            self::WEEKLY_PERIOD => self::DEFAULT_WEEKLY_DATAPOINTS,
            self::MONTHLY_PERIOD => self::DEFAULT_MONTHLY_DATAPOINTS,
        ];

        return $defaultPeriodValues[$period];
    }

    /**
     * Return the period in short format.
     *
     * @param string $period
     *
     * @return string
     */
    public function shortPeriod($period)
    {
        $shortPeriodValues = [
            self::DAILY_PERIOD => self::DAILY_PERIOD_SHORT,
            self::WEEKLY_PERIOD => self::WEEKLY_PERIOD_SHORT,
            self::MONTHLY_PERIOD => self::MONTHLY_PERIOD_SHORT,
        ];

        return $shortPeriodValues[$period];
    }
}
