<?php

namespace App\Exceptions;

class CannotVoidException extends DomainException
{
    public function __construct(string $message = 'No se puede anular el movimiento porque dejaría fondos en negativo.')
    {
        parent::__construct($message, 'cannot_void', 422);
    }
}
