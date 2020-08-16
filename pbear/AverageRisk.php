<?php


namespace NewtonAnalytics\Apps;

use NewtonAnalytics\Apps\Math\Statistics;
use NewtonAnalytics\Apps\Math\PolynomialRegression;
use NewtonAnalytics\Apps\Math\BayesianInference;

require_once __DIR__ . "/../pbear/RiskReturnUtilities.php";

class AverageRisk extends RiskReturnUtilities
{
    private $bayes, $poly, $statistics;
    private $dataMap, $regression, $forwardRisk;

    static $varFutureCast = 24;
    static $varBayesCalibrationA = 0.01;
    static $varBayesCalibrationB = 0.05;
    static $varTrendLine = 1;

    public function __construct(BayesianInference $bayes, PolynomialRegression $poly, Statistics $statistics)
    {
        $this->bayes = $bayes;
        $this->poly = $poly;
        $this->statistics = $statistics;
    }

    public function runAverageRisk($host, $user, $pass, $database, $charSet)
    {
        $this->setStockAndYieldData($host, $user, $pass, $database, $charSet);
        $this->calculateForwardReturns($this::$varFutureCast);
        $this->calculateForwardRisk();
        $this->mergeForwardAndYield($this->forwardRisk);

        $this->dataMap = $this->bayes->dataMap($this->forwardAndYield, $this::$varBayesCalibrationA, $this::$varBayesCalibrationB);
        $this->createYieldsAndValues($this->dataMap);
        $this->regression = $this->poly->polynomialRegression($this->yieldsAndValues, $this::$varTrendLine);
        $this->calculateTimeSeries($this->regression);
        $this->calculateCurrentYieldRatio($this::$varBayesCalibrationB);
        $this->createTrendLine($this->regression);
        $this->createChartData();
        $this->findYieldRatioPosition();
        $this->makeCurrentMonth();

        $this->calculateCurrentValue($this->timeSeries);
        $this->calculateMinimumValue($this->timeSeries);
        $this->calculateMaximumValue($this->timeSeries);
        $this->calculateDifferenceValue($this->timeSeries);
        $this->calculatePercentDifference($this->timeSeries);
        $this->calculateAverageValue($this->timeSeries);
    }

    private function calculateForwardRisk()
    {
        $forwardReturns = $this->forwardReturns;
        $cast = $this::$varFutureCast;

        $forwardRisk = null;
        $castData = null;
        $returnCount = count($forwardReturns);

        for($i=0; $i<$returnCount-$cast+1; $i++)
        {
            for($j=$i; $j<$i+$cast; $j++)
            {
                $castData[] = $forwardReturns[$j];
            }
            $forwardRisk[] = $this->statistics->statsStandardDeviation($castData);
            unset($castData);
        }

        $this->forwardRisk = $forwardRisk;
    }
}