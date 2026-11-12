<?php

namespace App\Models;

use Database\Factories\GenericNameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * An active ingredient name such as Paracetamol.
 */
class GenericName extends Lookup
{
    /** @use HasFactory<GenericNameFactory> */
    use HasFactory;
}
