<?php


namespace NewtonAnalytics\Apps\Math;

require_once(__DIR__ . "/ExceptionMatrixAlgebra.php");

class MatrixAlgebra
{
    public function matrixMultiplication(array $matrix1, array $matrix2)
    {
        $matrix1RowCount = count($matrix1);
        $matrix1ColumnCount = count($matrix1[0]);
        $matrix2ColumnCount = count($matrix2[0]);
        $matrix2RowCount = count($matrix2);
        $productMatrix = array();

        $this->isTwoLevelMultidimensionalArray($matrix1);
        $this->isTwoLevelMultidimensionalArray($matrix2);
        $this->validatorMatrixMultiplication($matrix1ColumnCount, $matrix2RowCount);

        for($i=0; $i<$matrix1RowCount; $i++)
        {
            for($j=0; $j<$matrix2ColumnCount; $j++)
            {
                $productMatrix[$i][$j] = 0;

                for($k=0; $k<$matrix2RowCount; $k++)
                {
                    $productMatrix[$i][$j] += $matrix1[$i][$k] * $matrix2[$k][$j];
                }
            }
        }

        return $productMatrix;
    }

    protected function validatorMatrixMultiplication($matrix1ColumnCount, $matrix2RowCount)
    {
        if($matrix1ColumnCount != $matrix2RowCount)
        {
            throw new ExceptionMatrixAlgebra("Incompatible matrices multiplication. \n");
        }
    }

    public function matrixTranspose(array $matrix)
    {
        $this->isTwoLevelMultidimensionalArray($matrix);

        $matrixRowCount = count($matrix);
        $matrixColumnCount = count($matrix[0]);
        $matrixTransposed = array();

        for($i=0; $i<$matrixRowCount; $i++)
        {
            for($j=0; $j<$matrixColumnCount; $j++)
            {
                $matrixTransposed[$j][$i] = $matrix[$i][$j];
            }
        }

        return $matrixTransposed;
    }

    //Gauss-Jordan elimination method for matrix inverse
    public function inverseMatrix(array $matrix)
    {
        $this->isTwoLevelMultidimensionalArray($matrix);
        $this->validateMatrixRowsEqualColumns($matrix);

        $matrixCount = count($matrix);

        $identityMatrix = $this->identityMatrix($matrixCount);
        $augmentedMatrix = $this->appendIdentityMatrixToMatrix($matrix, $identityMatrix);
        $inverseMatrixWithIdentity = $this->createInverseMatrix($augmentedMatrix);
        $inverseMatrix = $this->removeIdentityMatrix($inverseMatrixWithIdentity);

        return $inverseMatrix;
    }

    private function createInverseMatrix(array $matrix)
    {
        $numberOfRows = count($matrix);

        for($i=0; $i<$numberOfRows; $i++)
        {
            $matrix = $this->oneOperation($matrix, $i, $i);

            for($j=0; $j<$numberOfRows; $j++)
            {
                if($i !== $j)
                {
                    $matrix = $this->zeroOperation($matrix, $j, $i, $i);
                }
            }
        }
        $inverseMatrixWithIdentity = $matrix;

        return $inverseMatrixWithIdentity;
    }

    private function oneOperation(array $matrix, $rowPosition, $zeroPosition)
    {
        if($matrix[$rowPosition][$zeroPosition] !== 1)
        {
            $numberOfCols = count($matrix[$rowPosition]);

            if($matrix[$rowPosition][$zeroPosition] === 0)
            {
                $divisor = 0.0000000001;
                $matrix[$rowPosition][$zeroPosition] = 0.0000000001;
            }
            else
            {
                $divisor = $matrix[$rowPosition][$zeroPosition];
            }

            for($i=0; $i<$numberOfCols; $i++)
            {
                $matrix[$rowPosition][$i] = $matrix[$rowPosition][$i] / $divisor;
            }
        }

        return $matrix;
    }

    private function zeroOperation(array $matrix, $rowPosition, $zeroPosition, $subjectRow)
    {
        $numberOfCols = count($matrix[$rowPosition]);

        if($matrix[$rowPosition][$zeroPosition] !== 0)
        {
            $numberToSubtract = $matrix[$rowPosition][$zeroPosition];

            for($i=0; $i<$numberOfCols; $i++)
            {
                $matrix[$rowPosition][$i] = $matrix[$rowPosition][$i] - $numberToSubtract * $matrix[$subjectRow][$i];
            }
        }

        return $matrix;
    }

    //"Augmented Matrix" method using "Elementary Row Operations"
    public function inverseMatrixBackForthMethod(array $matrix)
    {
        $this->isTwoLevelMultidimensionalArray($matrix);
        $this->validateMatrixRowsEqualColumns($matrix);

        $matrixCount = count($matrix);
        $identityMatrix = $this->identityMatrix($matrixCount);
        $augmentedMatrix = $this->appendIdentityMatrixToMatrix($matrix, $identityMatrix);
        $forwardRunMatrix = $this->inverseMatrixForwardRun($augmentedMatrix);
        $reverseRunMatrix = $this->inverseMatrixReverseRun($forwardRunMatrix);
        $lastRunMatrix = $this->inverseMatrixLastRun($reverseRunMatrix);
        $inverseMatrix = $this->removeIdentityMatrix($lastRunMatrix);

        return $inverseMatrix;
    }

    private function removeIdentityMatrix(array $matrix)
    {
        $inverseMatrix = array();
        $matrixCount = count($matrix);

        for($i=0; $i<$matrixCount; $i++)
        {
            $inverseMatrix[$i] = array_slice($matrix[$i], $matrixCount);
        }

        return $inverseMatrix;
    }

    private function inverseMatrixLastRun(array $reverseRunMatrix)
    {
        $reverseRunMatrixCount = count($reverseRunMatrix);

        for($i=0; $i<$reverseRunMatrixCount; $i++)
        {
            if($reverseRunMatrix[$i][$i] !== 1)
            {
                $scalar = 1 / $reverseRunMatrix[$i][$i];
                for($j=$i; $j<$reverseRunMatrixCount*2; $j++)
                {
                    $reverseRunMatrix[$i][$j] *= $scalar;
                }
            }
        }
        $lastRunMatrix = $reverseRunMatrix;

        return $lastRunMatrix;
    }

    private function inverseMatrixReverseRun(array $forwardRunMatrix)
    {
        $forwardRunMatrixCount = count($forwardRunMatrix);

        for($i=$forwardRunMatrixCount-1; $i>0; $i--)
        {
            for($j=$i-1; $j>=0; $j--)
            {
                if($forwardRunMatrix[$j][$i] !== 0)
                {
                    $scalar = $forwardRunMatrix[$i][$i] / $forwardRunMatrix[$j][$i];

                    for($k=$j; $k<$forwardRunMatrixCount*2; $k++)
                    {
                        $forwardRunMatrix[$j][$k] *= $scalar;
                        $forwardRunMatrix[$j][$k] -= $forwardRunMatrix[$i][$k];
                    }
                }
            }
        }
        $reverseRunMatrix = $forwardRunMatrix;

        return $reverseRunMatrix;
    }

    private function inverseMatrixForwardRun(array $augmentedMatrix)
    {
        $augmentedMatrixCount = count($augmentedMatrix);

        for($i=0; $i<$augmentedMatrixCount; $i++)
        {
            for($j=$i+1; $j<$augmentedMatrixCount; $j++)
            {
                if($augmentedMatrix[$j][$i] !== 0)
                {
                    $scalar = $augmentedMatrix[$i][$i] / $augmentedMatrix[$j][$i];

                    for($k=$i; $k<$augmentedMatrixCount*2; $k++)
                    {
                        $augmentedMatrix[$j][$k] *= $scalar;
                        $augmentedMatrix[$j][$k] -= $augmentedMatrix[$i][$k];
                    }
                }
            }
        }
        $forwardRunMatrix = $augmentedMatrix;

        return $forwardRunMatrix;
    }

    private function appendIdentityMatrixToMatrix(array $matrix, array $identityMatrix)
    {
        $augmentedMatrix = array();

        $this->validateTwoMatricesHaveSameNumberOfColumns($matrix, $identityMatrix);
        $this->validateTwoMatricesHaveSameNumberOfRows($matrix, $identityMatrix);

        for($i=0; $i<count($matrix); $i++)
        {
            $augmentedMatrix[$i] = array_merge($matrix[$i], $identityMatrix[$i]);
        }

        return $augmentedMatrix;
    }

    private function validateTwoMatricesHaveSameNumberOfRows(array $matrix1, array $matrix2)
    {
        $matrix1RowCount = count($matrix1);
        $matrix2RowCount = count($matrix2);

        if($matrix1RowCount != $matrix2RowCount)
        {
            throw new ExceptionMatrixAlgebra("Two matrices do not have the same number of rows. \n");
        }
    }

    private function validateTwoMatricesHaveSameNumberOfColumns(array $matrix1, array $matrix2)
    {
        $this->validateEachRowInMatrixHasSameNumberOfColumns($matrix1);
        $this->validateEachRowInMatrixHasSameNumberOfColumns($matrix2);

        $matrix1ColumnCount = count($matrix1[0]);
        $matrix2ColumnCount = count($matrix2[0]);

        if($matrix1ColumnCount != $matrix2ColumnCount)
        {
            throw new ExceptionMatrixAlgebra("Two matrices do not have the same number of columns. \n");
        }
    }

    private function validateMatrixRowsEqualColumns(array $matrix)
    {
        $this->validateEachRowInMatrixHasSameNumberOfColumns($matrix);

        $rowCount = count($matrix);
        $columnCount = count($matrix[0]);

        if($rowCount != $columnCount)
        {
            throw new ExceptionMatrixAlgebra("The matrix number of columns and rows must be the same. \n");
        }
    }

    private function validateEachRowInMatrixHasSameNumberOfColumns(array $matrix)
    {
        $columnCount = count($matrix[0]);

        foreach($matrix as $columns)
        {
            if(count($columns) != $columnCount)
            {
                throw new ExceptionMatrixAlgebra("Inconsistent number of columns in each row. \n");
            }
        }
    }

    public function identityMatrix(int $size)
    {
        $identityMatrix = array();

        for($i=0; $i<$size; $i++)
        {
            for($j=0; $j<$size; $j++)
            {
                if($i == $j)
                {
                    $identityMatrix[$i][$j] = 1;
                }
                else
                {
                    $identityMatrix[$i][$j] = 0;
                }
            }
        }

        return $identityMatrix;
    }

    protected function isTwoLevelMultidimensionalArray(array $arrays)
    {
        foreach($arrays as $array)
        {
            if(!is_array($array))
            {
                throw new ExceptionMatrixAlgebra("Must be an array within an array. \n");
            }

            foreach($array as $possiblyAnotherArray)
            {
                if(is_array($possiblyAnotherArray))
                {
                    throw new ExceptionMatrixAlgebra("Must not be 3 or more level array. \n");
                }
            }
        }
    }
}
