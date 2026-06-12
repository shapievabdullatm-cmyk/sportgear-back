<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MeiliSearch\Client;

class MeilisearchSetupCommand extends Command
{
    protected $signature   = 'meilisearch:setup';
    protected $description = 'Configure Meilisearch indexes (searchable attributes, typo tolerance, synonyms)';

    public function handle(): int
    {
        $client = new Client(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key') ?: null,
        );

        $this->setupProducts($client);
        $this->setupCategories($client);

        $this->info('Meilisearch indexes configured.');

        return self::SUCCESS;
    }

    private function setupProducts(Client $client): void
    {
        $index = $client->index('products');

        // Порядок важен — чем выше в списке, тем больший вес при ранжировании.
        $index->updateSearchableAttributes([
            'title',
            'external_title',
            'article',
            'barcodes',
            'param_values',
            'meta_keywords',
        ]);

        $index->updateFilterableAttributes([
            'is_active',
            'parent_id',
            'category_id',
            'price',
        ]);

        $index->updateSortableAttributes(['price']);

        // words → typo → proximity → attribute → sort → exactness
        $index->updateRankingRules([
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
        ]);

        $index->updateTypoTolerance([
            'enabled'            => true,
            'minWordSizeForTypos' => [
                'oneTypo'  => 4,
                'twoTypos' => 7,
            ],
        ]);

        $index->updateSynonyms($this->buildSynonyms());

        $this->line('  ✓ products index configured');
    }

    private function setupCategories(Client $client): void
    {
        $index = $client->index('categories');

        $index->updateSearchableAttributes(['title', 'keywords']);

        $index->updateTypoTolerance([
            'enabled'            => true,
            'minWordSizeForTypos' => [
                'oneTypo'  => 4,
                'twoTypos' => 7,
            ],
        ]);

        $this->line('  ✓ categories index configured');
    }

    /**
     * Bidirectional Russian synonyms for common color adjective forms.
     * Мeilisearch requires each word as a key pointing to all its synonyms.
     */
    private function buildSynonyms(): array
    {
        $groups = [
            ['белый',       'белая',       'белое',       'белые',       'белого',       'белой',       'белым'],
            ['чёрный',      'черный',      'чёрная',      'черная',      'чёрного',      'черного',     'чёрные',    'черные'],
            ['красный',     'красная',     'красного',    'красной',     'красные'],
            ['синий',       'синяя',       'синего',      'синей',       'синие'],
            ['зелёный',     'зеленый',     'зелёная',     'зеленая',     'зелёного',     'зеленого',    'зелёные',   'зеленые'],
            ['жёлтый',      'желтый',      'жёлтая',      'желтая',      'жёлтого',      'желтого',     'жёлтые',    'желтые'],
            ['оранжевый',   'оранжевая',   'оранжевого',  'оранжевые'],
            ['фиолетовый',  'фиолетовая',  'фиолетового', 'фиолетовые'],
            ['розовый',     'розовая',     'розового',    'розовые'],
            ['серый',       'серая',       'серого',      'серые'],
            ['коричневый',  'коричневая',  'коричневого', 'коричневые'],
            ['бежевый',     'бежевая',     'бежевого',    'бежевые'],
            ['голубой',     'голубая',     'голубого',    'голубые'],
            ['золотой',     'золотая',     'золотого',    'золотые'],
            ['серебряный',  'серебряная',  'серебряного', 'серебряные'],
            ['бордовый',    'бордовая',    'бордового',   'бордовые'],
            ['тёмный',      'темный',      'тёмная',      'темная',      'тёмно',        'темно'],
            ['светлый',     'светлая',     'светлого',    'светлые'],
            ['мужской',     'мужская',     'мужского'],
            ['женский',     'женская',     'женского'],
            ['детский',     'детская',     'детского'],
            ['спортивный',  'спортивная',  'спортивного', 'спортивные'],
        ];

        $synonyms = [];

        foreach ($groups as $group) {
            foreach ($group as $word) {
                $rest = array_values(array_filter($group, fn($w) => $w !== $word));
                $synonyms[$word] = $rest;
            }
        }

        return $synonyms;
    }
}