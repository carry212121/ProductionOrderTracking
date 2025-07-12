<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function toggleStatus(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->Status = $request->status;
        $product->save();

        return back()->with('success', 'สถานะของสินค้าได้รับการอัปเดตแล้ว');
    }

}
