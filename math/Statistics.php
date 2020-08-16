<?php


namespace NewtonAnalytics\Apps\Math;

require_once __DIR__ . "/ExceptionStatistics.php";

class Statistics
{
    public function deriveCorrelationMatrix(array $covarianceMatrix, array $standardDeviations)
    {
        $this->validateArraySizeMatch($covarianceMatrix, $standardDeviations);
        $this->validateArrayNotBlank($standardDeviations);
        foreach($covarianceMatrix as $array)
        {
            $this->validateArrayNotBlank($array);
            $this->validateArrayContent($array);
        }
        $this->validateArrayContent($standardDeviations);

        $count = count($standardDeviations);
        $correlationRow = array();
        $correlationMatrix = array();

        for($i=0; $i<$count; $i++)
        {
            for($j=0; $j<$count; $j++)
            {
                $numerator = $covarianceMatrix[$j][$i];
                $denominator = $standardDeviations[$j] * $standardDeviations[$i];
                $correlationRow[] = $numerator / $denominator;
            }
            $correlationMatrix[] = $correlationRow;
            unset($correlationRow);
        }

        return $correlationMatrix;
    }

    public function statsCovariance(array $tickerReturnData, array $indexReturnData)
    {
        $this->validateArrayMinSize($tickerReturnData, 2);
        $this->validateArrayContent($tickerReturnData);
        $this->validateArrayNotBlank($tickerReturnData);
        $this->validateArrayMinSize($indexReturnData, 2);
        $this->validateArrayContent($indexReturnData);
        $this->validateArrayNotBlank($indexReturnData);
        $this->validateArraySizeMatch($tickerReturnData, $indexReturnData);

        $count = count($tickerReturnData);
        $groupAverage = (array_sum($tickerReturnData) * array_sum($indexReturnData)) / $count;
        $groupReturns = 0;

        for($i=0; $i<$count; $i++)
        {
            $groupReturns += $tickerReturnData[$i] * $indexReturnData[$i];
        }

        $covariance = ($groupReturns - $groupAverage) / ($count - 1);

        return $covariance;
    }

    public function statsStandardDeviation(array $data)
    {
        $this->validateArrayMinSize($data, 2);
        $this->validateArrayContent($data);
        $this->validateArrayNotBlank($data);

        $variance = $this->statsVariance($data);
        $standardDeviation = sqrt($variance);

        return $standardDeviation;
    }

    public function statsVariance(array $returnData)
    {
        $this->validateArrayMinSize($returnData, 2);
        $this->validateArrayContent($returnData);
        $this->validateArrayNotBlank($returnData);

        $average = $this->statsAverage($returnData);
        $sumOfSquares = 0;
        $count = count($returnData);

        for($i=0; $i<$count; $i++)
        {
            $sumOfSquares += ($returnData[$i] - $average) * ($returnData[$i] - $average);
        }

        return $sumOfSquares / ($count - 1);
    }

    public function statsAverage(array $returnData)
    {
        $this->validateArrayMinSize($returnData, 2);
        $this->validateArrayContent($returnData);
        $this->validateArrayNotBlank($returnData);

        $count = count($returnData);
        $sumValues = 0;

        for($i=0; $i<$count; $i++)
        {
            $sumValues += $returnData[$i];
        }

        return $sumValues / $count;
    }

    private function validateArraySizeMatch(array $array1, array $array2)
    {
        if(count($array1) != count($array2))
        {
            throw new ExceptionStatistics("Statistics error: Array sizes do not match.");
        }
    }

    private function validateArrayMinSize(array $array, $minSize)
    {
        if(count($array) < $minSize)
        {
            throw new ExceptionStatistics("Statistics error: not enough observations to make calculation.");
        }
    }

    private function validateArrayNotBlank(array $array)
    {
        if($array == "" || $array == null)
        {
            throw new ExceptionStatistics("Statistics error: data is blank.");
        }
    }

    private function validateArrayContent(array $array)
    {
        foreach($array as $value)
        {
            if(!is_numeric($value))
            {
                throw new ExceptionStatistics("Statistics error: observation values not numeric.");
            }
        }
    }
}
