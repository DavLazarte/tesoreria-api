<?php

namespace App\Exceptions;

class InsufficientFundsException extends DomainException
{
    public function __construct(string $message = 'Saldo insuficiente en la caja de origen.')
    {
        parent::__construct($message, 'insufficient_funds', 422);
    }
}
