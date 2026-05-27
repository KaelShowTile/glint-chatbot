<?php
namespace App\Services;

use App\Database;
use GuzzleHttp\Client;

class SyncService {
    public static function syncProducts() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT value FROM settings WHERE key = 'product_feed_url'");
        $feedUrl = $stmt->fetchColumn();

        if (empty($feedUrl)) return false;

        $client = new Client();
        try {
            $response = $client->get($feedUrl);
            $xml = simplexml_load_string($response->getBody()->getContents());
            
            // Handle standard Google Merchant Center RSS feed format
            $items = $xml->channel->item ?? [];
            if (empty($items) && isset($xml->item)) { 
                $items = $xml->item;
            }
            if (empty($items) && isset($xml->entry)) {
                $items = $xml->entry;
            }
            
            $feedProducts = [];
            foreach ($items as $item) {
                $g = $item->children('http://base.google.com/ns/1.0');
                if (empty($g)) $g = $item->children('g', true); // fallback

                $id = (string)($g->id ?? $item->id);
                if (empty($id)) continue;

                $title = (string)($g->title ?? $item->title);
                $desc = strip_tags((string)($g->description ?? $item->description));
                $link = (string)($g->link ?? $item->link);
                $image = (string)($g->image_link ?? $item->image_link);
                $price = (string)($g->price ?? $item->price ?? '');
                $category = (string)($g->product_type ?? $g->google_product_category ?? $item->category ?? '');
                $sku = (string)($g->mpn ?? $item->sku ?? $id);

                // New fields
                $sale_price = (string)($g->sale_price ?? $item->sale_price ?? '');
                $availability = (string)($g->availability ?? $item->availability ?? '');
                $brand = (string)($g->brand ?? $item->brand ?? '');
                $color = (string)($g->color ?? $item->color ?? '');
                $material = (string)($g->material ?? $item->material ?? '');
                $size = (string)($g->size ?? $item->size ?? '');

                // Product details
                $details = [];
                if (isset($g->product_detail) || isset($item->product_detail)) {
                    $detailNodes = isset($g->product_detail) ? $g->product_detail : $item->product_detail;
                    foreach ($detailNodes as $detail) {
                        $detail_g = $detail->children('http://base.google.com/ns/1.0');
                        if (empty($detail_g)) $detail_g = $detail->children('g', true);

                        $attrName = (string)($detail_g->attribute_name ?? $detail->attribute_name ?? '');
                        $attrVal = (string)($detail_g->attribute_value ?? $detail->attribute_value ?? '');

                        if (!empty($attrName) && !empty($attrVal)) {
                            $details[] = "{$attrName}: {$attrVal}";
                        }
                    }
                }

                $searchParts = [];
                $searchParts[] = "Name: {$title}";
                if (!empty($category)) $searchParts[] = "Category: {$category}";
                if (!empty($brand)) $searchParts[] = "Brand: {$brand}";
                if (!empty($color)) $searchParts[] = "Color: {$color}";
                if (!empty($material)) $searchParts[] = "Material: {$material}";
                if (!empty($size)) $searchParts[] = "Size: {$size}";
                
                $displayPrice = !empty($sale_price) ? $sale_price : $price;
                if (!empty($displayPrice)) $searchParts[] = "Price: {$displayPrice}";
                if (!empty($availability)) $searchParts[] = "Availability: {$availability}";
                
                if (!empty($details)) {
                    $searchParts[] = "Attributes: " . implode(", ", $details);
                }
                
                if (!empty($desc)) $searchParts[] = "Description: {$desc}";

                $searchContent = implode(". ", $searchParts) . ".";
                $hash = md5($searchContent);

                $feedProducts[$id] = [
                    'sku' => $sku,
                    'hash' => $hash,
                    'search_content' => $searchContent,
                    'payload' => [
                        'type' => 'product',
                        'product_id' => $id,
                        'category' => $category,
                        'product_url' => $link,
                        'thumbnail_url' => $image,
                        'price' => $price,
                        'sale_price' => $sale_price,
                        'name' => $title
                    ]
                ];
            }

            // Get existing products
            $stmt = $db->query("SELECT product_id, hash, qdrant_id FROM products");
            $existingProducts = [];
            while ($row = $stmt->fetch()) {
                $existingProducts[$row['product_id']] = $row;
            }

            $vectorService = new VectorService();
            $llm = new LlmService();

            foreach ($feedProducts as $id => $p) {
                if (!isset($existingProducts[$id])) {
                    // New product
                    $vector = $llm->embed($p['search_content']);
                    $qdrantId = VectorService::generateUuid();
                    
                    $p['payload']['search_content'] = $p['search_content']; 
                    
                    $vectorService->upsert($qdrantId, $vector, $p['payload']);

                    $stmtInsert = $db->prepare("INSERT INTO products (product_id, sku, hash, qdrant_id) VALUES (?, ?, ?, ?)");
                    $stmtInsert->execute([$id, $p['sku'], $p['hash'], $qdrantId]);
                } elseif ($existingProducts[$id]['hash'] !== $p['hash']) {
                    // Update product
                    $qdrantId = $existingProducts[$id]['qdrant_id'];
                    $vector = $llm->embed($p['search_content']);
                    
                    $p['payload']['search_content'] = $p['search_content'];
                    $vectorService->upsert($qdrantId, $vector, $p['payload']);

                    $stmtUpdate = $db->prepare("UPDATE products SET hash = ?, updated_at = CURRENT_TIMESTAMP WHERE product_id = ?");
                    $stmtUpdate->execute([$p['hash'], $id]);
                }

                // Remove from existing to track deletions
                unset($existingProducts[$id]);
            }

            // Remaining items in $existingProducts are deleted
            foreach ($existingProducts as $id => $row) {
                $vectorService->delete($row['qdrant_id']);
                $stmtDel = $db->prepare("DELETE FROM products WHERE product_id = ?");
                $stmtDel->execute([$id]);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Product Sync Error: " . $e->getMessage());
            return false;
        }
    }
}
