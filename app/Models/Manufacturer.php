<?php

namespace App\Models;

use Database\Factories\ManufacturerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A drug manufacturer or brand owner.
 */
class Manufacturer extends Lookup
{
    /** @use HasFactory<ManufacturerFactory> */
    use HasFactory;
}
