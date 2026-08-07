<?php
namespace App\Services;

use App\Database;
use GuzzleHttp\Client;

class SyncService
{
    public static function syncAll()
    {
        try {
            $prepare = self::prepareSync();
            if ($prepare['total'] === 0) return;
            
            $isSyncing = true;
            while ($isSyncing) {
                $result = self::processSyncChunk(15);
                if ($result['status'] === 'complete') {
                    $isSyncing = false;
                }
            }
            self::finalizeSync();
        } catch (\Exception $e) {
            error_log("Cron sync error: " . $e->getMessage());
        }
    }

    public static function prepareSync()
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT value FROM settings WHERE key = 'product_feed_url'");
        $feedUrl = $stmt->fetchColumn();

        if (empty($feedUrl))
            throw new \Exception("Feed URL not configured.");

        $client = new Client();
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
            if (empty($g))
                $g = $item->children('g', true); // fallback

            $id = (string) ($g->id ?? $item->id);
            if (empty($id))
                continue;

            $title = (string) ($g->title ?? $item->title);
            $product_code = '';
            $codeStart = strpos($title, '(Code:');
            if ($codeStart !== false) {
                $codeEnd = strpos($title, ')', $codeStart);
                if ($codeEnd !== false) {
                    $product_code = trim(substr($title, $codeStart + 6, $codeEnd - $codeStart - 6));
                }
            }
            $desc = strip_tags((string) ($g->description ?? $item->description));
            $link = (string) ($g->link ?? $item->link);
            $image = (string) ($g->image_link ?? $item->image_link);
            
            $available_images = [];
            if (!empty($image)) {
                $available_images[] = $image;
            }
            if (isset($g->additional_image_link)) {
                foreach ($g->additional_image_link as $addImg) {
                    $available_images[] = (string) $addImg;
                }
            }
            if (isset($item->additional_image_link)) {
                foreach ($item->additional_image_link as $addImg) {
                    $available_images[] = (string) $addImg;
                }
            }
            // Remove duplicates
            $available_images = array_values(array_unique($available_images));

            $price = (string) ($g->price ?? $item->price ?? '');
            $category = (string) ($g->product_type ?? $g->google_product_category ?? $item->category ?? '');
            $sku = (string) ($g->mpn ?? $item->sku ?? $id);

            // New fields
            $sale_price = (string) ($g->sale_price ?? $item->sale_price ?? '');
            $availability = (string) ($g->availability ?? $item->availability ?? '');
            $brand = (string) ($g->brand ?? $item->brand ?? '');
            $color = (string) ($g->color ?? $item->color ?? '');
            $material = (string) ($g->material ?? $item->material ?? '');
            $size = (string) ($g->size ?? $item->size ?? '');

            // Product details
            $details = [];
            if (isset($g->product_detail) || isset($item->product_detail)) {
                $detailNodes = isset($g->product_detail) ? $g->product_detail : $item->product_detail;
                foreach ($detailNodes as $detail) {
                    $detail_g = $detail->children('http://base.google.com/ns/1.0');
                    if (empty($detail_g))
                        $detail_g = $detail->children('g', true);

                    $attrName = (string) ($detail_g->attribute_name ?? $detail->attribute_name ?? '');
                    $attrVal = (string) ($detail_g->attribute_value ?? $detail->attribute_value ?? '');

                    if (!empty($attrName) && !empty($attrVal)) {
                        $details[] = "{$attrName}: {$attrVal}";
                    }
                }
            }

            $searchParts = [];
            $searchParts[] = "Name: {$title}";
            if (!empty($product_code))
                $searchParts[] = "Product Code: {$product_code}";
            if (!empty($category))
                $searchParts[] = "Category: {$category}";
            if (!empty($brand))
                $searchParts[] = "Brand: {$brand}";
            if (!empty($color))
                $searchParts[] = "Color: {$color}";
            if (!empty($material))
                $searchParts[] = "Material: {$material}";
            if (!empty($size))
                $searchParts[] = "Size: {$size}";

            $displayPrice = !empty($sale_price) ? $sale_price : $price;
            if (!empty($displayPrice))
                $searchParts[] = "Price: {$displayPrice}";
            if (!empty($availability))
                $searchParts[] = "Availability: {$availability}";

            if (!empty($details)) {
                $searchParts[] = "Attributes: " . implode(", ", $details);
            }

            if (!empty($desc))
                $searchParts[] = "Description: {$desc}";

            $searchContent = implode(". ", $searchParts) . ".";
            $hash = md5($searchContent);

            $feedProducts[$id] = [
                'sku' => $sku,
                'hash' => $hash,
                'search_content' => $searchContent,
                'payload' => [
                    'type' => 'product',
                    'product_id' => $id,
                    'product_code' => $product_code,
                    'category' => $category,
                    'product_url' => $link,
                    'thumbnail_url' => $image,
                    'price' => $price,
                    'sale_price' => $sale_price,
                    'name' => $title
                ],
                'available_images' => json_encode($available_images)
            ];
        }

        // Get existing products
        $stmt = $db->query("SELECT product_id, hash, qdrant_id FROM products");
        $existingProducts = [];
        while ($row = $stmt->fetch()) {
            $existingProducts[$row['product_id']] = $row;
        }

        $pendingQueue = [];
        foreach ($feedProducts as $id => $p) {
            if (!isset($existingProducts[$id])) {
                $p['action'] = 'insert';
                $pendingQueue[] = $p;
            } elseif ($existingProducts[$id]['hash'] !== $p['hash']) {
                $p['action'] = 'update';
                $p['qdrant_id'] = $existingProducts[$id]['qdrant_id'];
                $pendingQueue[] = $p;
            }
            unset($existingProducts[$id]);
        }

        $deleteQueue = [];
        foreach ($existingProducts as $id => $row) {
            $deleteQueue[] = $row;
        }

        $queueData = [
            'pending' => $pendingQueue,
            'delete' => $deleteQueue,
            'total_pending' => count($pendingQueue),
            'total_delete' => count($deleteQueue)
        ];

        $dataDir = __DIR__ . '/../../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        file_put_contents($dataDir . '/sync_queue.json', json_encode($queueData));

        return [
            'total' => count($pendingQueue) + count($deleteQueue),
            'pending' => count($pendingQueue),
            'delete' => count($deleteQueue)
        ];
    }

    public static function processSyncChunk($batchSize = 10)
    {
        $queueFile = __DIR__ . '/../../data/sync_queue.json';
        if (!file_exists($queueFile)) {
            throw new \Exception("Sync queue not found.");
        }

        $queueData = json_decode(file_get_contents($queueFile), true);
        if (!$queueData) {
            throw new \Exception("Invalid queue data.");
        }

        $db = Database::getConnection();
        $vectorService = new VectorService();
        $llm = new LlmService();

        $processed = 0;

        // Process pending (inserts and updates)
        while ($processed < $batchSize && !empty($queueData['pending'])) {
            $p = array_shift($queueData['pending']);
            $id = $p['payload']['product_id'];

            try {
                // Determine image to embed
                $imageBase64 = null;
                $mimeType = 'image/jpeg';
                $coverUrl = $p['payload']['thumbnail_url'] ?? '';
                if (!empty($coverUrl)) {
                    $imgData = @file_get_contents($coverUrl);
                    if ($imgData) {
                        $im = @imagecreatefromstring($imgData);
                        if ($im !== false) {
                            $width = imagesx($im);
                            $height = imagesy($im);
                            $maxSize = 512;
                            if ($width > $maxSize || $height > $maxSize) {
                                $ratio = min($maxSize / $width, $maxSize / $height);
                                $newWidth = (int)($width * $ratio);
                                $newHeight = (int)($height * $ratio);
                                $newIm = imagecreatetruecolor($newWidth, $newHeight);
                                imagealphablending($newIm, false);
                                imagesavealpha($newIm, true);
                                $transparent = imagecolorallocatealpha($newIm, 255, 255, 255, 127);
                                imagefilledrectangle($newIm, 0, 0, $newWidth, $newHeight, $transparent);
                                imagecopyresampled($newIm, $im, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                                imagedestroy($im);
                                $im = $newIm;
                            }
                            $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
                            $white = imagecolorallocate($bg, 255, 255, 255);
                            imagefill($bg, 0, 0, $white);
                            imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                            
                            ob_start();
                            imagejpeg($bg, null, 80);
                            $imageBase64 = base64_encode(ob_get_clean());
                            imagedestroy($im);
                            imagedestroy($bg);
                        }
                    }
                }

                $chunks = [];
                // Chunk 1: Basic info
                $basicInfo = "Product: {$p['payload']['name']}\nPrice: {$p['payload']['price']}";
                if (!empty($p['payload']['sale_price'])) $basicInfo .= " (Sale: {$p['payload']['sale_price']})";
                $chunks[] = $basicInfo;
                
                // Chunk 2+: Description paragraphs
                // In SyncService, the original description is only in search_content. 
                // Wait, search_content is full text. We can just chunk search_content.
                $paragraphs = preg_split('/\n+/', $p['search_content']);
                $currentChunk = "";
                foreach ($paragraphs as $para) {
                    $para = trim($para);
                    if (empty($para)) continue;
                    if (strlen($currentChunk) + strlen($para) > 500) {
                        if (!empty($currentChunk)) {
                            $chunks[] = $currentChunk;
                        }
                        $currentChunk = $para;
                    } else {
                        $currentChunk .= (empty($currentChunk) ? "" : "\n") . $para;
                    }
                }
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                }
                
                $p['payload']['search_content'] = $p['search_content'];
                $p['payload']['thumbnail_url'] = $coverUrl; // Ensure it's in payload

                $qdrantIds = [];
                
                // If update, delete old Qdrant IDs first
                if ($p['action'] !== 'insert') {
                    $oldQdrantId = $p['qdrant_id'] ?? '';
                    if (!empty($oldQdrantId)) {
                        $oldIds = json_decode($oldQdrantId, true);
                        if (is_array($oldIds)) {
                            foreach ($oldIds as $oid) $vectorService->delete($oid);
                        } else {
                            $vectorService->delete($oldQdrantId);
                        }
                    }
                }
                
                foreach ($chunks as $idx => $chunk) {
                    // Only attach image to the first chunk to save tokens
                    $chunkImageBase64 = ($idx === 0) ? $imageBase64 : null;
                    
                    $vector = $llm->embed($chunk, $chunkImageBase64, $mimeType);
                    $sparseVector = $llm->generateSparseVector($chunk);
                    
                    $chunkQdrantId = VectorService::generateUuid();
                    $vectorService->upsert($chunkQdrantId, $vector, $p['payload'], $sparseVector);
                    $qdrantIds[] = $chunkQdrantId;
                }
                
                $qdrantIdsJson = json_encode($qdrantIds);

                if ($p['action'] === 'insert') {
                    $stmtInsert = $db->prepare("INSERT INTO products (product_id, sku, hash, qdrant_id, available_images) VALUES (?, ?, ?, ?, ?)");
                    $stmtInsert->execute([$id, $p['sku'], $p['hash'], $qdrantIdsJson, $p['available_images']]);
                } else {
                    $stmtUpdate = $db->prepare("UPDATE products SET hash = ?, qdrant_id = ?, available_images = ?, updated_at = CURRENT_TIMESTAMP WHERE product_id = ?");
                    $stmtUpdate->execute([$p['hash'], $qdrantIdsJson, $p['available_images'], $id]);
                }
            } catch (\Exception $e) {
                error_log("Error syncing product $id: " . $e->getMessage());
            }
            $processed++;
        }

        // Process deletes if pending is empty
        while ($processed < $batchSize && !empty($queueData['delete'])) {
            $row = array_shift($queueData['delete']);
            $id = $row['product_id'];

            try {
                if (!empty($row['qdrant_id'])) {
                    $ids = json_decode($row['qdrant_id'], true);
                    if (is_array($ids)) {
                        foreach ($ids as $oid) $vectorService->delete($oid);
                    } else {
                        $vectorService->delete($row['qdrant_id']);
                    }
                }
                $stmtDel = $db->prepare("DELETE FROM products WHERE product_id = ?");
                $stmtDel->execute([$id]);
            } catch (\Exception $e) {
                error_log("Error deleting product $id: " . $e->getMessage());
            }
            $processed++;
        }

        file_put_contents($queueFile, json_encode($queueData));

        $remaining = count($queueData['pending']) + count($queueData['delete']);
        $total = $queueData['total_pending'] + $queueData['total_delete'];

        return [
            'status' => $remaining > 0 ? 'syncing' : 'complete',
            'remaining' => $remaining,
            'processed' => $total - $remaining,
            'total' => $total
        ];
    }

    public static function finalizeSync()
    {
        $queueFile = __DIR__ . '/../../data/sync_queue.json';
        if (file_exists($queueFile)) {
            unlink($queueFile);
        }
        return true;
    }
}
