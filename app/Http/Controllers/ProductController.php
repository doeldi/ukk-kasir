<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;

class ProductController extends Controller
{
    // Admin methods
    public function index()
    {
        $products = Product::all();
        return view('admin.product.index', compact('products'));
    }

    public function create()
    {
        return view('admin.product.create');
    }

    public function store(Request $request)
    {
        $removeRP = str_replace(['RP. ', '.'], '', $request->price);
        $request->merge(['price' => $removeRP]);

        $request->validate([
            'name' => 'required|min:3',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif',
            'price' => 'required|numeric|min:1',
            'stock' => 'required|numeric|min:1'
        ]);

        Product::create([
            'name' => $request->name,
            'image' => $request->hasFile('image') ? $request->file('image')->store('product-images', 'public') : null,
            'price' => $request->price,
            'stock' => $request->stock
        ]);

        return redirect()->route('admin.ProductHome')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return view('admin.product.edit', compact('product'));
    }

    public function update(Request $request, $id)
    {
        $removeRP = str_replace(['RP. ', '.'], '', $request->price);
        $request->merge(['price' => $removeRP]);

        $request->validate([
            'name' => 'required|min:3',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:5120',
            'price' => 'required|numeric|min:1',
        ]);

        $product = Product::findOrFail($id);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('product-images', 'public');
        }

        $product->name = $request->name;
        $product->price = $request->price;
        $product->save();

        return redirect()->route('admin.ProductHome')->with('success', 'Produk berhasil di edit!');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        if ($product->detail_sales()->exists()) {
            return redirect()->back()->with('failed', 'Produk sudah terdaftar saat pembelian!');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();
        return redirect()->route('admin.ProductHome')->with('success', 'Product berhasil dihapus!');
    }

    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'stock' => 'required|integer|min:0'
        ]);

        $product = Product::findOrFail($id);
        $product->update(['stock' => $request->stock]);

        return redirect()->route('admin.ProductHome')->with('success', 'Stok berhasil diupdate!');
    }
}
