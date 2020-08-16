<?php


namespace NewtonAnalytics\Apps;

use NewtonAnalytics\Apps\Math\BayesianInference;
use NewtonAnalytics\Apps\Math\PolynomialRegression;

require_once __DIR__ . "/ExceptionPBear.php";
require_once __DIR__ . "/PBearModel.php";

class PBear extends PBearModel
{
    private $bayes, $poly;
    private $stockReturns, $returnsAndYields, $posterior, $regression, $timeSeries, $trendLine, $chartData,
        $currentYieldRatio, $yieldRatioPosition;

    static $varFutureCast = 24;
    static $varBearDefinition = -0.2;
    static $varBayesCalibrationA = 0.01;
    static $varBayesCalibrationB = 0.05;
    static $varBayesScopeA = -1;
    static $varPolynomialOrder = 3;

    public function __construct(BayesianInference $bayes, PolynomialRegression $poly)
    {
        $this->bayes = $bayes;
        $this->poly = $poly;
    }

    public function getStockReturns() { return $this->stockReturns; }
    public function getReturnsAndYields() { return $this->returnsAndYields; }
    public function getPosterior() { return $this->posterior; }
    public function getRegression() { return $this->regression; }
    public function getTimeSeries() { return $this->timeSeries; }
    public function getChartData() { return $this->chartData; }
    public function getYieldRatioPosition() { return $this->yieldRatioPosition; }
    public function getCurrentYieldRatio() { return $this->currentYieldRatio; }

    public function runPBear($host, $user, $pass, $database, $charSet)
    {
        $this->setStockAndYieldData($host, $user, $pass, $database, $charSet);
        $this->calculateStockReturns();
        $this->joinReturnsAndYields();
        $this->calculateInference();
        $this->calculateRegression();
        $this->timeSeriesProbabilities();
        $this->calculateCurrentYieldRatio($this::$varBayesCalibrationB);
        $this->createTrendLine($this->regression, $this->posterior);
        $this->createChartData($this->posterior, $this->trendLine);
        $this->findYieldRatioPosition();

        $this->calculateCurrentValue($this->timeSeries);
        $this->calculatePercentDifference($this->timeSeries);
        $this->calculateAverageValue($this->timeSeries);
        $this->calculateDifferenceValue($this->timeSeries);
        $this->calculateMaximumValue($this->timeSeries);
        $this->calculateMinimumValue($this->timeSeries);
    }

    private function findYieldRatioPosition()
    {
        $chartData = $this->chartData;
        $currentYieldRatio = $this->currentYieldRatio;
        $yieldRatioPosition = null;
        $rowCount = count($chartData);

        for($i=0; $i<$rowCount; $i++)
        {
            $chartDataYieldRatio = strval($chartData[$i][0]);
            if($chartDataYieldRatio == $currentYieldRatio)
            {
                $yieldRatioPosition = $i;
            }
        }

        $this->yieldRatioPosition = $yieldRatioPosition;
    }

    private function calculateCurrentYieldRatio($varBayesCalibrationB)
    {
        $yieldData = $this->yieldData;
        $calibrationB = $varBayesCalibrationB;

        $currentYieldRatio = end($yieldData);
        $currentYieldRatio = strval(round($currentYieldRatio / $calibrationB) * $calibrationB);

        $this->currentYieldRatio = $currentYieldRatio;
    }

    private function createChartData($posterior, $trendline)
    {
        $chartData = null;
        $rowCount = count($trendline);
        $bayesCalibrationB = $posterior[1][0] - $posterior[0][0];
        $yield = 0;

        for($i=0; $i<$rowCount; $i++)
        {
            if(!isset($posterior[$i]))
            {
                $historicYieldValue = "null";
            }
            else
            {
                $historicYieldValue = $posterior[$i][1];
            }
            $chartData[] = array(round($yield, 2),$historicYieldValue,$trendline[$i]);
            $yield = $bayesCalibrationB + $yield;
        }

        $this->chartData = $chartData;
    }

    private function createTrendLine($regression, $posterior)
    {
        $currentYieldRatio = $this->currentYieldRatio;
        $trendLine = null;
        $yieldCount = count($posterior);
        $bayesCalibrationB = $posterior[1][0] - $posterior[0][0];
        $currentYieldCount = $currentYieldRatio / $bayesCalibrationB;
        $yield = 0;

        if($currentYieldCount >= $yieldCount)
        {
            $count = $currentYieldCount + 1;
        }
        else
        {
            $count = $yieldCount;
        }

        for($i=0; $i<$count; $i++)
        {
            $dataPoint =  $regression[3] * pow($yield,3) +
                          $regression[2] * pow($yield,2) +
                          $regression[1] * $yield +
                          $regression[0];

            if($dataPoint > 0.99)
            {
                $dataPoint = 0.99;
            }

            $trendLine[] = $dataPoint;
            $yield = $yield + $bayesCalibrationB;
        }

        $this->trendLine = $trendLine;
    }

    private function timeSeriesProbabilities()
    {
        $regression = $this->regression;
        $yieldData = $this->yieldData;
        $stockData = $this->stockData;
        $timeSeries = null;
        $i=0;
        $preparedDates = $this->prepareDates($stockData);

        foreach($yieldData as $yieldRatio)
        {
            $prob = $regression[3]*pow($yieldRatio,3) +
                $regression[2]*pow($yieldRatio,2) +
                $regression[1]*$yieldRatio +
                $regression[0];

            if($prob > 0.99)
            {
                $prob = 0.99;
            }

            $timeSeries[] = array($preparedDates[$i], $prob);
            $i++;
        }

        $this->timeSeries = $timeSeries;
    }

    private function prepareDates($stockData)
    {
        $preparedDates = null;

        foreach($stockData as $row)
        {
            $rawDate = $row[2];
            $year = substr($rawDate, 0, 4);
            $month = substr($rawDate, 4, 2);
            $preparedDates[] = $month . "-" . $year;
        }

        return $preparedDates;
    }

    private function calculateRegression()
    {
        $this->regression = $this->poly->polynomialRegression($this->posterior, $this::$varPolynomialOrder);
    }

    private function calculateInference()
    {
        $this->posterior = $this->bayes->bayesInference(
            $this->returnsAndYields,
            $this::$varBayesCalibrationA,
            $this::$varBayesCalibrationB,
            $this::$varBearDefinition,
            $this::$varBayesScopeA
        );
    }

    private function joinReturnsAndYields()
    {
        $stockReturns = $this->stockReturns;
        $yieldData = $this->yieldData;
        $data = null;
        $dataCount = count($stockReturns);

        for($i=0; $i<$dataCount; $i++)
        {
            $data[] = array($stockReturns[$i], $yieldData[$i]);
        }

        $this->returnsAndYields = $data;
    }

    private function calculateStockReturns()
    {
        $stockData = $this->stockData;
        $stockDataCount = count($stockData);
        $stockReturns = null;

        for($i=0; $i<$stockDataCount-$this::$varFutureCast; $i++)
        {
            $minReturn = +999999999999;

            for($j=1; $j<$this::$varFutureCast; $j++)
            {
                $return = log($stockData[$i+$j][4] / $stockData[$i][4]);

                if($return < $minReturn)
                {
                    $minReturn = $return;
                }
            }

            $stockReturns[] = $minReturn;
        }

        $this->stockReturns = $stockReturns;
    }
}
