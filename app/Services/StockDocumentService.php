<?php

namespace App\Services;

use App\Models\StockDocument;
use App\Models\StockDocumentItem;
use Illuminate\Support\Facades\DB;

class StockDocumentService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Создать новый документ
     */
    public function createDocument(array $data, int $userId): StockDocument
    {
        return DB::transaction(function () use ($data, $userId) {
            $document = StockDocument::create([
                'number' => $this->generateDocumentNumber($data['type']),
                'type' => $data['type'],
                'warehouse_id' => $data['warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
                'status' => 'draft',
                'user_id' => $userId,
                'comment' => $data['comment'] ?? null,
            ]);

            // Добавляем позиции
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $document->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'] ?? null,
                        'comment' => $item['comment'] ?? null,
                    ]);
                }
            }

            return $document->load(['items.product', 'warehouse', 'toWarehouse']);
        });
    }

    /**
     * Обновить документ (только если он в статусе draft)
     */
    public function updateDocument(StockDocument $document, array $data): StockDocument
    {
        if ($document->status !== 'draft') {
            throw new \Exception('Можно редактировать только черновики');
        }

        return DB::transaction(function () use ($document, $data) {
            $document->update([
                'type' => $data['type'] ?? $document->type,
                'warehouse_id' => $data['warehouse_id'] ?? $document->warehouse_id,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? $document->to_warehouse_id,
                'comment' => $data['comment'] ?? $document->comment,
            ]);

            // Обновляем позиции
            if (isset($data['items'])) {
                // Удаляем старые позиции
                $document->items()->delete();

                // Добавляем новые
                foreach ($data['items'] as $item) {
                    $document->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'] ?? null,
                        'comment' => $item['comment'] ?? null,
                    ]);
                }
            }

            return $document->fresh(['items.product', 'warehouse', 'toWarehouse']);
        });
    }

    /**
     * Провести документ (применить изменения к остаткам)
     */
    public function completeDocument(StockDocument $document, int $userId): StockDocument
    {
        if ($document->status !== 'draft') {
            throw new \Exception('Документ уже проведен или отменен');
        }

        if ($document->items->isEmpty()) {
            throw new \Exception('Документ не содержит позиций');
        }

        return DB::transaction(function () use ($document, $userId) {
            foreach ($document->items as $item) {
                $reason = "Документ #{$document->number}";
                $comment = $document->comment;

                switch ($document->type) {
                    case 'in':
                        $this->stockService->addStock(
                            $item->product_id,
                            $document->warehouse_id,
                            $item->quantity,
                            $reason,
                            $comment,
                            $userId
                        );
                        break;

                    case 'out':
                        $this->stockService->removeStock(
                            $item->product_id,
                            $document->warehouse_id,
                            $item->quantity,
                            $reason,
                            $comment,
                            $userId
                        );
                        break;

                    case 'transfer':
                        if (!$document->to_warehouse_id) {
                            throw new \Exception('Не указан целевой склад для перемещения');
                        }
                        $this->stockService->transferStock(
                            $item->product_id,
                            $document->warehouse_id,
                            $document->to_warehouse_id,
                            $item->quantity,
                            $reason,
                            $comment,
                            $userId
                        );
                        break;
                }
            }

            $document->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            return $document->fresh(['items.product', 'warehouse', 'toWarehouse', 'completedBy']);
        });
    }

    /**
     * Отменить документ
     */
    public function cancelDocument(StockDocument $document): StockDocument
    {
        if ($document->status === 'completed') {
            throw new \Exception('Нельзя отменить проведенный документ');
        }

        $document->update(['status' => 'cancelled']);

        return $document;
    }

    /**
     * Удалить документ (только черновики)
     */
    public function deleteDocument(StockDocument $document): void
    {
        if ($document->status !== 'draft') {
            throw new \Exception('Можно удалять только черновики');
        }

        $document->delete();
    }

    /**
     * Генерировать номер документа
     */
    private function generateDocumentNumber(string $type): string
    {
        $prefix = match($type) {
            'in' => 'IN',
            'out' => 'OUT',
            'transfer' => 'TR',
        };

        $date = now()->format('Ymd');
        $lastDocument = StockDocument::where('type', $type)
            ->whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastDocument ? (int) substr($lastDocument->number, -4) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }
}