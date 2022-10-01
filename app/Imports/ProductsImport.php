<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToModel, WithBatchInserts, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $store = auth('store_api')->user();
      
        $category = Category::where(strtolower("name"), strtolower($row['category']))->first();
        $brand = Brand::where(strtolower("name"), strtolower($row['brand']))->first();

        !is_null($category) ? $categoryId = $category->id : $categoryId = 1;
        !is_null($brand) ? $brandId = $brand->id : $brandId = 1;

        if (is_null($brand)) {
        }
        return new Product([

            'product_name' => $row['name'],
            'product_desc'  => $row['description'],
            'price'  => $row['price'],
            'sales_price'  => $row['sales_price'],
            'manufacture_date'  => $row['manufacture_date'],
            'expiry_date'  => $row['expiry_date'],
            'product_no' => rand(000000, 999999),
            'batch_no' => rand(000000, 999999),
            'in_stock'  => $row['in_stock'],
            'category_id'  => $categoryId,
            'brand_id'  => $brandId,
            'store_id' => $store->id,
            'image'  => [],
            'weight'  => $row['weight'],

        ]);
    }

    public function batchSize(): int
    {
        return 100;
    }
}
