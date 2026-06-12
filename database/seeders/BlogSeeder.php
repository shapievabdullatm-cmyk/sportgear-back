<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    /**
     * Гарантированно работающий YouTube ID — подменяется на любые "example"-плейсхолдеры
     * из старого сидера, чтобы embed реально загружался.
     */
    private const FALLBACK_YT_ID = 'dQw4w9WgXcQ';

    public function run(): void
    {
        $categories = $this->seedCategories();

        $blogs = $this->blogsData($categories);

        foreach ($blogs as $index => $data) {
            $imageDiskPath = $this->uploadBanner($data['image']);
            if (!$imageDiskPath) continue;

            $html = $this->blocksToHtml($data['content']);

            $blog = new Blog([
                'blog_category_id'  => $data['category_id'] ?? null,
                'title'             => $data['title'],
                'banner_image'      => $imageDiskPath,
                'content'           => $html,
                'meta_title'        => $data['meta_title'],
                'meta_description'  => $data['meta_description'],
                'meta_keywords'     => $data['meta_keywords'],
                'is_published'      => true,
                'published_at'      => now()->subDays(5 + ($index * 3)),
            ]);

            if (empty($blog->slug)) {
                $blog->slug = Blog::uniqueSlug($data['title']);
            }

            $blog->save();

            echo "✅ Блог '{$blog->title}' создан (ID: {$blog->id})\n";
        }

        echo "\n🎉 Все блоги созданы\n";
    }

    /**
     * Категории + возврат map name => id
     */
    private function seedCategories(): array
    {
        $defs = [
            ['name' => 'История',         'sort_order' => 1],
            ['name' => 'Топ-листы',       'sort_order' => 2],
            ['name' => 'Обучение',        'sort_order' => 3],
            ['name' => 'Питание',         'sort_order' => 4],
            ['name' => 'Тренировки',      'sort_order' => 5],
            ['name' => 'Психология',      'sort_order' => 6],
        ];

        $map = [];
        foreach ($defs as $d) {
            $slug = Str::slug($d['name']) ?: 'cat-' . Str::random(6);
            $cat = BlogCategory::firstOrCreate(
                ['name' => $d['name']],
                [
                    'slug'       => $slug,
                    'sort_order' => $d['sort_order'],
                ]
            );
            $map[$d['name']] = $cat->id;
        }
        return $map;
    }

    private function uploadBanner(string $filename): ?string
    {
        $path = database_path("seeders/blogs/{$filename}");
        if (!file_exists($path)) {
            echo "⚠️  Файл {$filename} не найден\n";
            return null;
        }

        $diskPath = 'blogs/' . uniqid() . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        Storage::disk('s3')->put($diskPath, file_get_contents($path), 'public');
        return $diskPath;
    }

    /**
     * Конвертирует массив блоков в HTML под формат TipTapEditor.
     */
    private function blocksToHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $b) {
            $type = $b['type'] ?? null;
            switch ($type) {
                case 'paragraph':
                    $html .= '<p>' . e($b['content']) . '</p>';
                    break;

                case 'heading':
                    $level = max(1, min(3, (int)($b['level'] ?? 2)));
                    $html .= "<h{$level}>" . e($b['content']) . "</h{$level}>";
                    break;

                case 'list':
                    $html .= '<ul>';
                    foreach ($b['items'] as $item) {
                        $html .= '<li>' . e($item) . '</li>';
                    }
                    $html .= '</ul>';
                    break;

                case 'quote':
                    $html .= '<blockquote><p>' . e($b['content']);
                    if (!empty($b['author'])) {
                        $html .= ' — <em>' . e($b['author']) . '</em>';
                    }
                    $html .= '</p></blockquote>';
                    break;

                case 'link':
                    $url = e($b['url']);
                    $text = e($b['text']);
                    $html .= "<p><a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$text}</a></p>";
                    break;

                case 'video':
                    $info = $this->parseVideoUrl($b['url']);
                    if (!$info) break;
                    $src = e($info['embed']);
                    $platform = $info['platform'];
                    $allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
                    $html .= "<div data-video-embed=\"\" data-src=\"{$src}\" data-platform=\"{$platform}\" class=\"video-embed\">"
                        . "<iframe src=\"{$src}\" frameborder=\"0\" allowfullscreen=\"true\" allow=\"{$allow}\"></iframe>"
                        . '</div>';
                    if (!empty($b['caption'])) {
                        $html .= '<p style="text-align:center;color:#888;font-size:13px;font-style:italic;">'
                            . e($b['caption']) . '</p>';
                    }
                    break;

                case 'link_preview':
                    $href = e($b['href']);
                    $title = e($b['title']);
                    $desc = e($b['description'] ?? '');
                    $domain = e($b['domain']);
                    $image = e($b['image'] ?? '');
                    $img = $image ? "<div class=\"lp-img\"><img src=\"{$image}\" alt=\"{$title}\"></div>" : '';
                    $html .= "<a data-link-preview=\"\" class=\"link-preview-block\" "
                        . "href=\"{$href}\" data-href=\"{$href}\" data-title=\"{$title}\" "
                        . "data-description=\"{$desc}\" data-image=\"{$image}\" data-domain=\"{$domain}\" "
                        . "target=\"_blank\" rel=\"noopener noreferrer\">"
                        . $img
                        . "<div class=\"lp-body\">"
                        . "<span class=\"lp-domain\">{$domain}</span>"
                        . "<strong class=\"lp-title\">{$title}</strong>"
                        . ($desc ? "<p class=\"lp-desc\">{$desc}</p>" : '')
                        . "</div></a>";
                    break;

                case 'html_demo':
                    $code = $b['code'] ?? '';
                    $heightAttr = (string)($b['height'] ?? '420');
                    $encoded = base64_encode($code);
                    $html .= "<div data-html-demo=\"\" data-code=\"{$encoded}\" data-height=\"{$heightAttr}\" class=\"html-demo-block\"></div>";
                    break;
            }
        }
        return $html;
    }

    private function parseVideoUrl(string $url): ?array
    {
        // Подменяем явно фейковые "example" на рабочий ID
        if (preg_match('/(?:example|video-?example|psychology-ufc|video-training-basics|diet-example|mental-training)/i', $url)) {
            return [
                'embed'    => 'https://www.youtube.com/embed/' . self::FALLBACK_YT_ID,
                'platform' => 'youtube',
            ];
        }

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return ['embed' => "https://www.youtube.com/embed/{$m[1]}", 'platform' => 'youtube'];
        }
        if (preg_match('/rutube\.ru\/(?:video|embed|play\/embed)\/([a-zA-Z0-9]+)/', $url, $m)) {
            return ['embed' => "https://rutube.ru/play/embed/{$m[1]}", 'platform' => 'rutube'];
        }
        if (preg_match('/(?:vk\.com|vkvideo\.ru)\/video(-?\d+_\d+)/', $url, $m)) {
            [$oid, $id] = explode('_', $m[1]);
            return ['embed' => "https://vkvideo.ru/video_ext.php?oid={$oid}&id={$id}&hd=2", 'platform' => 'vk'];
        }
        if (preg_match('/dzen\.ru\/video\/watch\/([a-zA-Z0-9]+)/', $url, $m)) {
            return ['embed' => "https://dzen.ru/embed/{$m[1]}", 'platform' => 'dzen'];
        }
        if (preg_match('/ok\.ru\/video\/(\d+)/', $url, $m)) {
            return ['embed' => "https://ok.ru/videoembed/{$m[1]}", 'platform' => 'ok'];
        }
        // Неизвестный формат — подменяем на fallback
        return [
            'embed'    => 'https://www.youtube.com/embed/' . self::FALLBACK_YT_ID,
            'platform' => 'youtube',
        ];
    }

    private function blogsData(array $cat): array
    {
        return [
            [
                'title' => 'История UFC: От подпольных боёв до мирового признания',
                'image' => 'blog-1.jpg',
                'category_id' => $cat['История'],
                'meta_title' => 'История UFC: От подпольных боёв до мирового признания',
                'meta_description' => 'Узнайте, как UFC превратился из подпольного турнира в крупнейшую организацию смешанных единоборств в мире',
                'meta_keywords' => 'UFC, история UFC, смешанные единоборства, ММА',
                'content' => [
                    ['type' => 'paragraph', 'content' => 'Ultimate Fighting Championship (UFC) — это не просто спортивная организация, это целая эпоха в истории боевых искусств. Основанная в 1993 году, UFC начинала как турнир без правил, где бойцы различных дисциплин сражались за звание лучшего.'],
                    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Документальный фильм об истории UFC'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Начало пути'],
                    ['type' => 'paragraph', 'content' => 'Первый турнир UFC состоялся 12 ноября 1993 года в Денвере, штат Колорадо. Восемь бойцов из разных боевых дисциплин встретились в восьмиугольнике, который позже стал символом организации.'],
                    ['type' => 'quote', 'content' => 'UFC 1 изменил мир боевых искусств навсегда. Мы доказали, что техника побеждает силу.', 'author' => 'Ройс Грейси, победитель UFC 1'],
                    ['type' => 'list', 'items' => [
                        'Ройс Грейси (Бразильское джиу-джитсу) — победитель',
                        'Кен Шемрок (Панкратион)',
                        'Патрик Смит (Кикбоксинг)',
                        'Жерар Гордо (Савате)',
                        'Кевин Розье (Кикбоксинг)',
                        'Зейн Фрейзер (Карате)',
                        'Теила Туи (Сумо)',
                        'Арт Джиммерсон (Бокс)',
                    ]],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Тёмные времена'],
                    ['type' => 'paragraph', 'content' => 'В конце 1990-х UFC столкнулся с серьёзными проблемами. Сенатор Джон Маккейн назвал ММА "человеческими петушиными боями", что привело к запрету трансляций во многих штатах. Организация была на грани банкротства.'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Возрождение'],
                    ['type' => 'paragraph', 'content' => 'В 2001 году братья Фертитта и Дана Уайт выкупили UFC за 2 миллиона долларов. Они ввели единые правила, весовые категории и медицинский контроль. Запуск реалити-шоу "The Ultimate Fighter" в 2005 году стал переломным моментом — UFC получил массовую аудиторию.'],
                    ['type' => 'link_preview',
                        'href' => 'https://www.ufc.com',
                        'title' => 'UFC — Ultimate Fighting Championship',
                        'description' => 'Официальный сайт UFC: расписание турниров, рейтинги бойцов, новости и видео.',
                        'image' => 'https://www.ufc.com/themes/custom/ufc/assets/img/logo--small.svg',
                        'domain' => 'ufc.com'],
                    ['type' => 'paragraph', 'content' => 'Сегодня UFC — это многомиллиардная индустрия с турнирами по всему миру, контрактами с крупнейшими телеканалами и легендарными бойцами, ставшими мировыми звёздами.'],
                ],
            ],
            [
                'title' => 'Топ-5 легендарных боёв в истории UFC',
                'image' => 'blog-2.jpg',
                'category_id' => $cat['Топ-листы'],
                'meta_title' => 'Топ-5 легендарных боёв в истории UFC',
                'meta_description' => 'Самые эпичные и запоминающиеся поединки в истории Ultimate Fighting Championship',
                'meta_keywords' => 'UFC, легендарные бои, лучшие бои UFC, ММА',
                'content' => [
                    ['type' => 'paragraph', 'content' => 'За 30 лет существования UFC мы видели тысячи поединков, но некоторые из них навсегда вошли в историю спорта. Представляем пять боёв, которые должен знать каждый фанат ММА.'],
                    ['type' => 'heading', 'level' => 2, 'content' => '1. Форрест Гриффин vs Стефан Боннар (TUF 1 Finale, 2005)'],
                    ['type' => 'paragraph', 'content' => 'Этот бой спас UFC от банкротства. Три раунда безумного обмена ударами, где оба бойца выложились на 200%. После боя Дана Уайт дал контракты обоим участникам.'],
                    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Полный бой: Гриффин vs Боннар'],
                    ['type' => 'quote', 'content' => 'Это был самый важный бой в истории UFC. Без него не было бы того UFC, который мы знаем сегодня.', 'author' => 'Дана Уайт, президент UFC'],
                    ['type' => 'heading', 'level' => 2, 'content' => '2. Конор МакГрегор vs Хосе Альдо (UFC 194, 2015)'],
                    ['type' => 'paragraph', 'content' => '13 секунд — именно столько понадобилось МакГрегору, чтобы нокаутировать непобедимого чемпиона Альдо. Самый быстрый нокаут в истории титульных боёв UFC.'],
                    ['type' => 'list', 'items' => [
                        'Время боя: 13 секунд',
                        'Способ победы: Нокаут левым хуком',
                        'Серия Альдо до боя: 18 побед подряд',
                        'Возраст Альдо на момент боя: 29 лет',
                        'Возраст МакГрегора: 27 лет',
                    ]],
                    ['type' => 'heading', 'level' => 2, 'content' => '3. Хабиб Нурмагомедов vs Конор МакГрегор (UFC 229, 2018)'],
                    ['type' => 'paragraph', 'content' => 'Самый продаваемый PPV в истории UFC — 2.4 миллиона покупок. Противостояние двух стилей, культур и характеров. Хабиб доминировал четыре раунда и завершил бой удушающим приёмом.'],
                    ['type' => 'heading', 'level' => 2, 'content' => '4. Жанна Йенджейчик vs Вейли Жанг (UFC 248, 2020)'],
                    ['type' => 'paragraph', 'content' => 'Лучший женский бой в истории UFC. Пять раундов войны, где обе спортсменки показали невероятную технику, выносливость и волю к победе.'],
                    ['type' => 'heading', 'level' => 2, 'content' => '5. Исраэль Адесанья vs Келвин Гастелум (UFC 236, 2019)'],
                    ['type' => 'paragraph', 'content' => 'Бой за временный титул в среднем весе превратился в настоящую войну. Оба бойца были на грани нокаута несколько раз. Адесанья победил единогласным решением, но оба получили бонус "Бой вечера".'],
                    ['type' => 'link', 'url' => 'https://www.sherdog.com', 'text' => 'Полная статистика всех боёв UFC на Sherdog'],
                ],
            ],
            [
                'title' => 'Как выбрать экипировку для тренировок ММА',
                'image' => 'blog-3.jpg',
                'category_id' => $cat['Обучение'],
                'meta_title' => 'Как выбрать экипировку для тренировок ММА',
                'meta_description' => 'Полное руководство по выбору перчаток, защиты и одежды для смешанных единоборств',
                'meta_keywords' => 'экипировка ММА, перчатки для ММА, защита для единоборств',
                'content' => [
                    ['type' => 'paragraph', 'content' => 'Правильная экипировка — это не просто комфорт, это ваша безопасность и эффективность тренировок. Разбираемся, что нужно начинающему бойцу ММА и на что обратить внимание при выборе.'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Базовый набор для начинающих'],
                    ['type' => 'list', 'items' => [
                        'Перчатки для ММА (6-7 унций)',
                        'Шлем с защитой подбородка',
                        'Капа (термопластичная или индивидуальная)',
                        'Защита голени и стопы',
                        'Рашгард и компрессионные шорты',
                        'Бинты для рук',
                        'Защита паха',
                    ]],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Перчатки для ММА'],
                    ['type' => 'paragraph', 'content' => 'Перчатки для ММА отличаются от боксёрских — они легче (4-6 унций) и имеют открытые пальцы для захватов. Для начинающих рекомендуем перчатки весом 6-7 унций с хорошей защитой запястья.'],
                    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Как правильно выбрать перчатки для ММА'],
                    ['type' => 'quote', 'content' => 'Не экономьте на защите. Одна травма может стоить вам месяцев тренировок.', 'author' => 'Федор Емельяненко'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Калькулятор размера перчаток'],
                    ['type' => 'paragraph', 'content' => 'Простой интерактивный калькулятор: введите свой вес и получите рекомендуемую унцовку перчаток.'],
                    ['type' => 'html_demo',
                        'height' => '320',
                        'code' => "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><style>body{font-family:system-ui,sans-serif;padding:24px;background:#FAFAF8;color:#1A1A1A;margin:0;}h3{margin:0 0 12px;font-size:16px;}label{display:block;font-size:13px;color:#666;margin-bottom:6px;}input{width:100%;padding:10px 12px;border:1px solid #E8E6E0;border-radius:8px;font-size:14px;box-sizing:border-box;outline:none;}input:focus{border-color:#1A1A1A;}.result{margin-top:20px;padding:16px;background:#1A1A1A;color:#fff;border-radius:8px;font-size:14px;}.result strong{font-size:22px;display:block;margin-top:6px;}</style></head><body><h3>Калькулятор перчаток</h3><label for=\"w\">Ваш вес (кг)</label><input id=\"w\" type=\"number\" placeholder=\"75\" oninput=\"calc()\"><div class=\"result\" id=\"r\">Введите вес для расчёта</div><script>function calc(){var w=parseFloat(document.getElementById('w').value);var r=document.getElementById('r');if(!w||w<30){r.innerHTML='Введите вес для расчёта';return}var oz;if(w<60)oz='4 oz';else if(w<70)oz='6 oz';else if(w<80)oz='8 oz';else oz='10 oz';r.innerHTML='Рекомендуем:<strong>'+oz+'</strong>'}</script></body></html>"],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Топ брендов экипировки'],
                    ['type' => 'list', 'items' => [
                        'Venum — французский бренд, официальный партнёр UFC',
                        'Hayabusa — премиум-качество из Канады',
                        'Everlast — классика бокса и ММА',
                        'RDX — отличное соотношение цены и качества',
                        'Twins Special — тайский бренд для муай-тай и ММА',
                    ]],
                    ['type' => 'paragraph', 'content' => 'Помните: качественная экипировка служит годами и окупается безопасностью и комфортом тренировок.'],
                ],
            ],
            [
                'title' => 'Диета бойца UFC: Что едят чемпионы',
                'image' => 'blog-4.jpg',
                'category_id' => $cat['Питание'],
                'meta_title' => 'Диета бойца UFC: Что едят чемпионы',
                'meta_description' => 'Секреты питания профессиональных бойцов ММА и UFC. Как правильно питаться для набора массы и сгонки веса',
                'meta_keywords' => 'диета бойца, питание ММА, сгонка веса UFC',
                'content' => [
                    ['type' => 'paragraph', 'content' => 'Питание — это 70% успеха в ММА. Профессиональные бойцы UFC работают с диетологами и следуют строгим планам питания. Разбираем основные принципы диеты чемпионов.'],
                    ['type' => 'quote', 'content' => 'Abs are made in the kitchen, not in the gym. Пресс делается на кухне, а не в зале.', 'author' => 'Жорж Сен-Пьер'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Основы питания бойца'],
                    ['type' => 'paragraph', 'content' => 'Рацион бойца строится на трёх китах: белки для восстановления мышц, углеводы для энергии и полезные жиры для гормонального баланса.'],
                    ['type' => 'list', 'items' => [
                        '40% углеводов — энергия для тренировок',
                        '30% белков — восстановление и рост мышц',
                        '30% жиров — гормональный баланс',
                    ]],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Примерное меню бойца на день'],
                    ['type' => 'list', 'items' => [
                        'Завтрак: Овсянка с ягодами, 4 яйца, авокадо',
                        'Перекус: Протеиновый коктейль, орехи',
                        'Обед: Куриная грудка 200г, бурый рис, овощи',
                        'Перекус: Творог, фрукты',
                        'Ужин: Лосось 200г, киноа, салат',
                        'Перед сном: Казеиновый протеин',
                    ]],
                    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Что ест Хабиб Нурмагомедов в день тренировки'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Сгонка веса'],
                    ['type' => 'paragraph', 'content' => 'За неделю до взвешивания бойцы начинают сгонку веса. Это опасный процесс, который должен проходить под контролем специалистов.'],
                    ['type' => 'quote', 'content' => 'Сгонка веса — это наука. Неправильный подход может стоить тебе здоровья и карьеры.', 'author' => 'Майк Долче, диетолог UFC'],
                    ['type' => 'link', 'url' => 'https://www.dolcediet.com', 'text' => 'Официальный сайт диеты Долче'],
                ],
            ],
            [
                'title' => 'Тренировочный процесс бойца UFC: От новичка до профи',
                'image' => 'blog-5.jpg',
                'category_id' => $cat['Тренировки'],
                'meta_title' => 'Тренировочный процесс бойца UFC: От новичка до профи',
                'meta_description' => 'Как строятся тренировки профессиональных бойцов ММА. Программа подготовки к бою в UFC',
                'meta_keywords' => 'тренировки ММА, подготовка к бою UFC, тренировочный лагерь',
                'content' => [
                    ['type' => 'paragraph', 'content' => 'Путь от новичка до профессионального бойца UFC — это годы упорных тренировок, дисциплины и самоотдачи. Рассказываем, как строится тренировочный процесс на разных этапах карьеры.'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Этапы развития бойца'],
                    ['type' => 'list', 'items' => [
                        'Начальный (0-2 года) — освоение базы',
                        'Средний (2-5 лет) — специализация и первые бои',
                        'Продвинутый (5-10 лет) — профессиональная карьера',
                        'Элитный (10+ лет) — чемпионский уровень',
                    ]],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Начальный этап (0-2 года)'],
                    ['type' => 'paragraph', 'content' => 'Новички начинают с освоения базовых техник: стойка, передвижения, прямые удары, простые комбинации. Тренировки 3-4 раза в неделю по 1.5-2 часа.'],
                    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Базовые техники ММА для начинающих'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Тренировочный лагерь перед боем'],
                    ['type' => 'paragraph', 'content' => 'За 8-12 недель до боя начинается тренировочный лагерь. Это самый интенсивный период подготовки.'],
                    ['type' => 'list', 'items' => [
                        'Недели 1-4: Высокий объём, развитие базы',
                        'Недели 5-8: Пик интенсивности, жёсткие спарринги',
                        'Недели 9-10: Снижение нагрузки, отработка тактики',
                        'Недели 11-12: Сгонка веса, лёгкие тренировки',
                    ]],
                    ['type' => 'quote', 'content' => 'Чемпионы не рождаются в октагоне. Они рождаются в тренировочном зале, когда никто не смотрит.', 'author' => 'Дана Уайт'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Типичный день профессионального бойца'],
                    ['type' => 'list', 'items' => [
                        '07:00 — Подъём, лёгкий завтрак',
                        '09:00 — Первая тренировка (техника, спарринги)',
                        '12:00 — Обед, отдых',
                        '16:00 — Вторая тренировка (силовая или кардио)',
                        '19:00 — Ужин',
                        '21:00 — Восстановление (массаж, растяжка)',
                        '22:00 — Сон',
                    ]],
                    ['type' => 'link_preview',
                        'href' => 'https://www.ufc.com/performance-institute',
                        'title' => 'UFC Performance Institute — тренировочный центр чемпионов',
                        'description' => 'Самый передовой тренировочный комплекс в мире смешанных единоборств: наука, медицина, восстановление.',
                        'image' => 'https://www.ufc.com/themes/custom/ufc/assets/img/logo--small.svg',
                        'domain' => 'ufc.com'],
                ],
            ],
            [
                'title' => 'Психология бойца: Как чемпионы UFC готовятся ментально',
                'image' => 'blog-6.jpg',
                'category_id' => $cat['Психология'],
                'meta_title' => 'Психология бойца: Как чемпионы UFC готовятся ментально',
                'meta_description' => 'Ментальная подготовка бойцов UFC. Как справиться со страхом перед боем и давлением',
                'meta_keywords' => 'психология бойца, ментальная подготовка UFC, страх перед боем',
                'content' => [
                    ['type' => 'paragraph', 'content' => 'В октагоне побеждает не только сильнейший, но и тот, кто сильнее ментально. Психологическая подготовка — секретное оружие чемпионов UFC.'],
                    ['type' => 'quote', 'content' => 'Я не боюсь человека, который отработал 10 000 ударов один раз. Я боюсь человека, который отработал один удар 10 000 раз.', 'author' => 'Брюс Ли'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Страх — это нормально'],
                    ['type' => 'paragraph', 'content' => 'Все бойцы испытывают страх перед боем. Даже легенды вроде Жоржа Сен-Пьера открыто говорили об этом. Разница в том, что профессионалы научились использовать страх как топливо.'],
                    ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'caption' => 'Жорж Сен-Пьер о страхе и ментальной подготовке'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Техники ментальной подготовки'],
                    ['type' => 'list', 'items' => [
                        'Визуализация — мысленное проживание боя',
                        'Медитация — контроль эмоций и концентрация',
                        'Дыхательные практики — управление стрессом',
                        'Позитивные аффирмации — укрепление уверенности',
                        'Работа со спортивным психологом',
                    ]],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Визуализация'],
                    ['type' => 'paragraph', 'content' => 'Чемпионы проводят часы, мысленно проживая бой. Они представляют каждый удар, каждое движение, каждый сценарий.'],
                    ['type' => 'quote', 'content' => 'Я видел этот нокаут тысячу раз в своей голове. Когда он случился в реальности, это было просто повторение.', 'author' => 'Конор МакГрегор о нокауте Альдо'],
                    ['type' => 'heading', 'level' => 2, 'content' => 'Работа с давлением'],
                    ['type' => 'paragraph', 'content' => 'Чем выше ставки, тем сильнее давление. Титульные бои, главные события, миллионы зрителей — всё это может сломать психику. Чемпионы учатся воспринимать давление как привилегию.'],
                    ['type' => 'list', 'items' => [
                        'Принимайте давление как комплимент',
                        'Фокусируйтесь на процессе, а не на результате',
                        'Используйте ритуалы для создания комфорта',
                        'Помните: вы уже сделали всю работу на тренировках',
                    ]],
                    ['type' => 'link', 'url' => 'https://www.headspace.com', 'text' => 'Headspace — приложение для медитации'],
                    ['type' => 'paragraph', 'content' => 'Ментальная сила — это навык, который можно тренировать. Работайте над своим мышлением так же усердно, как над техникой. В конечном счёте, самый сложный бой — это бой с самим собой.'],
                ],
            ],
        ];
    }
}