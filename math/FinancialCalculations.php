<?php

namespace NewtonAnalytics\Apps\Math;


class FinancialCalculations
{
    private $statistics;

    public function __construct(Statistics $statistics)
    {
        $this->statistics = $statistics;
    }

    public function portfolioAverageReturn(array $weights, array $averageReturns)
    {
        $weightedReturns = array();
        $count = count($weights);

        for($i=0; $i<$count; $i++)
        {
            $weightedReturns[] = $weights[$i] * $averageReturns[$i];
        }

        $portfolioAverageReturn = array_sum($weightedReturns);

        return $portfolioAverageReturn;
    }

    public function portfolioStandardDeviation(
        MatrixAlgebra $matrixAlgebra,
        array $covarianceMatrix,
        array $weights,
        array $standardDeviations)
    {
        $correlationMatrix = $this->statistics->deriveCorrelationMatrix($covarianceMatrix, $standardDeviations);
        $weightedStandardDeviations[] = $this->weightedStandardDeviations($weights, $standardDeviations);
        $transposedWeightedStandDevs = $matrixAlgebra->matrixTranspose($weightedStandardDeviations);
        $m1 = $matrixAlgebra->matrixMultiplication($weightedStandardDeviations, $correlationMatrix);
        $m2 = $matrixAlgebra->matrixMultiplication($m1, $transposedWeightedStandDevs);
        $portfolioStandardDeviation = sqrt($m2[0][0]);

        return $portfolioStandardDeviation;
    }

    private function weightedStandardDeviations(array $weights, array $standardDeviations)
    {
        $count = count($weights);
        $weightedStandardDeviations = array();

        for($i=0; $i<$count; $i++)
        {
            $weightedStandardDeviations[] = $weights[$i] * $standardDeviations[$i];
        }

        return $weightedStandardDeviations;
    }

    public function betaCalculation(array $tickerReturnData, array $indexReturnData)
    {
        $covariance = $this->statistics->statsCovariance($tickerReturnData, $indexReturnData);
        $variance = $this->statistics->statsVariance($indexReturnData);

        $beta = $covariance / $variance;

        return $beta;
    }

    public function calculateEqualWeightedMovingAverageReturns(array $priceData)
    {
        $returnData = array();

        for($i=0; $i<count($priceData)-1; $i++)
        {
            $m = $i+1;
            $returnData[] = $priceData[$i] / $priceData[$m] - 1;
        }

        return $returnData;
    }
}
