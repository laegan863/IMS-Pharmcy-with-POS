<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the first sheet of an upload as rows keyed by the heading row
 * (headings are snake_cased, so "Generic Name" becomes generic_name).
 */
class HeadingRowSheet implements Import, WithHeadingRow {}
