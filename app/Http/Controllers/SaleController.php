<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use App\Exports\SalesExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use App\Models\Customers;
use App\Models\Detail_sale;
use App\Models\Product;
use App\Models\Sale;

class SaleController extends Controller
{
    //
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

    // Employee methods
    public function SaleIndex(Request $request)
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

        $sale = $query->latest()->get();
        $detail_sale = Detail_sale::with('product')->get();

        return view('employee.purchases.index', compact('sale', 'detail_sale', 'start_date', 'end_date'));
    }

    public function create()
    {
        $product = Product::where('stock', '>', 0)->get();
        return view('employee.purchases.create', compact('product'));
    }

    public function store(Request $request)
    {
        $products = $request->products;
        if (empty($products)) {
            return redirect()->back()->with('failed', 'Please choose product at least 1.');
        }

        $data['products'] = [];
        $data['total'] = 0;

        foreach ($products as $productStr) {
            $parts = explode(';', $productStr);
            $id = $parts[0];
            $name = $parts[1];
            $price = (float) str_replace(['Rp', '.', ','], '', $parts[2]);
            $quantity = (int) $parts[3];
            $subtotal = $price * $quantity;

            $data['products'][] = [
                'product_id' => $id,
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
                'sub_total' => $subtotal,
            ];
            $data['total'] += $subtotal;
        }

        return view('employee.purchases.payment', $data);
    }

    public function paymentProcess(Request $request)
    {
        $products = $request->shop;
        $sale_product = [];
        $total_pay = (int)str_replace(['Rp. ', '.'], '', $request->total_payment);
        $total = (int)str_replace(['Rp. ', '.'], '', $request->total);
        $customer_id = null;

        if ($request->customer == 'Member') {
            $phone = $request->phone;
            $member_name = $request->member_name;
            $existCustomer = Customers::where('phone', $phone)->first();

            if ($existCustomer) {
                $updateData = ['point' => $existCustomer->point + ($total / 100)];
                if ($member_name) {
                    $updateData['name'] = $member_name;
                }
                $existCustomer->update($updateData);
                $customer_id = $existCustomer->id;
            } else {
                $newCustomer = Customers::create([
                    'name' => $member_name ?? 'Member Baru',
                    'phone' => $phone,
                    'point' => 0, // Pembelian pertama tidak dapet poin
                ]);
                $customer_id = $newCustomer->id;
            }
        }

        $sale = Sale::create([
            'sale_date' => now(),
            'customer_id' => $customer_id,
            'total_price' => $total,
            'total_payment' => $total_pay,
            'change' => $total_pay - $total,
            'user_id' => Auth::user()->id,
            'sale_product' => '',
            'used_point' => 0
        ]);

        foreach ($products as $productStr) {
            $parts = explode(';', $productStr);
            $id = $parts[0];
            $name = $parts[1];
            $price = number_format($parts[2], 0, ',', '.');
            $quantity = (int)$parts[3];
            $subtotal = (int)$parts[4];

            $sale_product[] = "{$name} ( {$quantity} : Rp. {$price} )";

            $productModel = Product::find($id);
            if ($productModel) {
                $productModel->decrement('stock', $quantity);
            }

            Detail_sale::create([
                'sale_id' => $sale->id,
                'product_id' => $id,
                'quantity' => $quantity,
                'sub_total' => $subtotal,
            ]);
        }

        $sale->update(['sale_product' => implode(' , ', $sale_product)]);

        if ($request->customer == 'Member') {
            return redirect()->route('employee.EditMember', $sale->id);
        }

        return redirect()->route('employee.DetPrint', $sale->id)->with('success', 'Transaksi Berhasil!');
    }

    public function EditMember($id)
    {
        $sale = Sale::with(['customer', 'user'])->findOrFail($id);
        return view('employee.purchases.member', compact('sale'));
    }

    public function member(Request $request, $id)
    {
        $customer = Customers::findOrFail($request->customer_id);
        $customer->update(['name' => $request->name]);

        $sale = Sale::findOrFail($id);

        if ($request->check_point == 'Ya') {
            $used_point = $customer->point;
            $customer->update(['point' => 0]);

            $sale->used_point = $used_point;
            $sale->total_price -= $used_point;
            $sale->change = $sale->total_payment - $sale->total_price;
        }

        $sale->save();

        return redirect()->route('employee.DetPrint', $sale->id)->with('success', 'Data Member Berhasil Diperbarui!');
    }

    public function print($id)
    {
        $sale = Sale::with(['customer', 'user'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        return view('employee.purchases.print', compact('sale', 'detail_sale'));
    }

    public function exportPDF($id)
    {
        $sale = Sale::with(['customer', 'user'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        $data = ['sale' => $sale, 'detail_sale' => $detail_sale];
        $pdf = Pdf::loadView('employee.purchases.exportpdf', $data);
        return $pdf->download('receipt.pdf');
    }

    public function Excel(Request $request)
    {
        return Excel::download(new SalesExport($request->filter), 'sale_export.xlsx');
    }
}
