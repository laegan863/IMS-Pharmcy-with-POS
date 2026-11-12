<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A product category such as Analgesics or Vitamins.
 */
class Category extends Lookup
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;
}
