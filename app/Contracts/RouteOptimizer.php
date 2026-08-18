<?php

namespace App\Contracts;

use App\Data\RouteOptimizationInput;
use App\Data\RouteOptimizationResult;

interface RouteOptimizer
{
    public function optimize(RouteOptimizationInput $input): RouteOptimizationResult;
}
