<?php


namespace NewtonAnalytics\Apps;

require_once __DIR__ . "/../pbear/PBearModel.php";

class RiskReturnUtilities extends PBearModel
{
    protected $forwardAndYield, $yieldsAndValues, $trendLine, $chartData, $currentYieldRatio, $yieldRatioPosition,
        $currentMonth, $forwardReturns, $timeSeries;

    public function getChartData() { return $this->chartData; }
    public function getYieldRatioPosition() { return $this->yieldRatioPosition; }
    public function getForwardAndYield() { return $this->forwardAndYield; }
    public function getTimeSeries() { return $this->timeSeries; }
    public function getCurrentYieldRatio() { return $this->currentYieldRatio; }
    public function getCurrentMonth() { return $this->currentMonth; }

    protected function calculateTimeSeries($regression)
    {
        $yieldData = $this->yieldData;
        $stockData = $this->stockData;
        $timeSeries = null;
        $i=0;
        $preparedDates = $this->prepareDates($stockData);

        foreach($yieldData as $yieldRatio)
        {
            $prob = $regression[1]*$yieldRatio + $regression[0];

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

    protected function calculateForwardReturns($cast)
    {
        $stockData = $this->stockData;

        $forwardReturns = null;
        $stockDataCount = count($stockData);
        $j = $cast-1;

        for($i=0; $i<$stockDataCount-$cast+1; $i++)
        {
            $forwardReturns[] = log($stockData[$j][4]/$stockData[$i][4]);
            $j++;
        }

        $this->forwardReturns = $forwardReturns;
    }

    protected function makeCurrentMonth()
    {
        $stockData = $this->stockData;
        $row = end($stockData);
        $currentMonthRaw = $row[2];
        $year = substr($currentMonthRaw, 0,4);
        $month = substr($currentMonthRaw, 4,2);
        $currentMonth = $month . "-" . $year;

        $this->currentMonth = $currentMonth;
    }

    protected function findYieldRatioPosition()
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

    protected function calculateCurrentYieldRatio($varBayesCalibrationB)
    {
        $yieldData = $this->yieldData;
        $calibrationB = $varBayesCalibrationB;

        $currentYieldRatio = end($yieldData);
        $currentYieldRatio = strval(round($currentYieldRatio / $calibrationB) * $calibrationB);

        $this->currentYieldRatio = $currentYieldRatio;
    }

    //todo think about capping the trendline length
    protected function createChartData()
    {
        $yieldsAndValues = $this->yieldsAndValues;
        $trendLine = $this->trendLine;
        $chartData = null;
        $rowCount = count($trendLine);
        $bayesCalibrationB = $yieldsAndValues[1][0] - $yieldsAndValues[0][0];
        $yield = 0;

        for($i=0; $i<$rowCount; $i++)
        {
            if(!isset($yieldsAndValues[$i]))
            {
                $historicYieldValue = "null";
            }
            else
            {
                $historicYieldValue = $yieldsAndValues[$i][1];
            }
            $chartData[] = array(round($yield, 2),$historicYieldValue,$trendLine[$i]);
            $yield = $bayesCalibrationB + $yield;
        }

        $this->chartData = $chartData;
    }

    protected function createTrendLine($regression)
    {
        $currentYieldRatio = $this->currentYieldRatio;
        $yieldsAndValues = $this->yieldsAndValues;
        $trendLine = null;
        $yieldCount = count($yieldsAndValues);
        $bayesCalibrationB = $yieldsAndValues[1][0] - $yieldsAndValues[0][0];
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
            $trendLine[] = $regression[1] * $yield + $regression[0];
            $yield = $yield + $bayesCalibrationB;
        }

        $this->trendLine = $trendLine;
    }

    protected function createYieldsAndValues($dataMap)
    {
        $yieldsAndValues = null;
        $weightedValues = $this->calculateWeightedValues($dataMap);
        $yields = $dataMap[0];
        $yieldCount = count($yields);

        for($i=1; $i<$yieldCount-1; $i++)
        {
            $yieldsAndValues[] = array($yields[$i], $weightedValues[$i-1]);
        }

        $this->yieldsAndValues = $yieldsAndValues;
    }

    private function calculateWeightedValues($dataMap)
    {
        $weightedValues = null;
        $weightedDataMap = $this->createWeightedDataMap($dataMap);
        $rowCount = count($weightedDataMap);
        $colCount = count($weightedDataMap[0]);

        for($i=0; $i<$colCount; $i++)
        {
            $value = 0;

            for($j=0; $j<$rowCount; $j++)
            {
                $cell = $weightedDataMap[$j][$i];
                $value = $cell + $value;
            }

            $weightedValues[] = $value;
        }

        return $weightedValues;
    }

    private function createWeightedDataMap($dataMap)
    {
        $weightedDataMap = null;
        $weightedDataRow = null;
        $rowCount = count($dataMap);     //includes last sum row
        $colCount = count($dataMap[0]);  //does not include last sum col as sum col not in first row

        for($i=1; $i<$rowCount-1; $i++)
        {
            for($j=1; $j<$colCount-1; $j++)
            {
                $cell = $dataMap[$i][$j];
                $colSum = $dataMap[$rowCount-1][$j];
                $value = $dataMap[$i][0];

                if($cell == 0)
                {
                    $weightedDataRow[] = 0;
                }
                else
                {
                    $weightedDataRow[] = $cell / $colSum * $value;
                }
            }
            $weightedDataMap[] = $weightedDataRow;
            unset($weightedDataRow);
        }

        return $weightedDataMap;
    }

    protected function mergeForwardAndYield($forwardData)
    {
        $yieldData = $this->yieldData;
        $forwardAndYield = null;
        $dataCount = count($forwardData);

        for($i=0; $i<$dataCount; $i++)
        {
            $forwardAndYield[] = array($forwardData[$i], $yieldData[$i]);
        }

        $this->forwardAndYield = $forwardAndYield;
    }
}