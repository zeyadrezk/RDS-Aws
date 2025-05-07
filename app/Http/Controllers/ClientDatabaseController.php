<?php
namespace App\Http\Controllers;

use App\Models\ClientDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ClientDatabaseController extends Controller
{
    public function index()
    {
        $databases = ClientDatabase::all();
        return response()->json($databases);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'database_name' => 'required|string|regex:/^[a-zA-Z0-9_]+$/|min:3|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dbName = 'client_' . Str::lower($request->database_name);

        if (ClientDatabase::where('name', $dbName)->exists()) {
            return response()->json(['error' => 'Database already exists.'], 409);
        }

        try {
            DB::statement("CREATE DATABASE `$dbName`");

            $clientDatabase = ClientDatabase::create([
                'name' => $dbName,
                'status' => 'active',
            ]);

            return response()->json($clientDatabase, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create database: ' . $e->getMessage()], 500);
        }
    }
}
