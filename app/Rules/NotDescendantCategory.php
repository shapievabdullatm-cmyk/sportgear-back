<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotDescendantCategory implements ValidationRule
{
    public function __construct(private ?int $categoryId = null)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Если parent_id не указан, валидация проходит
        if (is_null($value)) {
            return;
        }

        $parentId = (int) $value;

        // Если это создание новой категории (нет ID), проверять нечего
        if (is_null($this->categoryId)) {
            return;
        }

        // Нельзя назначить саму себя родителем
        if ($parentId === $this->categoryId) {
            $fail('Категория не может быть родителем самой себя.');
            return;
        }

        // Проверяем, не является ли выбранный родитель потомком текущей категории
        if ($this->isDescendant($parentId, $this->categoryId)) {
            $fail('Нельзя назначить дочернюю категорию в качестве родительской.');
            return;
        }
    }

    /**
     * Проверяет, является ли $potentialDescendantId потомком $ancestorId
     */
    private function isDescendant(int $potentialDescendantId, int $ancestorId): bool
    {
        $current = Category::find($potentialDescendantId);

        // Защита от бесконечного цикла (максимум 100 уровней вложенности)
        $depth = 0;
        $maxDepth = 100;

        while ($current && $depth < $maxDepth) {
            // Если нашли предка — значит potentialDescendant является потомком ancestor
            if ($current->id === $ancestorId) {
                return true;
            }

            // Поднимаемся выше по иерархии
            if ($current->parent_id) {
                $current = Category::find($current->parent_id);
                $depth++;
            } else {
                break;
            }
        }

        return false;
    }
}
