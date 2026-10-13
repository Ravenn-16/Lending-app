<?php
namespace App\Exceptions;
class LendingValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly string $field, string $message) { parent::__construct($message); }
}
