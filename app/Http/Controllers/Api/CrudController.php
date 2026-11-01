<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrudController extends Controller
{
    private function cfg(string $resource): array
    {
        return config("crud.$resource") ?? abort(404);
    }

    private function rules(array $cfg, bool $update, $id = null): array
    {
        $out = [];
        foreach ($cfg['rules'] as $field => $rule) {
            $rule = str_replace('{id}', $update ? (string) $id : 'NULL', $rule);
            $out[$field] = $update ? str_replace('required', 'sometimes', $rule) : $rule;
        }
        return $out;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $cfg = $this->cfg($resource);
        $q = $cfg['model']::query();

        foreach ($cfg['filters'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('id', 'desc')->paginate(15)]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $cfg = $this->cfg($resource);
        $data = $request->validate($this->rules($cfg, false));

        return response()->json(['success' => true, 'message' => 'Created.', 'data' => $cfg['model']::create($data)], 201);
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $cfg = $this->cfg($resource);

        return response()->json(['success' => true, 'data' => $cfg['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        $cfg = $this->cfg($resource);
        $row = $cfg['model']::findOrFail($id);
        $row->update($request->validate($this->rules($cfg, true, $id)));

        return response()->json(['success' => true, 'message' => 'Updated.', 'data' => $row]);
    }

    public function destroy(string $resource, string $id): JsonResponse
    {
        $cfg = $this->cfg($resource);
        $row = $cfg['model']::findOrFail($id);

        try {
            $row->delete();
        } catch (QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Cannot delete: this record is used by other data.'], 409);
        }

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}