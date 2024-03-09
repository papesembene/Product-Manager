<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    /**
     * Instantiate a new ProductController instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:create-product|edit-product|delete-product', ['only' => ['index','show']]);
        $this->middleware('permission:create-product', ['only' => ['create','store']]);
        $this->middleware('permission:edit-product', ['only' => ['edit','update']]);
        $this->middleware('permission:delete-product', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::latest()->paginate(3)
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('products.create',[
            'category'=>Category::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = new Product();
        $filename = time() .'.' . $request->image->extension();
        $request->image->storeAs('public/images',$filename);
        Product::create([
            'name'=>$request->input('name'),
            'price'=>$request->input('price'),
            'quantity'=>$request->input('quantity'),
            'description'=>$request->input('description'),
            'image'=>$filename,
            'category_id'=>$request->input('category_id'),
        ]);
        //$product->image = $filename;
        return redirect()->route('products.index')
            ->with('store','New product is added successfully.');
    }
    public  function imageurl():string
    {
        return  Storage::disk('public/images/products')->url($this->image);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): View
    {
        return view('products.show', [
            'product' => $product
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {

        $product->update($request->all());

        return redirect()->route('products.index')
            ->with('update','Product is updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    /*public function destroy(Product $product): RedirectResponse
    {
        if ($product->image !== null){
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();
        return redirect()->route('products.index')
            ->with('delete','Product is deleted successfully.');
    }*/
    public function destroy(Product $product): RedirectResponse
    {
        // Vérifier si le produit est associé à une commande
        if ($product->Orders()->exists()) {
            return redirect()->route('products.index')
                ->with('error', 'can not delete this commande');
        }

        // Supprimer l'image du produit s'il en a une
        if ($product->image !== null){
            Storage::disk('public')->delete($product->image);
        }

        // Supprimer le produit lui-même de la base de données
        $product->delete();

        return redirect()->route('products.index')
            ->with('delete', 'Product is deleted successfully');
    }

}
