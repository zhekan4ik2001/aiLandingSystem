<?php

namespace App\Http\Controllers;

use App\Models\Point;
use Illuminate\Http\Request;

class PointController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('points', [
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
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude_feet' => 'required|numeric|min:0',
            'type' => 'required|in:input,intermediate,final',
        ]);

        $point = Point::create($data);

        return $request->expectsJson()
            ? response()->json($point)
            : redirect()->route('points.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(Point::findOrFail($id));
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
        $point = Point::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude_feet' => 'required|numeric|min:0',
            'type' => 'required|in:input,intermediate,final',
        ]);

        $point->update($data);

        return $request->expectsJson()
            ? response()->json($point)
            : redirect()->route('points.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Point::findOrFail($id)->delete();

        return request()->expectsJson()
            ? response()->json(['status' => 'ok'])
            : redirect()->route('points.index');
    }
}
