<?php

namespace App\Exceptions;

class InvalidMovementException extends DomainException
{
    public function __construct(string $message = 'El movimiento no cumple las reglas de negocio.')
    {
        parent::__construct($message, 'invalid_movement', 422);
    }
}
