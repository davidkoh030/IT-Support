<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['impact', 'urgency', 'priority'])]
class PriorityMatrixRule extends Model
{
    //
}
