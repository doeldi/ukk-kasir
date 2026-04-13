<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    //
    public function loginAuth(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            if ($user->role == 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role == 'employee') {
                return redirect()->route('employee.dashboard');
            }
        }

        return redirect()->back()->with('error', 'Email atau password salah.');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('landingpage');
    }

    public function dashboardAdmin()
    {
        $products = Product::all();
        $sales = Sale::all();
        return view('admin.dashboard.index', compact('products', 'sales'));
    }

    public function dashboardEmployee()
    {
        return view('employee.dashboard.index');
    }

    public function index()
    {
        $users = User::all();
        return view('admin.user.index', compact('users'));
    }

    public function create()
    {
        return view('admin.user.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,employee'
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role
        ]);

        return redirect()->route('admin.UserHome')->with('success', 'User berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.user.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin,employee'
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.UserHome')->with('success', 'User berhasil diupdate!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admin.UserHome')->with('success', 'User berhasil dihapus!');
    }
}
