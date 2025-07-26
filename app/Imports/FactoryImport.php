<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class FactoryImport implements ToCollection
{
    public Collection $factories;

    public function collection(Collection $rows)
    {
        $this->factories = $rows->skip(1); // skip header row
    }
}

