<?php

namespace Database\Seeders;

use App\Models\Param;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ParamOptionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $map = [
            // ── Основные характеристики ──────────────────────────────────────────
            'Размер' => [
                'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL',
                '38', '40', '42', '44', '46', '48', '50', '52', '54', '56', '58', '60',
                '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '36',
            ],

            'Цвет' => [
                // Сплошные цвета
                json_encode(['name' => 'Белый', 'type' => 'solid', 'color1' => '#FFFFFF']),
                json_encode(['name' => 'Чёрный', 'type' => 'solid', 'color1' => '#000000']),
                json_encode(['name' => 'Серый', 'type' => 'solid', 'color1' => '#808080']),
                json_encode(['name' => 'Светло-серый', 'type' => 'solid', 'color1' => '#C0C0C0']),
                json_encode(['name' => 'Тёмно-серый', 'type' => 'solid', 'color1' => '#404040']),
                json_encode(['name' => 'Тёмно-синий', 'type' => 'solid', 'color1' => '#000080']),
                json_encode(['name' => 'Синий', 'type' => 'solid', 'color1' => '#0000FF']),
                json_encode(['name' => 'Голубой', 'type' => 'solid', 'color1' => '#87CEEB']),
                json_encode(['name' => 'Красный', 'type' => 'solid', 'color1' => '#FF0000']),
                json_encode(['name' => 'Бордовый', 'type' => 'solid', 'color1' => '#8B0000']),
                json_encode(['name' => 'Розовый', 'type' => 'solid', 'color1' => '#FFC0CB']),
                json_encode(['name' => 'Ярко-розовый', 'type' => 'solid', 'color1' => '#FF69B4']),
                json_encode(['name' => 'Фиолетовый', 'type' => 'solid', 'color1' => '#800080']),
                json_encode(['name' => 'Индиго', 'type' => 'solid', 'color1' => '#4B0082']),
                json_encode(['name' => 'Зелёный', 'type' => 'solid', 'color1' => '#008000']),
                json_encode(['name' => 'Светло-зелёный', 'type' => 'solid', 'color1' => '#90EE90']),
                json_encode(['name' => 'Тёмно-зелёный', 'type' => 'solid', 'color1' => '#006400']),
                json_encode(['name' => 'Оливковый', 'type' => 'solid', 'color1' => '#808000']),
                json_encode(['name' => 'Жёлтый', 'type' => 'solid', 'color1' => '#FFFF00']),
                json_encode(['name' => 'Золотистый', 'type' => 'solid', 'color1' => '#FFD700']),
                json_encode(['name' => 'Оранжевый', 'type' => 'solid', 'color1' => '#FFA500']),
                json_encode(['name' => 'Коричневый', 'type' => 'solid', 'color1' => '#A52A2A']),
                json_encode(['name' => 'Шоколадный', 'type' => 'solid', 'color1' => '#D2691E']),
                json_encode(['name' => 'Бежевый', 'type' => 'solid', 'color1' => '#F5F5DC']),
                json_encode(['name' => 'Хаки', 'type' => 'solid', 'color1' => '#F0E68C']),
                json_encode(['name' => 'Бирюзовый', 'type' => 'solid', 'color1' => '#00FFFF']),
                json_encode(['name' => 'Пурпурный', 'type' => 'solid', 'color1' => '#FF00FF']),
                json_encode(['name' => 'Лавандовый', 'type' => 'solid', 'color1' => '#E6E6FA']),
                json_encode(['name' => 'Персиковый', 'type' => 'solid', 'color1' => '#FFE4E1']),
                json_encode(['name' => 'Лимонный', 'type' => 'solid', 'color1' => '#FFFACD']),
                json_encode(['name' => 'Мятный', 'type' => 'solid', 'color1' => '#98FB98']),
                json_encode(['name' => 'Сливовый', 'type' => 'solid', 'color1' => '#DDA0DD']),
                json_encode(['name' => 'Медный', 'type' => 'solid', 'color1' => '#B87333']),
                json_encode(['name' => 'Серебристый', 'type' => 'solid', 'color1' => '#C0C0C0']),
                json_encode(['name' => 'Графитовый', 'type' => 'solid', 'color1' => '#708090']),
                json_encode(['name' => 'Антрацит', 'type' => 'solid', 'color1' => '#2F4F4F']),
                json_encode(['name' => 'Молочный', 'type' => 'solid', 'color1' => '#FFFAF0']),
                json_encode(['name' => 'Слоновая кость', 'type' => 'solid', 'color1' => '#F5F5F5']),
                json_encode(['name' => 'Терракотовый', 'type' => 'solid', 'color1' => '#8B4513']),
                json_encode(['name' => 'Васильковый', 'type' => 'solid', 'color1' => '#4169E1']),

                // Двойные цвета (dual)
                json_encode(['name' => 'Чёрно-белый', 'type' => 'dual', 'color1' => '#000000', 'color2' => '#FFFFFF']),
                json_encode(['name' => 'Красно-чёрный', 'type' => 'dual', 'color1' => '#FF0000', 'color2' => '#000000']),
                json_encode(['name' => 'Сине-белый', 'type' => 'dual', 'color1' => '#0000FF', 'color2' => '#FFFFFF']),
                json_encode(['name' => 'Жёлто-синий', 'type' => 'dual', 'color1' => '#FFFF00', 'color2' => '#0000FF']),
                json_encode(['name' => 'Красно-белый', 'type' => 'dual', 'color1' => '#FF0000', 'color2' => '#FFFFFF']),
                json_encode(['name' => 'Зелёно-белый', 'type' => 'dual', 'color1' => '#008000', 'color2' => '#FFFFFF']),
                json_encode(['name' => 'Серо-чёрный', 'type' => 'dual', 'color1' => '#808080', 'color2' => '#000000']),
                json_encode(['name' => 'Розово-белый', 'type' => 'dual', 'color1' => '#FFC0CB', 'color2' => '#FFFFFF']),

                // Градиенты
                json_encode(['name' => 'Закат', 'type' => 'gradient', 'color1' => '#FF6B6B', 'color2' => '#FFD93D', 'angle' => 135]),
                json_encode(['name' => 'Океан', 'type' => 'gradient', 'color1' => '#2E3192', 'color2' => '#1BFFFF', 'angle' => 135]),
                json_encode(['name' => 'Лес', 'type' => 'gradient', 'color1' => '#134E5E', 'color2' => '#71B280', 'angle' => 135]),
                json_encode(['name' => 'Радуга', 'type' => 'gradient', 'color1' => '#FF0000', 'color2' => '#FF00FF', 'angle' => 90]),
                json_encode(['name' => 'Рассвет', 'type' => 'gradient', 'color1' => '#FF9A56', 'color2' => '#FFD39A', 'angle' => 135]),
                json_encode(['name' => 'Сумерки', 'type' => 'gradient', 'color1' => '#0F2027', 'color2' => '#2C5364', 'angle' => 135]),
                json_encode(['name' => 'Лаванда', 'type' => 'gradient', 'color1' => '#C471F5', 'color2' => '#FA71CD', 'angle' => 135]),
                json_encode(['name' => 'Мята', 'type' => 'gradient', 'color1' => '#00B4DB', 'color2' => '#0083B0', 'angle' => 135]),
                json_encode(['name' => 'Персик', 'type' => 'gradient', 'color1' => '#ED4264', 'color2' => '#FFEDBC', 'angle' => 135]),
                json_encode(['name' => 'Аврора', 'type' => 'gradient', 'color1' => '#00C9FF', 'color2' => '#92FE9D', 'angle' => 135]),
            ],

            'Материал' => [
                'Хлопок', 'Лён', 'Шерсть', 'Кашемир', 'Шёлк', 'Вискоза',
                'Полиэстер', 'Нейлон', 'Акрил', 'Эластан', 'Спандекс',
                'Кожа натуральная', 'Кожа искусственная', 'Замша', 'Нубук',
                'Деним', 'Джинса', 'Флис', 'Велюр', 'Бархат',
                'Трикотаж', 'Футер', 'Кулирка', 'Интерлок', 'Рибана',
                'Мембрана', 'Софтшелл', 'Неопрен', 'Микрофибра',
            ],

            'Бренд' => [
                'Zara', 'H&M', 'Mango', 'Massimo Dutti', 'Bershka',
                'Pull&Bear', 'Stradivarius', 'Reserved', 'Sinsay',
                'Nike', 'Adidas', 'Puma', 'Reebok', 'New Balance',
                'Under Armour', 'Columbia', 'The North Face', 'Patagonia',
                'Levi\'s', 'Wrangler', 'Lee', 'Diesel', 'Calvin Klein',
                'Tommy Hilfiger', 'Ralph Lauren', 'Lacoste', 'Hugo Boss',
                'Armani', 'Gucci', 'Prada', 'Versace', 'Dolce&Gabbana',
                'Uniqlo', 'GAP', 'Old Navy', 'Banana Republic',
                'Marks & Spencer', 'Next', 'Topshop', 'ASOS',
                'Befree', 'Ostin', 'Sela', 'Incity', 'Твое',
                'Baon', 'Mexx', 'Tom Tailor', 'S.Oliver', 'Esprit',
            ],

            'Страна производства' => [
                'Россия', 'Китай', 'Турция', 'Бангладеш', 'Вьетнам',
                'Индия', 'Пакистан', 'Италия', 'Португалия', 'Испания',
                'Польша', 'Румыния', 'Германия', 'Франция', 'Великобритания',
                'США', 'Южная Корея', 'Индонезия', 'Таиланд', 'Камбоджа',
            ],

            'Сезон' => [
                'Весна', 'Лето', 'Осень', 'Зима',
                'Весна-Лето', 'Осень-Зима', 'Демисезон', 'Всесезонный',
            ],

            'Пол' => [
                'Мужской', 'Женский', 'Унисекс', 'Для мальчиков', 'Для девочек',
            ],

            // ── Детали и особенности ─────────────────────────────────────────────
            'Тип посадки' => [
                'Свободная', 'Прямая', 'Приталенная', 'Облегающая',
                'Оверсайз', 'Слим', 'Регуляр', 'Boyfriend', 'Skinny',
                'Высокая посадка', 'Средняя посадка', 'Низкая посадка',
            ],

            'Длина' => [
                'Мини', 'Миди', 'Макси', 'Короткая', 'Средняя', 'Длинная',
                'До колена', 'Ниже колена', 'В пол', 'Укороченная',
                'Стандартная', 'Удлинённая',
            ],

            'Тип рукава' => [
                'Без рукавов', 'Короткий', 'Три четверти', 'Длинный',
                'Реглан', 'Кимоно', 'Фонарик', 'Летучая мышь',
                'Спущенное плечо', 'Втачной', 'Цельнокроеный',
            ],

            'Тип горловины' => [
                'Круглый вырез', 'V-образный вырез', 'Лодочка', 'Квадратный вырез',
                'Воротник-стойка', 'Отложной воротник', 'Поло', 'Хомут',
                'Водолазка', 'Американская пройма', 'Халтер', 'Асимметричный',
            ],

            'Застёжка' => [
                'Без застёжки', 'Молния', 'Пуговицы', 'Кнопки', 'Липучка',
                'Завязки', 'Крючки', 'Шнуровка', 'Резинка', 'Пояс',
            ],

            'Принт/узор' => [
                'Без принта', 'Однотонный', 'Полоска', 'Клетка', 'Горох',
                'Цветочный', 'Геометрический', 'Абстрактный', 'Анималистичный',
                'Камуфляж', 'Надписи', 'Логотип', 'Этнический', 'Пейсли',
                'Тай-дай', 'Градиент', 'Колор-блок',
            ],

            'Стиль' => [
                'Повседневный', 'Деловой', 'Спортивный', 'Классический',
                'Casual', 'Smart Casual', 'Business Casual', 'Formal',
                'Уличный', 'Гранж', 'Бохо', 'Минимализм', 'Романтический',
                'Этнический', 'Милитари', 'Морской', 'Ретро', 'Винтаж',
            ],
        ];

        foreach ($map as $title => $values) {
            $param = Param::where('title', $title)->first();
            if (!$param) continue;

            $param->options()->delete();
            foreach ($values as $i => $value) {
                $param->options()->create([
                    'value' => $value,
                    'slug'  => Str::slug($value),
                    'sort'  => $i,
                ]);
            }
        }
    }
}
