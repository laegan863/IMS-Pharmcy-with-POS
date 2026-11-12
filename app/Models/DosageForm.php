<?php

namespace App\Models;

use Database\Factories\DosageFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A dosage form such as Tablet, Capsule or Syrup.
 */
class DosageForm extends Lookup
{
    /** @use HasFactory<DosageFormFactory> */
    use HasFactory;
}
