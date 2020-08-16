<?php


namespace NewtonAnalytics\Apps;

use NewtonAnalytics\Apps\Math\BayesianInference;
use NewtonAnalytics\Apps\Math\PolynomialRegression;

require_once __DIR__ . "/../pbear/RiskReturnUtilities.php";

class AverageReturn extends RiskReturnUtilities
{
    private $bayes, $poly;
    private $dataMap, $regression;

    static $varFutureCast = 24;
    static $varBayesCalibrationA = 0.01;
    static $varBayesCalibrationB = 0.05;
    static $varTrendLine = 1;

    public function __construct(BayesianInference $bayes, PolynomialRegression $poly)
    {
        $this->bayes = $bayes;
        $this->poly = $poly;
    }

    public function runAverageReturn($host, $user, $pass, $database, $charSet)
    {
        $this->setStockAndYieldData($host, $user, $pass, $database, $charSet);
        $this->calculateForwardReturns($this::$varFutureCast);
        $this->mergeForwardAndYield($this->forwardReturns);

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
}
