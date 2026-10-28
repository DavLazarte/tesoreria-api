<?php

namespace App\Enums;

enum MovementType: string
{
    case Ingreso = 'ingreso';
    case Egreso = 'egreso';
    case Asignacion = 'asignacion';
    case Devolucion = 'devolucion';
}
