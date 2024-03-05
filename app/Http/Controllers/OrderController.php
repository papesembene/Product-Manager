<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderDetailRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use function Laravel\Prompts\alert;


class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('orders.index', [
                  'orders' => Order::latest()->paginate(5),
               ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //validation  data
        $validatedData = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_date' => 'required|date',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.order_quantity' => 'required|integer|min:1',
        ]);

        // Vérifier la disponibilité en stock pour chaque produit demandé
        foreach ($validatedData['products'] as $productData) {
            $product = Product::find($productData['product_id']);
            if ($product->quantity < $productData['order_quantity'] || $product->quantity == 0 ) {
                return redirect()->back()->withError('La quantité demandée n\'est pas disponible en stock pour le produit '.$product->name);
            }
        }

        // Créer la commande
        $order = Order::create([
            'customer_id' => $validatedData['customer_id'],
            'order_num' => "COM" . rand(100, 1000),
            'order_date' => $validatedData['order_date'],
        ]);

        // Créer les détails de la commande pour chaque produit
        foreach ($validatedData['products'] as $productData) {
            $order->Order_details()->create([
                'order_quantity' => $productData['order_quantity'],
                'product_id' => $productData['product_id'],
            ]);

            // Mettre à jour la quantité en stock du produit
            $product = Product::find($productData['product_id']);
            $product->quantity -= $productData['order_quantity'];
            if ($product->quantity == 0) $product->save();

        }

        // Rediriger avec un message de succès
        return redirect()->route('orders.index')->with('success', 'Commande ajoutée avec succès.');
    }
    public function getCustomerDetails($id)
    {
        //récupérer les détails du client en fonction de l'ID

        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        return response()->json([
            'adress' => $customer->adress,
            'number' => $customer->number,



        ]);
    }

    public function getProductDetails($id)
    {
        //récupérer les détails du produit en fonction de l'ID
        $prod = Product::find($id);

        if (!$prod) {
            return response()->json(['error' => 'Product not found'], 404);
        }
        //$currentDate = Carbon::createFromFormat('d/m/Y', $prod->Orders->date)->format('Y-m-d');
        return response()->json([
            'price' => $prod->price,
            'quantity'=>$prod->quantity,
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('orders.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        // Validation des données de la requête
        $validatedData = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_date' => 'required|date',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.order_quantity' => 'required|integer|min:1',
        ]);

        // Supprimer les détails de la commande existants
        $order->Order_details()->delete();

        // Vérifier la disponibilité en stock pour chaque produit demandé
        foreach ($validatedData['products'] as $productData) {
            $product = Product::find($productData['product_id']);
            if ($product->quantity < $productData['order_quantity'] || $product->quantity == 0 ) {
                return redirect()->back()->withError('La quantité demandée n\'est pas disponible en stock pour le produit '.$product->name);
            }
        }

        // Mettre à jour la commande
        $order->update([
            'customer_id' => $validatedData['customer_id'],
            'order_date' => $validatedData['order_date'],
        ]);

        // Recréer les détails de la commande pour chaque produit
        foreach ($validatedData['products'] as $productData) {
            $order->Order_details()->create([
                'order_quantity' => $productData['order_quantity'],
                'product_id' => $productData['product_id'],
            ]);

            // Mettre à jour la quantité en stock du produit
            $product = Product::find($productData['product_id']);
            $product->quantity -= $productData['order_quantity'];
            if ($product->quantity == 0) $product->save();
        }

        // Rediriger avec un message de succès
        return redirect()->route('orders.index')->with('success', 'Commande mise à jour avec succès.');
    }




    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        dd($order->Order_details);
        return view('products.show', [
            'order' => $order->Order_details(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        return view('orders.edit', [
            'order' => $order
        ]);
    }




    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Récupérer la commande
        $order = Order::findOrFail($id);

        // Restaurer le stock pour chaque produit dans la commande
        foreach ($order->Order_details as $orderDetail) {
            if ($orderDetail->Products){
                $product = $orderDetail->Products;
                $product->quantity += $orderDetail->order_quantity;
                $product->save();
            }

        }

        // Supprimer la commande
        $order->delete();

        // Rediriger avec un message de succès
        return redirect()->route('orders.index')->with('success', 'Commande supprimée avec succès et le stock a été restauré.');
    }

    public function customerOrderHistory($customerId)
    {
        // Récupérer le client
        $customer = Customer::findOrFail($customerId);

        // Récupérer l'historique des commandes pour ce client
        $orderHistory = $customer->Orders()->with('Order_details')->get();

        // Retourner la vue avec l'historique des commandes
        return view('order.history', compact('orderHistory'));
    }
}
