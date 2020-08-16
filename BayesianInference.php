<?php


namespace NewtonAnalytics\Apps\Math;

require_once(__DIR__ . "/ExceptionBayesianInference.php");

class BayesianInference
{
    public function bayesInference($data, $calibrationA, $calibrationB, $A, $AScope)
    {
        $this->validateA($A);
        $this->validateAScope($AScope);         //$AScope is UpTo(-1), Only(0), After(1)

        $dataMap = $this->dataMap($data, $calibrationA, $calibrationB);
        $this->validateAInRange($A, $dataMap);
        $prior = $this->prior($dataMap, $A, $AScope);
        $marginalLikelihood = $this->marginalLikelihood($dataMap);
        $likelihood = $this->likelihood($dataMap, $A, $AScope);
        $posterior = $this->posterior($likelihood, $prior, $marginalLikelihood);
        $presentPosterior = $this->presentPosterior($dataMap, $posterior);

        return $presentPosterior;
    }

    private function presentPosterior($dataMap, $posterior)
    {
        $presentPosterior = null;
        $axisCount = count($dataMap[0]);

        for($i=1; $i<$axisCount; $i++)
        {
            $presentPosterior[] = array($dataMap[0][$i], $posterior[$i-1]);
        }

        return $presentPosterior;
    }

    private function posterior($likelihood, $prior, $marginalLikelihood)
    {
        $posterior = null;
        $BCount = count($likelihood);

        for($i=0; $i<$BCount; $i++)
        {
            if($marginalLikelihood[$i] == 0)
            {
                $posterior[] = 0;
            }
            else
            {
                $posterior[] = $likelihood[$i] * $prior / $marginalLikelihood[$i];
            }
        }

        return $posterior;
    }

    private function likelihood($dataMap, $A, $AScope)
    {
        $likelihood = null;
        $lastColumn = count($dataMap[1]) - 1;
        $APosition = $this->searchColumn($dataMap, $A);
        $frequency = $this->subjectFrequency($dataMap, $APosition, $AScope, $lastColumn);
        $columnCount = count($dataMap[0]);

        if($frequency == 0)
        {
            throw new ExceptionBayesianInference("No subject frequency found in data.");
        }

        for($i=1; $i<$columnCount; $i++)
        {
            $BFrequency = $this->subjectFrequency($dataMap, $APosition, $AScope, $i);

            $likelihood[] = $BFrequency / $frequency;
        }

        return $likelihood;
    }

    private function marginalLikelihood($dataMap)
    {
        $columnCount = count($dataMap[0]);
        $lastRowPosition = count($dataMap)-1;
        $marginalLikelihood = null;
        $totalLikelihood = $this->totalFrequency($dataMap);

        for($i=1; $i<$columnCount; $i++)
        {
            $marginalLikelihood[] = $dataMap[$lastRowPosition][$i] / $totalLikelihood;
        }

        return $marginalLikelihood;
    }

    private function prior($dataMap, $A, $AScope)        //P[A]
    {
        $prior = null;
        $lastColumn = count($dataMap[1]) - 1;
        $APosition = $this->searchColumn($dataMap, $A);
        $frequency = $this->subjectFrequency($dataMap, $APosition, $AScope, $lastColumn);
        $totalFrequency = $this->totalFrequency($dataMap);

        $prior = $frequency / $totalFrequency;

        return $prior;
    }

    private function totalFrequency($dataMap)
    {
        $rowCount = count($dataMap)-1;
        $columnCount = count($dataMap[1])-1;
        $totalFrequency = $dataMap[$rowCount][$columnCount];

        if($totalFrequency == 0)
        {
            throw new ExceptionBayesianInference("No total frequency in data found.");
        }

        return $totalFrequency;
    }

    private function subjectFrequency($dataMap, $APosition, $AScope, $columnPosition)
    {
        $frequency = null;
        $rowCount = count($dataMap);

        if($AScope == -1)
        {
            for($i=1; $i<$APosition; $i++)
            {
                $frequency = $dataMap[$i][$columnPosition] + $frequency;
            }
        }

        if($AScope == 0)
        {
            $frequency = $dataMap[$APosition][$columnPosition];
        }

        if($AScope == 1)
        {
            for($i=$APosition; $i<$rowCount-1; $i++)
            {
                $frequency = $dataMap[$i][$columnPosition] + $frequency;
            }
        }

        return $frequency;
    }

    public function dataMap($data, $calibrationA, $calibrationB)
    {
        $this->validateCalibration($calibrationA);
        $this->validateCalibration($calibrationB);
        $this->validateData($data);

        $preparedData = $this->prepareData($data, $calibrationA, $calibrationB);
        $minMaxValues = $this->minMaxValues($preparedData);
        $zeroMap = $this->buildZeroMap($minMaxValues, $calibrationA, $calibrationB);

        $dataMap = $this->buildHistograms($zeroMap, $preparedData);

        return $dataMap;
    }

    private function buildHistograms($zeroMap, $preparedData)
    {
        $loadedHistograms = $this->loadHistograms($zeroMap, $preparedData);
        $loadedHistogramsRowSums = $this->sumRows($loadedHistograms);
        $loadedHistogramsColumnSums = $this->sumColumns($loadedHistogramsRowSums);
        $histograms = $loadedHistogramsColumnSums;

        return $histograms;
    }

    private function sumColumns($loadedHistogramsRowSums)
    {
        $loadedHistogramsColumnSums = $loadedHistogramsRowSums;
        $rowCount = count($loadedHistogramsRowSums);
        $columnCount = count($loadedHistogramsRowSums[0])+1;
        $columnSum = null;
        $newRow[] = "";

        for($i=1; $i<$columnCount; $i++)
        {
            for($j=1; $j<$rowCount; $j++)
            {
                $columnSum = $columnSum + $loadedHistogramsColumnSums[$j][$i];
            }

            $newRow[] = $columnSum;
            $columnSum = null;
        }

        $loadedHistogramsColumnSums[] = $newRow;

        return $loadedHistogramsColumnSums;
    }

    private function sumRows($loadedHistograms)
    {
        $loadedHistogramsRowSums = $loadedHistograms;
        $rowCount = count($loadedHistograms);
        $columnCount = count($loadedHistograms[0]);
        $rowSum = null;

        for($i=1; $i<$rowCount; $i++)
        {
            for($j=1; $j<$columnCount; $j++)
            {
                $rowSum = $rowSum + $loadedHistogramsRowSums[$i][$j];
            }

            $loadedHistogramsRowSums[$i][] = $rowSum;
            $rowSum = null;
        }

        return $loadedHistogramsRowSums;
    }

    private function loadHistograms($zeroMap, $preparedData)
    {
        $loadedHistograms = $zeroMap;

        foreach($preparedData as $point)
        {
            $columnNumber = $this->searchRow($zeroMap, $point[1]);
            $rowNumber = $this->searchColumn($zeroMap, $point[0]);

            $loadedHistograms[$rowNumber][$columnNumber] = $loadedHistograms[$rowNumber][$columnNumber] + 1;
        }

        return $loadedHistograms;
    }

    private function searchRow($zeroMap, $needle)
    {
        $columnCount = count($zeroMap[0]);

        for($i=0; $i<$columnCount; $i++)
        {
            if($needle >= $zeroMap[0][$i])
            {
                if($needle <= $zeroMap[0][$i+1])
                {
                    $first = $needle - $zeroMap[0][$i];
                    $next = $needle - $zeroMap[0][$i+1];
                    $min = min($first, $next);

                    if($min == $first)
                    {
                        return $i;
                    }
                    if($min == $next)
                    {
                        return $i+1;
                    }
                }
            }
        }

        return null;
    }

    private function searchColumn($zeroMap, $needle)
    {
        $rowCount = count($zeroMap);

        for($i=0; $i<$rowCount; $i++)
        {
            if($needle >= $zeroMap[$i][0])
            {
                if($needle <= $zeroMap[$i+1][0])
                {
                    $first = $needle - $zeroMap[$i][0];
                    $next = $needle - $zeroMap[$i+1][0];
                    $min = min($first, $next);

                    if($min == $first)
                    {
                        return $i;
                    }
                    if($min == $next)
                    {
                        return $i+1;
                    }
                }
            }
        }

        return null;
    }

    private function buildZeroMap($minMaxValues, $calibrationA, $calibrationB)
    {
        $zeroMap = null;
        $numberOfColumns = (($minMaxValues[1][1] - $minMaxValues[1][0]) / $calibrationB);
        $numberOfRows = (($minMaxValues[0][1] - $minMaxValues[0][0]) / $calibrationA);

        for($i=0; $i<$numberOfRows+3; $i++)
        {
            if($i == 0)
            {
                $row[] = -99999999999999999;
                for($j=0; $j<$numberOfColumns+1; $j++)
                {
                    $row[] = $minMaxValues[1][0] + $calibrationB * $j;
                }
            }
            else
            {
                $row[] = $minMaxValues[0][0] + $calibrationA * ($i-1);
                for($j=0; $j<$numberOfColumns+1; $j++)
                {
                    $row[] = 0;
                }
            }

            $zeroMap[] = $row;
            unset($row);
        }

        return $zeroMap;
    }

    private function minMaxValues($preparedData)
    {
        $minMaxValues = null;

        for($i=0; $i<2; $i++)
        {
            $min = $preparedData[0][$i];
            $max = $preparedData[0][$i];

            foreach($preparedData as $row)
            {
                $observation = $row[$i];

                if($observation > $max)
                {
                    $max = $observation;
                }

                if($observation < $min)
                {
                    $min = $observation;
                }
            }

            $minMaxValues[] = array($min, $max);
        }

        return $minMaxValues;
    }

    private function prepareData($data, $calibrationA, $calibrationB)
    {
        $preparedData = null;

        foreach($data as $row)
        {
            $newDataA = strval(round($row[0]/$calibrationA) * $calibrationA);
            $newDataB = strval(round($row[1]/$calibrationB) * $calibrationB);
            $newRow = array($newDataA, $newDataB);
            $preparedData[] = $newRow;
        }

        return $preparedData;
    }

    private function validateAScope($AScope)
    {
        if($AScope != 0 && $AScope != -1 && $AScope != 1)
        {
            throw new ExceptionBayesianInference("Event value scope must be -1, 0, or 1.");
        }
    }

    private function validateA($A)
    {
        if(!is_numeric($A))
        {
            throw new ExceptionBayesianInference("Event value A must be numeric.");
        }
    }

    private function validateAInRange($A, $dataMap)
    {
        $dataMap = array_splice($dataMap, 1);
        $minMax = $this->minMaxValues($dataMap);

        if($A < $minMax[0][0] || $A > $minMax[0][1])
        {
            throw new ExceptionBayesianInference("Event value A is not within data range.");
        }
    }

    private function validateCalibration($calibration)
    {
        if($calibration <= 0 || !is_numeric($calibration))
        {
            throw new ExceptionBayesianInference("Calibration must be a number greater than 0.");
        }
    }

    private function validateData($data)
    {
        $this->validateArrayAtLeastTwoRows($data);
        $this->validateArrayTwoColumns($data);
        $this->validateValuesNumeric($data);
    }

    private function validateValuesNumeric($data)
    {
        foreach($data as $row)
        {
            foreach($row as $value)
            {
                if(!is_numeric($value))
                {
                    throw new ExceptionBayesianInference("Data values are not numeric. \n");
                }
            }
        }
    }

    private function validateArrayAtLeastTwoRows($data)
    {
        if(!is_array($data))
        {
            throw new ExceptionBayesianInference("Data must be an array. \n");
        }

        if(!is_array($data[0]))
        {
            throw new ExceptionBayesianInference("Data must be a multidimensional array. \n");
        }

        if(count($data) < 2)
        {
            throw new ExceptionBayesianInference("Data must have at least two rows.");
        }
    }

    private function validateArrayTwoColumns($data)
    {
        foreach($data as $row)
        {
            $columnCount = count($row);

            if($columnCount != 2)
            {
                throw new ExceptionBayesianInference("All Data rows must have two columns. \n");
            }
        }
    }
}
