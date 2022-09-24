<?php

namespace App\Http\Controllers;

use App\Models\Logistic;
use Illuminate\Http\Request;

class LogisticController extends Controller
{
    public function index()
    {

        return Logistic::paginate(20);
    }

    public function show(Logistic $logistic)
    {
        return $logistic;
    }

    public function store(Request $request)
    {
        return Logistic::create([
            'name' => $request->name,
            'fee' => $request->fee,
            'location' => $request->location,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'logo' => $request->logo
        ]);
    }

    public function update(Request $request, Logistic $logistic)
    {
        if ($request->filled('name')  && $request->has('name')) {
            $logistic->name = $request->name;
        }
        if ($request->filled('fee')  && $request->has('fee')) {
            $logistic->fee = $request->fee;
        }
        if ($request->filled('location')  && $request->has('location')) {
            $logistic->location = $request->location;
        }
        if ($request->filled('email')  && $request->has('email')) {
            $logistic->email = $request->email;
        }
        if ($request->filled('phone')  && $request->has('phone')) {
            $logistic->phone = $request->phone;
        }
        if ($request->filled('website')  && $request->has('website')) {
            $logistic->website = $request->website;
        }
        if ($request->filled('logo')  && $request->has('logo')) {
            $logistic->logo = $request->logo;
        }
        $logistic->save();
        return $logistic;
    }

    public function destroy(Logistic $logistic)
    {
        $logistic->delete();

        return "success";
    }
}
