<?php

namespace App\Services;

use App\Enums\CategoryFlow;
use App\Enums\FundKind;
use App\Enums\MovementType;
use App\Exceptions\CannotVoidException;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InvalidMovementException;
use App\Models\Category;
use App\Models\Entity;
use App\Models\Fund;
use App\Models\Movement;
use App\Models\MovementVoid;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Devuelve la Caja General única de la tesorería.
     */
    public function getGeneralFund(): Fund
    {
        return Fund::where('kind', FundKind::General)->firstOrFail();
    }

    /**
     * Devuelve el saldo actual de una caja por ID (Ingresos no anulados - Egresos no anulados).
     */
    public function getFundBalance(int $fundId): string
    {
        $incoming = Movement::query()
            ->where('to_fund_id', $fundId)
            ->whereDoesntHave('void')
            ->sum('amount');

        $outgoing = Movement::query()
            ->where('from_fund_id', $fundId)
            ->whereDoesntHave('void')
            ->sum('amount');

        $incomingStr = number_format((float) $incoming, 2, '.', '');
        $outgoingStr = number_format((float) $outgoing, 2, '.', '');

        return bcsub($incomingStr, $outgoingStr, 2);
    }

    /**
     * Devuelve el saldo actual de la Caja General.
     */
    public function getGeneralBalance(): string
    {
        $general = $this->getGeneralFund();

        return $this->getFundBalance($general->id);
    }

    /**
     * Registra un nuevo movimiento de fondos (ingreso o egreso).
     *
     * @param array{
     *     type: MovementType|string,
     *     amount: string|float|int,
     *     movement_date: string,
     *     concept: string,
     *     category_id: int,
     *     entity_id?: int|null,
     *     reference?: string|null,
     *     note?: string|null
     * } $data
     */
    public function recordMovement(array $data, User $user): Movement
    {
        return DB::transaction(function () use ($data, $user) {
            $type = $data['type'] instanceof MovementType ? $data['type'] : MovementType::from($data['type']);
            $amount = $this->normalizeAmount($data['amount'] ?? null);
            $entityId = $data['entity_id'] ?? null;
            $categoryId = $data['category_id'] ?? null;

            $generalFund = Fund::query()
                ->where('kind', FundKind::General)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$generalFund->is_active) {
                throw new InvalidMovementException('La Caja General no está activa.');
            }

            // Validar entidad si fue informada
            if ($entityId !== null) {
                $entity = Entity::find($entityId);
                if (!$entity) {
                    throw new InvalidMovementException('La entidad especificada no existe.');
                }
                if (!$entity->is_active) {
                    throw new InvalidMovementException("La entidad '{$entity->name}' se encuentra inactiva.");
                }
            }

            // Validar categoría (obligatoria en todo ingreso y egreso)
            if ($categoryId === null) {
                throw new InvalidMovementException('El movimiento exige una categoría.');
            }

            $fromFundId = null;
            $toFundId = null;

            if ($type === MovementType::Ingreso) {
                $this->validateCategoryFlow($categoryId, CategoryFlow::Ingreso);
                $toFundId = $generalFund->id;
            } elseif ($type === MovementType::Egreso) {
                $this->validateCategoryFlow($categoryId, CategoryFlow::Egreso);
                $fromFundId = $generalFund->id;

                // Verificar saldo disponible en la Caja General
                $currentBalance = $this->getGeneralBalance();
                if (bccomp($currentBalance, $amount, 2) < 0) {
                    throw new InsufficientFundsException(
                        "Saldo insuficiente en la Caja General. Disponible: \${$currentBalance}, Solicitado: \${$amount}."
                    );
                }
            } else {
                throw new InvalidMovementException("El tipo de movimiento '{$type->value}' no está habilitado en esta etapa.");
            }

            $movement = Movement::create([
                'type' => $type,
                'movement_date' => $data['movement_date'],
                'from_fund_id' => $fromFundId,
                'to_fund_id' => $toFundId,
                'entity_id' => $entityId,
                'category_id' => $categoryId,
                'amount' => $amount,
                'concept' => $data['concept'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->auditService->log(
                event: 'movement.created',
                model: $movement,
                old: null,
                new: $movement->toArray(),
                userId: $user->id
            );

            return $movement;
        });
    }

    /**
     * Anula un movimiento de fondos existente.
     */
    public function voidMovement(Movement $movement, string $reason, User $user): MovementVoid
    {
        return DB::transaction(function () use ($movement, $reason, $user) {
            if ($movement->void()->exists()) {
                throw new CannotVoidException('El movimiento ya se encuentra anulado.');
            }

            if (empty(trim($reason))) {
                throw new CannotVoidException('El motivo de anulación es obligatorio.');
            }

            // Bloquear la Caja General para el recálculo atómico
            Fund::query()->where('kind', FundKind::General)->lockForUpdate()->firstOrFail();

            // Si se anula un INGRESO, restará ese dinero de la caja: verificar que no deje saldo negativo
            if ($movement->type === MovementType::Ingreso) {
                $currentBalance = $this->getGeneralBalance();
                if (bccomp($currentBalance, $movement->amount, 2) < 0) {
                    throw new CannotVoidException(
                        'No se puede anular el ingreso porque dejaría el saldo de la Caja General en negativo.'
                    );
                }
            }

            $movementVoid = MovementVoid::create([
                'movement_id' => $movement->id,
                'voided_by' => $user->id,
                'reason' => trim($reason),
                'voided_at' => now(),
            ]);

            $this->auditService->log(
                event: 'movement.voided',
                model: $movement,
                old: null,
                new: [
                    'voided_by' => $user->id,
                    'reason' => $reason,
                    'voided_at' => (string) $movementVoid->voided_at,
                ],
                userId: $user->id
            );

            return $movementVoid;
        });
    }

    /**
     * Valida que la categoría coincida con el flujo (ingreso o egreso).
     */
    protected function validateCategoryFlow(int $categoryId, CategoryFlow $expectedFlow): void
    {
        $category = Category::find($categoryId);
        if (!$category) {
            throw new InvalidMovementException('La categoría especificada no existe.');
        }
        if (!$category->is_active) {
            throw new InvalidMovementException("La categoría '{$category->name}' no está activa.");
        }
        if ($category->flow !== $expectedFlow) {
            throw new InvalidMovementException(
                "La categoría '{$category->name}' no corresponde a un flujo de tipo {$expectedFlow->value}."
            );
        }
    }

    /**
     * Valida y normaliza el monto a formato decimal estricto de 2 decimales (string).
     */
    protected function normalizeAmount(mixed $amount): string
    {
        if ($amount === null || !is_numeric($amount)) {
            throw new InvalidMovementException('El monto debe ser un valor numérico válido.');
        }

        $amountStr = (string) $amount;
        if (str_contains($amountStr, '.')) {
            $decimals = strlen(substr(strrchr($amountStr, '.'), 1));
            if ($decimals > 2) {
                throw new InvalidMovementException('El monto no puede tener más de 2 decimales.');
            }
        }

        $formatted = number_format((float) $amount, 2, '.', '');
        if (bccomp($formatted, '0.00', 2) <= 0) {
            throw new InvalidMovementException('El monto debe ser mayor a cero.');
        }

        return $formatted;
    }
}
