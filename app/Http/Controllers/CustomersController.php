<?php

namespace App\Http\Controllers;

use App\Models\Customers;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    //
    public function create()
    {
        return view('employee.purchases.member');
    }

    public function getByPhone($phone)
    {
        $customer = Customers::where('phone', $phone)->first();
        
        if ($customer) {
            return response()->json([
                'success' => true,
                'data' => $customer
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Customer not found'
        ], 404);
    }
}