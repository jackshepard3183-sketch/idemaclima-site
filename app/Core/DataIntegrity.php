<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class DataIntegrity
{
    public static function categoryParentError(PDO $pdo, int $categoryId, ?int $parentId): ?string
    {
        if ($parentId === null) return null;
        if ($categoryId > 0 && $parentId === $categoryId) return 'Una categoria non può essere genitore di se stessa.';

        $s = $pdo->prepare('SELECT id,parent_id FROM product_categories WHERE id=?');
        $s->execute([$parentId]);
        $parent = $s->fetch(PDO::FETCH_ASSOC);
        if (!$parent) return 'Categoria genitore non valida.';

        if ($categoryId < 1) return null;
        $seen = [];
        $cursor = $parentId;
        while ($cursor !== null) {
            if (isset($seen[$cursor])) return 'Gerarchia categorie non valida: ciclo rilevato.';
            $seen[$cursor] = true;
            if ($cursor === $categoryId) return 'La categoria selezionata creerebbe un ciclo nella gerarchia.';
            $q = $pdo->prepare('SELECT parent_id FROM product_categories WHERE id=?');
            $q->execute([$cursor]);
            $value = $q->fetchColumn();
            $cursor = ($value === false || $value === null) ? null : (int)$value;
        }
        return null;
    }

    public static function categoryExists(PDO $pdo, int $categoryId): bool
    {
        $s = $pdo->prepare('SELECT 1 FROM product_categories WHERE id=? LIMIT 1');
        $s->execute([$categoryId]);
        return (bool)$s->fetchColumn();
    }

    /** @return array{category_id:?int,product_id:?int,model_id:?int,error:?string} */
    public static function normalizeDocumentLink(PDO $pdo, ?int $categoryId, ?int $productId, ?int $modelId): array
    {
        if ($modelId !== null) {
            $s = $pdo->prepare('SELECT m.product_id,p.category_id FROM product_models m JOIN products p ON p.id=m.product_id WHERE m.id=?');
            $s->execute([$modelId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!$row) return compact('categoryId','productId','modelId') + ['category_id'=>$categoryId,'product_id'=>$productId,'model_id'=>$modelId,'error'=>'Modello selezionato non valido.'];
            $actualProduct = (int)$row['product_id'];
            $actualCategory = (int)$row['category_id'];
            if ($productId !== null && $productId !== $actualProduct) return ['category_id'=>$categoryId,'product_id'=>$productId,'model_id'=>$modelId,'error'=>'Il modello selezionato non appartiene al prodotto indicato.'];
            if ($categoryId !== null && $categoryId !== $actualCategory) return ['category_id'=>$categoryId,'product_id'=>$productId,'model_id'=>$modelId,'error'=>'Il modello selezionato non appartiene alla categoria indicata.'];
            return ['category_id'=>$actualCategory,'product_id'=>$actualProduct,'model_id'=>$modelId,'error'=>null];
        }

        if ($productId !== null) {
            $s = $pdo->prepare('SELECT category_id FROM products WHERE id=?');
            $s->execute([$productId]);
            $actualCategory = $s->fetchColumn();
            if ($actualCategory === false) return ['category_id'=>$categoryId,'product_id'=>$productId,'model_id'=>null,'error'=>'Prodotto selezionato non valido.'];
            $actualCategory = (int)$actualCategory;
            if ($categoryId !== null && $categoryId !== $actualCategory) return ['category_id'=>$categoryId,'product_id'=>$productId,'model_id'=>null,'error'=>'Il prodotto selezionato non appartiene alla categoria indicata.'];
            return ['category_id'=>$actualCategory,'product_id'=>$productId,'model_id'=>null,'error'=>null];
        }

        if ($categoryId !== null && !self::categoryExists($pdo, $categoryId)) {
            return ['category_id'=>$categoryId,'product_id'=>null,'model_id'=>null,'error'=>'Categoria selezionata non valida.'];
        }

        return ['category_id'=>$categoryId,'product_id'=>null,'model_id'=>null,'error'=>null];
    }
}
