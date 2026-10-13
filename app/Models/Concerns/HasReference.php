<?php

namespace App\Models\Concerns;

use App\Support\Reference;

trait HasReference
{
    public function getReferenceAttribute(): string
    {
        return Reference::format(class_basename($this), (int) $this->getKey());
    }
}
