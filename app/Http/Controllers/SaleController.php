<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\Detail_sale;
use App\Models\Sale;

class SaleController extends Controller
{
    // Admin methods
    public function adminIndex(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $query = Sale::with(['customer', 'user']);

        if ($start_date && $end_date) {
            $query->whereBetween('created_at', [
                Carbon::parse($start_date)->startOfDay(),
                Carbon::parse($end_date)->endOfDay()
            ]);
        }

        $sales = $query->latest()->get();
        $detail_sales = Detail_sale::with(['product'])->get();
        return view('admin.purchases.index', compact('sales', 'detail_sales', 'start_date', 'end_date'));
    }

    public function exportPDFAd($id)
    {
        $sale = Sale::with(['customer', 'user', 'detail_sales.product'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        $data = ['sale' => $sale, 'detail_sale' => $detail_sale];
        $pdf = Pdf::loadView('admin.purchases.exportpdf', $data);
        return $pdf->download('receipt.pdf');
    }
}
