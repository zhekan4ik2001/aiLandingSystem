<?php

namespace App\Http\Controllers;

use App\Models\Point;
use App\Models\Route as FlightRoute;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('routes', [
            'routes' => FlightRoute::orderBy('id')->get(),
            'points' => Point::orderBy('id')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'point_ids' => 'required|array|min:2',
            'point_ids.*' => 'required|integer|exists:points,id',
        ]);

        $route = FlightRoute::create($data);

        return $request->expectsJson()
            ? response()->json($route)
            : redirect()->route('routes.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $route = FlightRoute::findOrFail($id);

        return response()->json([
            'route' => $route,
            'points' => $route->orderedPoints()->values(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $route = FlightRoute::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'point_ids' => 'required|array|min:2',
            'point_ids.*' => 'required|integer|exists:points,id',
        ]);

        $route->update($data);

        return $request->expectsJson()
            ? response()->json($route)
            : redirect()->route('routes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        FlightRoute::findOrFail($id)->delete();

        return request()->expectsJson()
            ? response()->json(['status' => 'ok'])
            : redirect()->route('routes.index');
    }
}
