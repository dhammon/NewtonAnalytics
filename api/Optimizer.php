<?php


namespace NewtonAnalytics\Api;

require_once __DIR__ . "/../config/Config.php";

class Optimizer
{
    private $statistics;
    private $matrixAlgebra;
    private $financialCalculations;
    private $portfolioReturn;
    private $numberPortfoliosToRun;
    private $portfolioReturnPrecision;

    public function __construct(
        \NewtonAnalytics\Apps\Math\Statistics $statistics,
        \NewtonAnalytics\Apps\Math\MatrixAlgebra $matrixAlgebra,
        \NewtonAnalytics\Apps\Math\FinancialCalculations $financialCalculations)
    {
        $this->statistics = $statistics;
        $this->matrixAlgebra = $matrixAlgebra;
        $this->financialCalculations = $financialCalculations;

        $this->portfolioReturn = Config::$app["modernPortfolio"]["startCasePortfolioReturn"];
        $this->numberPortfoliosToRun = Config::$app["modernPortfolio"]["numberPortfoliosToRun"];
        $this->portfolioReturnPrecision = Config::$app["modernPortfolio"]["portfolioReturnPrecision"];
    }

    public function runOptimizer(array $limitedPriceArrays)
    {
        $returnsArrays = $this->buildReturnsArrays($limitedPriceArrays);
        $averageReturns = $this->buildAverageReturns($returnsArrays);
        $covarianceMatrix = $this->buildCovarianceMatrix($returnsArrays);
        $bhmMatrix = $this->buildBhmMatrix($covarianceMatrix, $averageReturns);
        $inverseBhmMatrix = $this->inverseBhmMatrix($bhmMatrix);
        $efficientFrontier = $this->buildEfficientFrontier($inverseBhmMatrix, $covarianceMatrix);

        return $efficientFrontier;
    }

    public function buildEfficientFrontier(array $inverseBhmMatrix, array $covarianceMatrix)
    {
        $proportionsAndRisk = array();
        $efficientFrontier = array();
        $portfolioWeights = $this->getPortfolioWeights($inverseBhmMatrix);
        $portfolioReturn = $this->portfolioReturn;

        for($i=0; $i<$this->numberPortfoliosToRun; $i++)
        {
            $proportionsAndRisk[] = $portfolioReturn;
            $returnMatrix = array(array(1, $portfolioReturn));
            $portfolioProportions = $this->matrixAlgebra->matrixMultiplication($returnMatrix, $portfolioWeights);

            foreach($portfolioProportions[0] as $proportion)
            {
                $proportionsAndRisk[] = $proportion;
            }

            $portfolioStandardDeviation = $this->getPortfolioStandardDeviation($portfolioProportions, $covarianceMatrix);
            $proportionsAndRisk[] = $portfolioStandardDeviation;

            $portfolioReturn = $portfolioReturn + $this->portfolioReturnPrecision;
            $efficientFrontier[] = $proportionsAndRisk;
            $portfolioReturn = $portfolioReturn + $this->portfolioReturnPrecision;
            unset($proportionsAndRisk);
        }

        return $efficientFrontier;
    }

    private function getPortfolioStandardDeviation(array $portfolioWeights, array $covarianceMatrix)
    {
        $weightsByCovariance = $this->matrixAlgebra->matrixMultiplication($portfolioWeights, $covarianceMatrix);
        $transposedWeights = $this->matrixAlgebra->matrixTranspose($portfolioWeights);
        $portfolioVariance = $this->matrixAlgebra->matrixMultiplication($weightsByCovariance, $transposedWeights);
        $portfolioStandardDeviation = sqrt($portfolioVariance[0][0]);

        return $portfolioStandardDeviation;
    }

    private function getPortfolioWeights(array $inverseBhmMatrix)
    {
        $portfolioWeights = array();
        $tickersCount = count($inverseBhmMatrix) - 2;
        $lastTwoRows = array_slice($inverseBhmMatrix, -2, 2);

        foreach($lastTwoRows as $row)
        {
            $portfolioWeights[] = array_slice($row, 0, $tickersCount);
        }

        return $portfolioWeights;
    }

    public function inverseBhmMatrix(array $bhmMatrix)
    {
        $inverseBhmMatrix = $this->matrixAlgebra->inverseMatrix($bhmMatrix);

        return $inverseBhmMatrix;
    }

    public function buildBhmMatrix(array $covarianceMatrix, array $averageReturns)
    {
        $doubledCovarianceMatrix = $this->doubleCovarianceMatrix($covarianceMatrix);
        $withColumns = $this->appendOnesAndReturnsColumn($doubledCovarianceMatrix, $averageReturns);
        $withOnesRow = $this->appendOnesRow($withColumns);
        $bhmMatrix = $this->appendReturnsRow($withOnesRow, $averageReturns);

        return $bhmMatrix;
    }

    private function appendReturnsRow(array $withOnesRow, array $averageReturns)
    {
        $bhmMatrix = $withOnesRow;
        $averageReturnsRow = $averageReturns;
        $averageReturnsRow[] = 0;
        $averageReturnsRow[] = 0;
        $bhmMatrix[] = $averageReturnsRow;

        return $bhmMatrix;
    }

    private function appendOnesRow(array $withColumns)
    {
        $columnCount = count($withColumns);

        for($i=0; $i<$columnCount; $i++)
        {
            $onesRow[] = 1;
        }

        $onesRow[] = 0;
        $onesRow[] = 0;
        $withColumns[] = $onesRow;
        $withOnesRow = $withColumns;

        return $withOnesRow;
    }

    private function appendOnesAndReturnsColumn(array $doubledCovarianceMatrix, array $averageReturns)
    {
        $rowCount = count($averageReturns);

        for($i=0; $i<$rowCount; $i++)
        {
            array_push($doubledCovarianceMatrix[$i], "1", $averageReturns[$i]);
        }

        $withColumns = $doubledCovarianceMatrix;

        return $withColumns;
    }

    private function doubleCovarianceMatrix(array $covarianceMatrix)
    {
        $doubledCovarianceMatrix = array();
        $covarianceCellCount = count($covarianceMatrix);
        $doubledCovarianceRow = array();

        foreach($covarianceMatrix as $covarianceRow)
        {
            for($i=0; $i<$covarianceCellCount; $i++)
            {
                $doubledCovarianceRow[] = $covarianceRow[$i] * 2;
            }

            $doubledCovarianceMatrix[] = $doubledCovarianceRow;
            unset($doubledCovarianceRow);
        }

        return $doubledCovarianceMatrix;
    }

    public function buildCovarianceMatrix(array $returnsArrays)
    {
        $tickersCount = count($returnsArrays);
        $covariance = array();
        $covarianceMatrix = array();

        for($i=0; $i<$tickersCount; $i++)
        {
            for($j=0; $j<$tickersCount; $j++)
            {
                $covariance[] = $this->statistics->statsCovariance($returnsArrays[$i], $returnsArrays[$j]);
            }

            $covarianceMatrix[] = $covariance;
            unset($covariance);
        }

        return $covarianceMatrix;
    }

    public function buildAverageReturns(array $returnsArrays)
    {
        $averageReturns = array();

        foreach($returnsArrays as $returnsArray)
        {
            $observationsCount = count($returnsArray);
            $observationsSum = array_sum($returnsArray);
            $averageReturn = $observationsSum / $observationsCount;
            $averageReturns[] = $averageReturn;
        }

        return $averageReturns;
    }

    public function buildStandardDeviations(array $returnsArrays)
    {
        $standardDeviations = array();

        foreach($returnsArrays as $returnsArray)
        {
            $standardDeviation = $this->statistics->statsStandardDeviation($returnsArray);
            $standardDeviations[] = $standardDeviation;
        }

        return $standardDeviations;
    }

    public function buildReturnsArrays($limitedPriceArrays)
    {
        $this->validateLimitedPriceArrays($limitedPriceArrays);

        $returnsArrays = array();

        foreach($limitedPriceArrays as $priceArray)
        {
            $returnArray = $this->financialCalculations->calculateEqualWeightedMovingAverageReturns($priceArray);
            $returnsArrays[] = $returnArray;
        }

        return $returnsArrays;
    }

    private function validateLimitedPriceArrays($limitedPriceArrays)
    {
        $this->validateIsArray($limitedPriceArrays);
        $this->validateMultiArray($limitedPriceArrays);
        $this->validateNumericPrices($limitedPriceArrays);
        $this->validateSameArrayCount($limitedPriceArrays);
        $this->validateAtLeastTwoArraysExist($limitedPriceArrays);
    }

    private function validateIsArray($array)
    {
        if(!is_array($array))
        {
            throw new ApiException("Price data in Optimizer not an array");
        }
    }

    private function validateMultiArray($multiArray)
    {
        foreach($multiArray as $array)
        {
            if(!is_array($array))
            {
                throw new ApiException("Each price data in Optimizer not an array");
            }
        }
    }

    private function validateNumericPrices($limitedPriceArrays)
    {
        foreach($limitedPriceArrays as $array)
        {
            foreach($array as $data)
            {
                if(!is_numeric($data))
                {
                    throw new ApiException("Price data non-numeric in Optimizer");
                }
            }
        }
    }

    private function validateSameArrayCount($limitedPriceArrays)
    {
        $counts = array();
        foreach($limitedPriceArrays as $array)
        {
            $counts[] = count($array);
        }

        foreach($counts as $count)
        {
            $minCount = min($counts);
            if($count != $minCount)
            {
                throw new ApiException("Price data mismatch in Optimizer");
            }
        }
    }

    private function validateAtLeastTwoArraysExist($limitedPriceArrays)
    {
        if(count($limitedPriceArrays) < 2)
        {
            throw new ApiException("Price data in Optimizer is for only one ticker");
        }
    }
}
