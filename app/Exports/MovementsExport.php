<?php

namespace App\Exports;

use App\Models\Movement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MovementsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    /**
     * @param Collection<int, Movement> $movements
     */
    public function __construct(
        protected Collection $movements
    ) {}

    public function collection(): Collection
    {
        return $this->movements;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Tipo',
            'Categoría',
            'Entidad / Destinatario',
            'Concepto',
            'Referencia',
            'Monto ($)',
            'Estado',
            'Cargado por',
        ];
    }

    /**
     * @param Movement $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->movement_date?->format('d/m/Y') ?? (string) $row->movement_date,
            ucfirst($row->type->value),
            $row->category?->name ?? '—',
            $row->entity?->name ?? '—',
            $row->concept,
            $row->reference ?? '—',
            $row->amount,
            $row->isVoided() ? 'ANULADO' : 'ACTIVO',
            $row->creator?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']]],
        ];
    }
}
