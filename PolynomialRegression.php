<?php


namespace NewtonAnalytics\Apps\Math;

require_once(__DIR__ . "/ExceptionPolynomialRegression.php");

class PolynomialRegression
{
    public function polynomialRegression($data, $order)
    {
        $this->validateData($data);
        $this->validateOrder($order);

        $matrix = $this->matrix($data, $order);
        $columnVectorB = $this->columnVectorB($data, $order);
        $matrices = $this->matrices($matrix, $columnVectorB);
        $coefficients = $this->cramersRule($matrices);

        return $coefficients;       //prints highest order last
    }

    private function cramersRule(array $matrices)
    {
        $coefficients = null;
        $matrixCount = count($matrices);
        $M = $matrices[0];

        $detM = $this->determinant($M);

        for($i=1; $i<$matrixCount; $i++)
        {
            $Mx = $matrices[$i];
            $detMx = $this->determinant($Mx);

            $coefficient = $detMx / $detM;
            $coefficients[] = $coefficient;
        }

        return $coefficients;
    }

    private function matrices(array $matrix, array $columnVectorB)
    {
        $matrices[] = $matrix;
        $order = count($matrix);

        for($vectorPosition=0; $vectorPosition<$order; $vectorPosition++)
        {
            $Mx = $matrix;

            for($i=0; $i<$order; $i++)
            {
                $Mx[$i][$vectorPosition] = $columnVectorB[$i][0];
            }

            $matrices[] = $Mx;
            unset($Mx);
        }

        return $matrices;
    }

    private function columnVectorB(array $data, $order)
    {
        $vectorBFirstRow[] = $this->vectorBFirstRow($data);
        $vectorBRestOfRows = $this->vectorBRestOfRows($data, $order);
        $columnVectorB = array_merge($vectorBFirstRow, $vectorBRestOfRows);

        return $columnVectorB;
    }

    private function vectorBRestOfRows(array $data, $order)
    {
        $vectorBRestOfRows = null;
        $productValues = null;

        for($i=1; $i<$order+1; $i++)
        {
            foreach($data as $value)
            {
                $productValues[] = pow($value[0], $i) * $value[1];
            }

            $vectorBRestOfRows[] = [array_sum($productValues)];
            unset($productValues);
        }

        return $vectorBRestOfRows;
    }

    private function vectorBFirstRow(array $data)
    {
        $row = null;

        foreach($data as $value)
        {
            $row = $row + $value[1];
        }
        $vectorBFirstRow[] = $row;

        return $vectorBFirstRow;
    }

    private function matrix(array $data, $order)
    {
        $matrixFirstRow = $this->matrixFirstRow($data, $order);
        $matrixRestOfRows = $this->matrixRestOfRows($data, $order);
        $matrix = array_merge($matrixFirstRow, $matrixRestOfRows);

        return $matrix;
    }

    private function matrixRestOfRows(array $data, $order)
    {
        $matrixRestOfRows = null;
        $row = null;
        $rowStartOrder = 1;

        for($i=0; $i<$order; $i++)
        {
            for($j=$rowStartOrder; $j<$rowStartOrder+$order+1; $j++)
            {
                $row[] = $this->xSum($data, $j);
            }

            $matrixRestOfRows[] = $row;
            $rowStartOrder = $rowStartOrder+1;
            unset($row);
        }

        return $matrixRestOfRows;
    }

    private function matrixFirstRow(array $data, $order)
    {
        $matrixFirstRow = null;
        $N = count($data);
        $row[] = $N;

        for($i=1; $i<$order+1; $i++)
        {
            $row[] = $this->xSum($data, $i);
        }
        $matrixFirstRow[] = $row;

        return $matrixFirstRow;
    }

    private function xSum(array $data, $order)
    {
        $xSum = null;

        foreach($data as $value)
        {
            $raisedValue = pow($value[0], $order);
            $xSum = $xSum + $raisedValue;
        }

        return $xSum;
    }

    private function determinant(array $matrix)
    {
        $this->validateMatrix($matrix);

        $LU = $matrix;
        $pivots = [];
        $localColumnReferences = [];
        $matrixColCount = count($matrix);
        $matrixRowCount = count($matrix[0]);
        $pivotSign = 1;

        for($i=0; $i<$matrixColCount; $i++)
        {
            $pivots[$i] = $i;
        }

        for($j=0; $j<$matrixRowCount; $j++)
        {
            $p = $j;

            for($i=0; $i<$matrixColCount; $i++)
            {
                $localColumnReferences[$i] = &$LU[$i][$j];
            }

            for($i=0; $i<$matrixColCount; $i++)
            {
                $LUrowi = $LU[$i];
                $kmax = min($i, $j);
                $s = 0.0;

                for($k=0; $k<$kmax; $k++)
                {
                    $s = $s + $LUrowi[$k] * $localColumnReferences[$k];
                }

                $localColumnReferences[$i] = $localColumnReferences[$i] - $s;
                $LUrowi[$j] = $localColumnReferences[$i];
            }

            for($i=$j+1; $i<$matrixColCount; $i++)
            {
                if(abs($localColumnReferences[$i] ?? 0) > abs($localColumnReferences[$p] ?? 0))
                {
                    $p = $i;
                }
            }

            if($p != $j)
            {
                for($k=0; $k<$matrixRowCount; $k++)
                {
                    $t = $LU[$p][$k];
                    $LU[$p][$k] = $LU[$j][$k];
                    $LU[$j][$k] = $t;
                }

                $k = $pivots[$p];
                $pivots[$p] = $pivots[$j];
                $pivots[$j] = $k;
                $pivotSign = $pivotSign * -1;
            }


            if(($j < $matrixColCount) && ($LU[$j][$j] != 0.0))
            {
                for($i=$j+1; $i<$matrixColCount; $i++)
                {
                    $LU[$i][$j] = $LU[$i][$j] / $LU[$j][$j];
                }
            }
        }

        $determinant = $pivotSign;

        for($j=0; $j<$matrixRowCount; $j++)
        {
            $determinant = $determinant * $LU[$j][$j];
        }

        return $determinant;
    }

    private function validateMatrix($matrix)
    {
        $this->validateMultiArray($matrix);
        $this->validateNxNMatrix($matrix);
        $this->validateNumericMatrix($matrix);
    }

    private function validateNumericMatrix($matrix)
    {
        foreach($matrix as $row)
        {
            foreach($row as $value)
            {
                if(!is_numeric($value))
                {
                    throw new ExceptionPolynomialRegression("Matrix values non-numeric. \n");
                }
            }
        }
    }

    private function validateNxNMatrix($matrix)
    {
        $columnCount = count($matrix[0]);

        foreach($matrix as $row)
        {
            if(count($row) != $columnCount)
            {
                throw new ExceptionPolynomialRegression("Number of matrix columns don't match rows. \n");
            }
        }
    }

    private function validateMultiArray($matrix)
    {
        if(!is_array($matrix))
        {
            throw new ExceptionPolynomialRegression("Matrix is not an array. \n");
        }

        foreach($matrix as $array)
        {
            if(!is_array($array))
            {
                throw new ExceptionPolynomialRegression("Matrix is not a multidimensional array. \n");
            }
        }
    }

    private function validateOrder($order)
    {
        if($order > 7)
        {
            throw new ExceptionPolynomialRegression("order must be less than 8. \n");
        }

        if($order < 1)
        {
            throw new ExceptionPolynomialRegression("Order must be greater than 1. \n");
        }

        if(!$this->wholeNumeric($order))
        {
            throw new ExceptionPolynomialRegression("Order must be a whole number. \n");
        }
    }

    private function wholeNumeric($val)
    {
        if (is_numeric($val) && floor($val) == $val)
        {
            if ((string)$val === (string)0)
            {
                return true;
            }
            elseif(ltrim((string)$val, '0') === (string)$val)
            {
                return true;
            }
        }

        return false;
    }

    private function validateData($data)
    {
        $this->validateArrayAtLeastOneRow($data);
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
                    throw new ExceptionPolynomialRegression("Values not numeric. \n");
                }
            }
        }
    }

    private function validateArrayAtLeastOneRow($data)
    {
        if(!is_array($data))
        {
            throw new ExceptionPolynomialRegression("Data must be an array. \n");
        }

        if(!is_array($data[0]))
        {
            throw new ExceptionPolynomialRegression("Data must be a multidimensional array. \n");
        }
    }

    private function validateArrayTwoColumns($data)
    {
        foreach($data as $row)
        {
            $columnCount = count($row);

            if($columnCount != 2)
            {
                throw new ExceptionPolynomialRegression("All Data rows must have two points. \n");
            }
        }
    }
}
