<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class SalesExport implements FromCollection, WithHeadings
{
    protected $filter;

    public function __construct($filter = null)
    {
        $this->filter = $filter;
    }

    public function collection()
    {
        $query = Sale::with(['customer', 'user']);

        if ($this->filter === 'daily') {
            $query->whereDate('sale_date', Carbon::today());
        } elseif ($this->filter === 'weekly') {
            $query->whereBetween('sale_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($this->filter === 'monthly') {
            $query->whereMonth('sale_date', Carbon::now()->month);
        } elseif ($this->filter === 'yearly') {
            $query->whereYear('sale_date', Carbon::now()->year);
        }

        return $query->get()->map(function($sale) {
            return [
                'ID' => '#TRX-' . $sale->id,
                'Tanggal' => $sale->created_at->format('d M Y H:i'),
                'Customer' => $sale->customer->name ?? 'NON-MEMBER',
                'Produk' => $sale->sale_product,
                'Total Harga' => $sale->total_price,
                'Potongan Poin' => $sale->used_point,
                'Total Bayar' => $sale->total_payment,
                'Kembalian' => $sale->change,
                'Kasir' => $sale->user->name ?? $sale->user->email ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Tanggal & Waktu',
            'Customer',
            'Detail Produk (Qty : Harga)',
            'Total Harga (Rp)',
            'Potongan Poin (Rp)',
            'Total Bayar (Rp)',
            'Kembalian (Rp)',
            'Petugas Kasir',
        ];
    }
}