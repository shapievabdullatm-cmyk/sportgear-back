<?php

namespace App\Enums\Param;

enum ParamFilterTypeEnum: int
{
    case STRING      = 1;
    case TEXT        = 2;
    case INTEGER     = 3;
    case FLOAT       = 4;
    case COLOR       = 6;
    case BOOLEAN     = 7;
    case MULTISELECT = 8;

    public function label(): string
    {
        return match($this) {
            self::STRING      => 'Строчный',
            self::TEXT        => 'Текстовый',
            self::INTEGER     => 'Целый числовой',
            self::FLOAT       => 'Дробный числовой',
            self::COLOR       => 'Цвет',
            self::BOOLEAN     => 'Булевый',
            self::MULTISELECT => 'Мультивыбор',
        };
    }

    public function hasOptions(): bool
    {
        return match($this) {
            self::COLOR, self::MULTISELECT => true,
            default                        => false,
        };
    }

    /**
     * Единица измерения доступна для любого типа атрибута.
     */
    public function supportsUnit(): bool
    {
        return true;
    }

    public function group(): string
    {
        return match($this) {
            self::STRING,
            self::TEXT        => 'Текст',
            self::INTEGER,
            self::FLOAT       => 'Числа',
            self::COLOR       => 'Визуальные',
            self::BOOLEAN     => 'Логические',
            self::MULTISELECT => 'Выбор из списка',
        };
    }

    public static function valuesAsString(): string
    {
        return implode(',', array_map(fn($e) => $e->value, self::cases()));
    }

    /**
     * Плоский список для фронтенда (используется в ParamController).
     */
    public static function collection(): array
    {
        return array_map(fn($e) => [
            'value'        => $e->value,
            'title'        => $e->label(),
            'hasOptions'   => $e->hasOptions(),
            'supportsUnit' => $e->supportsUnit(),
        ], self::cases());
    }

    /**
     * Сгруппированный список — для мест, где нужна группировка.
     */
    public static function groupedCollection(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $group = $case->group();
            if (!isset($groups[$group])) {
                $groups[$group] = ['group' => $group, 'types' => []];
            }
            $groups[$group]['types'][] = [
                'value'        => $case->value,
                'title'        => $case->label(),
                'hasOptions'   => $case->hasOptions(),
                'supportsUnit' => $case->supportsUnit(),
            ];
        }
        return array_values($groups);
    }
}
