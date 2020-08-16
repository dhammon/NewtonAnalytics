<?php


namespace NewtonAnalytics\Apps;

require_once __DIR__ . "/../helpers/DBHelper.php";

class PBearModel extends DBHelper
{
    protected $stockData;
    protected $yieldData;
    protected $currentValue, $differenceValue, $percentDifference, $averageValue, $minimumValue, $maximumValue;

    public function setStockAndYieldData($host, $user, $pass, $database, $charSet)
    {
        $this->connect($host, $user, $pass, $database, $charSet);
        $this->queryStockData();
        $this->queryYieldData();
        $this->validateYieldData();
        $this->validateStockData();
    }

    public function getStockData() { return $this->stockData; }
    public function getYieldData() { return $this->yieldData; }
    public function getCurrentValue() { return $this->currentValue; }
    public function getDifferenceValue() { return $this->differenceValue; }
    public function getPercentDifference() { return $this->percentDifference; }
    public function getAverageValue() { return $this->averageValue; }
    public function getMinimumValue() { return $this->minimumValue; }
    public function getMaximumValue() { return $this->maximumValue; }

    //todo make stats ran here instead of in PBear and RiskReturnUtilities (yield position, current yield, etc)
    //todo remove stats from PBear and RiskReturnUtilities
    //todo remove tests Unit/Int
    protected function calculateCurrentValue($timeSeries)
    {
        $this->currentValue = end($timeSeries);
    }

    protected function calculateDifferenceValue($timeSeries)
    {
        $currentValue = end($timeSeries);
        $previousValue = prev($timeSeries);
        $differenceValue = $currentValue[1] - $previousValue[1];
        $this->differenceValue = $differenceValue;
    }

    protected function calculatePercentDifference($timeSeries)
    {
        $currentValue = end($timeSeries);
        $previousValue = prev($timeSeries);
        $percentDifference = $currentValue[1] / $previousValue[1] - 1;

        $this->percentDifference = $percentDifference;
    }

    protected function calculateAverageValue($timeSeries)
    {
        $values = null;

        foreach($timeSeries as $row)
        {
            $values[] = $row[1];
        }

        $averageValue = array_sum($values) / count($values);

        $this->averageValue = $averageValue;
    }

    protected function calculateMinimumValue($timeSeries)
    {
        $timeSeriesCount = count($timeSeries);
        $minimumValue = array('dateCode', +9999999999);

        for($i=0; $i<$timeSeriesCount; $i++)
        {
            if($timeSeries[$i][1] <= $minimumValue[1])
            {
                $minimumValue = $timeSeries[$i];
            }
        }

        $this->minimumValue = $minimumValue;
    }

    protected function calculateMaximumValue($timeSeries)
    {
        $timeSeriesCount = count($timeSeries);
        $maximumValue = array('dateCode', -9999999999);

        for($i=0; $i<$timeSeriesCount; $i++)
        {
            if($timeSeries[$i][1] >= $maximumValue[1])
            {
                $maximumValue = $timeSeries[$i];
            }
        }

        $this->maximumValue = $maximumValue;
    }

    private function validateStockData()
    {
        $stockData = $this->stockData;

        $this->validateIsArray($stockData);
        $this->validateManyRows($stockData);
        $this->validateColumnNumber($stockData, 5);

        foreach($stockData as $data)
        {
            $id = $data[0];
            $timestamp = $data[1];
            $dateCode = $data[2];
            $datePosted = $data[3];
            $adjPrice = $data[4];

            $this->validateIsNumeric($id);
            $this->validateMaxCharNumber($timestamp, 20);
            $this->validateIsNumeric($dateCode);
            $this->validateMaxCharNumber($datePosted, 20);
            $this->validateIsNumeric($adjPrice);
        }
    }

    private function validateYieldData()
    {
        $yieldData = $this->yieldData;

        $this->validateIsArray($yieldData);

        foreach($yieldData as $data)
        {
            $yieldRatio = $data;
            $this->validateIsNumeric($yieldRatio);
        }
    }

    private function validateMaxCharNumber($data, $MaxCharNumber)
    {
        $charCount = strlen($data);

        if($charCount > $MaxCharNumber)
        {
            throw new ExceptionPBear("Character count too large. \n");
        }
    }

    private function validateIsNumeric($data)
    {
        if(!is_numeric($data))
        {
            throw new ExceptionPBear("Data is not numeric. \n");
        }
    }

    private function validateColumnNumber($data, $columnNumber)
    {
        foreach($data as $row)
        {
            $columnCount = count($row);

            if($columnCount != $columnNumber)
            {
                throw new ExceptionPBear("Data has insufficient number of columns. \n");
            }
        }
    }

    private function validateIsArray($data)
    {
        if(!is_array($data))
        {
            throw new ExceptionPBear("Data is not an array. \n");
        }
    }

    private function validateManyRows($data)
    {
        foreach($data as $row)
        {
            if(!is_array($row))
            {
                throw new ExceptionPBear("Insufficient number of rows in data. \n");
            }
        }
    }

    private function queryStockData()
    {
        $stockData = null;
        $query = "SELECT * FROM spy_prices";

        $result = $this->connection->query($query);
        while($row = $result->fetch_array(MYSQLI_NUM))
        {
            $stockData[] = $row;
        }

        $this->stockData = $stockData;
    }

    private function queryYieldData()
    {
        $yieldData = null;
        $query = "SELECT * FROM treasury_yields";

        $result = $this->connection->query($query);
        while($row = $result->fetch_array(MYSQLI_NUM))
        {
            $yieldData[] = $row[6];
        }

        $this->yieldData = $yieldData;
    }
}