<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = ['sale_date', 'user_id', 'sale_product', 'customer_id', 'total_price', 'total_payment', 'change', 'used_point'];

    public function detail_sales()
    {
        return $this->hasMany(Detail_sale::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}