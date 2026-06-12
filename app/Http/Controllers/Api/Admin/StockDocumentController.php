<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockDocument;
use App\Services\StockDocumentService;
use Illuminate\Http\Request;

class StockDocumentController extends Controller
{
    public function __construct(
        private StockDocumentService $documentService
    ) {}

    /**
     * Список документов
     */
    public function index(Request $request)
    {
        $query = StockDocument::with(['warehouse', 'toWarehouse', 'user', 'items.product']);

        // Фильтр по типу
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Фильтр по статусу
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Фильтр по складу
        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Поиск по номеру
        if ($request->has('search')) {
            $query->where('number', 'like', "%{$request->search}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
    }

    /**
     * Получить документ
     */
    public function show(StockDocument $document)
    {
        return $document->load(['warehouse', 'toWarehouse', 'user', 'completedBy', 'items.product']);
    }

    /**
     * Создать документ
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:in,out,transfer',
            'warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'nullable|exists:warehouses,id|different:warehouse_id',
            'comment' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.comment' => 'nullable|string|max:500',
        ]);

        try {
            $document = $this->documentService->createDocument($validated, $request->user()->id);

            return response()->json([
                'message' => 'Документ успешно создан',
                'document' => $document,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Обновить документ
     */
    public function update(Request $request, StockDocument $document)
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:in,out,transfer',
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'to_warehouse_id' => 'nullable|exists:warehouses,id|different:warehouse_id',
            'comment' => 'nullable|string|max:1000',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.comment' => 'nullable|string|max:500',
        ]);

        try {
            $document = $this->documentService->updateDocument($document, $validated);

            return response()->json([
                'message' => 'Документ успешно обновлен',
                'document' => $document,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Провести документ
     */
    public function complete(Request $request, StockDocument $document)
    {
        try {
            $document = $this->documentService->completeDocument($document, $request->user()->id);

            return response()->json([
                'message' => 'Документ успешно проведен',
                'document' => $document,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Отменить документ
     */
    public function cancel(StockDocument $document)
    {
        try {
            $document = $this->documentService->cancelDocument($document);

            return response()->json([
                'message' => 'Документ отменен',
                'document' => $document,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Удалить документ
     */
    public function destroy(StockDocument $document)
    {
        try {
            $this->documentService->deleteDocument($document);

            return response()->json(['message' => 'Документ успешно удален']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
